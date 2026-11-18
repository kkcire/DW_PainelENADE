# Painel ENADE · Recorte R05 (História e Pedagogia)

Sistema web desenvolvido em **PHP puro + PDO + PostgreSQL** para visualização de indicadores do ENADE (Recorte R05 — Licenciaturas em História e Pedagogia, edições de 2015 a 2025) e gerenciamento completo (**CRUD com upload de evidências**) de Ações de Acompanhamento Pedagógico.

---

## Sumário

1. [Arquitetura do Projeto](#arquitetura-do-projeto)
2. [Padrões de Projeto Aplicados](#padrões-de-projeto-aplicados)
3. [Mapa de Rotas e Funcionalidades](#mapa-de-rotas-e-funcionalidades)
4. [Regras de Segurança e Validação](#regras-de-segurança-e-validação)
5. [Banco de dados (Instalação, Modelagem e Views)](#banco-de-dados)

---

## Arquitetura do Projeto

O projeto organiza o código em camadas bem definidas dentro de `app/`, separando **acesso a dados (`Models/`)**, **regras de negócio (`Services/`)**, **controle de fluxo (`Thin Controllers`)** e **apresentação (`views/`)**:

```text
DW_Grupo06_PainelENADE/
├── README.md                           # Documentação geral e guia de instalação
├── app/
│   ├── config.php                      # Conexão PDO com PostgreSQL (Singleton via static $pdo)
│   ├── config.local.php                # (Ignorado no Git) Senha local do PostgreSQL de cada integrante
│   ├── auth.php                        # Middleware de sessão, login/logout, flash messages e escape XSS
│   │
│   ├── Models/                         # Isola todos os SELECT / INSERT / UPDATE / DELETE (PDO)
│   │   ├── AcaoRepository.php          # CRUD na tabela acao e consultas na view vw_acao
│   │   └── IndicadorEnadeRepository.php# Consultas somente-leitura nas views do ENADE
│   │
│   ├── Services/                       # (SRP) Isola regras de negócio, cálculos e upload
│   │   ├── UploadEvidenciaService.php  # Valida PDF/PNG/JPG, limite de 2 MB e MIME real
│   │   └── PainelEstatisticasService.php # Calcula cards, barras comparativas, série histórica e síntese
│   │
│   ├── login.php                       # Thin Controller: processa login e carrega views/login.view.php
│   ├── logout.php                      # Thin Controller: destrói sessão e redireciona ao login
│   ├── painel.php                      # Thin Controller: aciona PainelEstatisticasService e carrega a View
│   │
│   ├── acoes/                          # Thin Controllers do CRUD de Ações de Acompanhamento
│   │   ├── listar.php                  # Filtra ações por oferta/situação e carrega lista.view.php
│   │   ├── criar.php                   # Recebe $_POST/$_FILES -> UploadService -> AcaoRepository
│   │   ├── editar.php                  # Atualiza dados/anexo e reaproveita form.view.php
│   │   ├── visualizar.php              # Exibe detalhes completos da ação e preview do anexo
│   │   └── excluir.php                 # Remove anexo físico de uploads/ e exclui registro no banco
│   │
│   ├── views/                          # HTML puro desacoplado de consultas SQL
│   │   ├── login.view.php              # Tela de autenticação
│   │   ├── painel.view.php             # Dashboard com cards, barras, histórico 2015–2025 e síntese
│   │   └── acoes/
│   │       ├── form.view.php           # Formulário único reaproveitado no criar.php e editar.php
│   │       ├── lista.view.php          # Listagem com filtros e resumo por situação
│   │       └── visualizar.view.php     # Página de detalhes e visualização de evidência
│   │
│   ├── includes/
│   │   ├── header.php                  # Cabeçalho HTML5, barra de navegação e alertas flash
│   │   └── footer.php                  # Rodapé institucional e inclusão do JS
│   │
│   ├── uploads/
│   │   └── .htaccess                   # Bloqueia listagem e execução de scripts maliciosos no upload
│   │
│   └── assets/
│       ├── css/style.css               # Estilização responsiva do painel, barras, badges e tabelas
│       └── js/validacao.js             # Validação client-side (2 MB, PDF/PNG/JPG e confirmação)
│
├── banco/
│   ├── estrutura.sql                   # Script DDL (tabelas, constraints, índices e 5 views)
│   └── dados_iniciais.sql              # Carga do Recorte R05 (3 ofertas, 33 resultados e usuário admin)
│
└── docs/
    ├── ARQUITETURA.md                  # Documentação técnica detalhada das camadas e fluxos
    └── MANUAL_DO_SISTEMA.md            # Guia de telas, rotas, parâmetros e contratos das views
```

---

## Padrões de Projeto Aplicados

| Padrão / Princípio | Onde é aplicado | Benefício no projeto |
|---|---|---|
| **Singleton (Procedural)** | `conectar()` em `app/config.php` | Usa `static $pdo = null` para garantir uma única conexão PDO aberta por requisição HTTP. |
| **Repository Pattern** | `app/Models/AcaoRepository.php` e `IndicadorEnadeRepository.php` | Centraliza todas as queries SQL (`PDO::prepare` / `PDO::query`). Nenhum controller ou view executa SQL diretamente. |
| **Service Layer & SRP** | `app/Services/UploadEvidenciaService.php` e `PainelEstatisticasService.php` | Cada classe tem uma única responsabilidade de negócio: validar/armazenar evidências ou consolidar indicadores do painel. |
| **Thin Controllers** | `app/painel.php`, `app/login.php` e `app/acoes/*.php` | Arquivos enxutos que apenas leem `$_GET`/`$_POST`/`$_FILES`, chamam Services/Models e incluem a View. |
| **Template View & Reuso** | `app/views/` (especialmente `form.view.php`) | O HTML fica separado da lógica de banco, e o mesmo formulário atende tanto ao cadastro (`criar.php`) quanto à edição (`editar.php`). |

---

## Mapa de Rotas e Funcionalidades

| URL (relativa a `app/`) | Arquivo Controller | View Renderizada | Descrição |
|---|---|---|---|
| `/login.php` | `app/login.php` | `views/login.view.php` | Autentica usuário via `password_verify()` e inicia sessão. |
| `/logout.php` | `app/logout.php` | *(Redirecionamento)* | Limpa sessão e cookies e redireciona para `login.php`. |
| `/painel.php` | `app/painel.php` | `views/painel.view.php` | Exibe cards, barras FG × CE, síntese, histórico 2015–2025 e resumo de ações da oferta. Aceita `?id_oferta=X&ano=Y`. |
| `/acoes/listar.php` | `app/acoes/listar.php` | `views/acoes/lista.view.php` | Lista ações de acompanhamento com filtro por oferta (`?id_oferta=X`) e situação (`?situacao=Planejada`). |
| `/acoes/criar.php` | `app/acoes/criar.php` | `views/acoes/form.view.php` | Cadastra nova ação vinculada a uma oferta, com anexo opcional (`PDF/PNG/JPG` até 2 MB). |
| `/acoes/editar.php?id=X` | `app/acoes/editar.php` | `views/acoes/form.view.php` | Edita dados da ação, permite substituir ou remover o anexo existente. |
| `/acoes/visualizar.php?id=X` | `app/acoes/visualizar.php` | `views/acoes/visualizar.view.php` | Mostra detalhes completos da ação e exibe/baixa o anexo comprobatório. |
| `/acoes/excluir.php` | `app/acoes/excluir.php` | *(Redirecionamento)* | Remove o arquivo físico de `app/uploads/` e apaga o registro no banco. |

---

## Regras de Segurança e Validação

1. **Prevenção contra SQL Injection:** Todas as consultas parametrizadas nos repositórios utilizam `PDO::prepare()` com *named placeholders* (`:id_oferta`, `:ano`, `:login`, etc.).
2. **Prevenção contra XSS (Cross-Site Scripting):** Toda saída dinâmica nas views passa pelo helper `e()` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`).
3. **Autenticação Segura:** Senhas armazenadas com hash `bcrypt` (`password_hash` / `password_verify`) e regeneração de ID de sessão (`session_regenerate_id(true)`) no login para evitar *Session Fixation*.
4. **Upload Seguro de Evidências (`UploadEvidenciaService` + `.htaccess`):**
   - Limite estrito de **2 MB** (`2 * 1024 * 1024` bytes).
   - Dupla verificação de tipo: extensão (`.pdf`, `.png`, `.jpg`, `.jpeg`) + inspeção do **MIME type real** via `finfo_file()` (`application/pdf`, `image/png`, `image/jpeg`).
   - Renomeação automática (`evidencia_YYYYmmdd_HHMMSS_random.ext`) para impedir *Path Traversal* e sobrescrita de arquivos.
   - Arquivo `app/uploads/.htaccess` desabilita a execução de scripts (`.php`, `.phtml`, `.sh`, `.py`, etc.) e bloqueia listagem de diretório (`Options -Indexes`).

---

## Banco de dados

O projeto usa **PostgreSQL** (acessado pelo PHP via PDO). O XAMPP é usado apenas para Apache e PHP; o MySQL/MariaDB do XAMPP **não** é utilizado.

> **Importante:** o banco não vem pronto dentro do projeto. **Cada pessoa precisa instalar e configurar o seu próprio PostgreSQL**, criar o banco e executar os scripts da pasta `banco/`. Não existe um servidor compartilhado.

### Arquivos

| Arquivo | Função |
|---|---|
| `banco/estrutura.sql` | Cria tabelas, restrições e views. Pode ser executado várias vezes: ele **apaga e recria tudo do zero**. |
| `banco/dados_iniciais.sql` | Insere recorte, ofertas, resultados e o usuário de demonstração. Deve ser executado **uma única vez**, depois do `estrutura.sql`. |
| `app/config.php` | Função `conectar()`, que devolve a conexão PDO. Traz os valores padronizados e uma senha de exemplo. |
| `app/config.local.php` | Arquivo **local, não versionado**, com a senha de cada pessoa (ver abaixo). |

### Padronizações

Para que todos os integrantes (e o professor) rodem o projeto do mesmo jeito, o grupo adota:

| Item | Padrão |
|---|---|
| Nome do banco | `dw_projeto_enade` |
| Host / porta | `localhost` / `5432` |
| Usuário do PostgreSQL | `postgres` |
| Senha do PostgreSQL | uma por pessoa, no formato `[nome]_senha_banco`, com o primeiro nome em minúsculas (ex.: `erick_senha_banco`). Informada apenas no `app/config.local.php` |
| Encoding do banco | `UTF8` |
| Pasta do projeto | `C:\xampp\htdocs\DW_Grupo06_PainelENADE\` |
| Ordem dos scripts | `estrutura.sql` e depois `dados_iniciais.sql` |
| Nomes no banco | `snake_case`, sem acento; tabelas no singular; chaves primárias `id_<tabela>`; views com prefixo `vw_` |
| Usuário de demonstração | login `admin`, senha `admin123` |

### Instalação e configuração (passo a passo)

1. **Instalar o XAMPP** (Apache e PHP). Apenas o **Apache** é necessário; o MySQL do XAMPP não é usado.
2. **Colocar o projeto em `htdocs`:** copiar a pasta `DW_Grupo06_PainelENADE` para `C:\xampp\htdocs\`. No *XAMPP Control Panel*, iniciar o **Apache**. A aplicação fica em `http://localhost/DW_Grupo06_PainelENADE/app/login.php`.
3. **Instalar o PostgreSQL** (o instalador do Windows já inclui o pgAdmin 4). Quando o instalador pedir a senha do superusuário `postgres`, definir a sua senha no formato padronizado (ex.: `erick_senha_banco`).
4. **Habilitar o driver no PHP:** em `C:\xampp\php\php.ini`, remover o `;` das linhas abaixo e **reiniciar o Apache**:
   ```
   extension=pdo_pgsql
   extension=pgsql
   ```
5. **Criar o banco no pgAdmin:**
   1. Abrir o **pgAdmin 4**.
   2. No painel lateral, expandir a pasta **Servers**. Vai aparecer o servidor com a sua versão do PostgreSQL (por exemplo, "PostgreSQL 17").
   3. Clicar no servidor, informar a senha do usuário `postgres` e expandi-lo.
   4. Botão direito em **Databases** → *Create* → *Database…*, nome `dw_projeto_enade`, encoding `UTF8`, e salvar.
6. **Executar os scripts**, nesta ordem, no banco `dw_projeto_enade`: botão direito no banco → *Query Tool* → ícone de abrir arquivo (pasta) → escolher o arquivo → executar com **F5**.
   1. `banco/estrutura.sql`
   2. `banco/dados_iniciais.sql`

   Confirme que o nome `dw_projeto_enade` aparece na aba do *Query Tool* antes de executar. O `dados_iniciais.sql` deve rodar **uma única vez**; para recomeçar, execute o `estrutura.sql` de novo (ele recria tudo).
7. **Criar o arquivo de senha local:** criar manualmente `app/config.local.php` com a senha definida no passo 3:
   ```php
   <?php
   return ['senha' => 'seunome_senha_banco'];
   ```
8. **Conferir** no *Query Tool*:
   ```sql
   SELECT * FROM vw_cobertura;
   ```
   Resultado esperado: 1 recorte (R05), 3 ofertas, 33 resultados (3 oficiais e 30 simulados), anos de 2015 a 2025.

**Usuário de demonstração:** login `admin`, senha `admin123` (armazenada como hash; ver "Autenticação").

### Configuração da conexão (`config.php` e `config.local.php`)

- `app/config.php` é versionado e define a função `conectar()`, que abre a conexão PDO com os valores padronizados (`localhost`, porta `5432`, banco `dw_projeto_enade`, usuário `postgres`). A senha que ele traz (`SUA_SENHA_AQUI`) é apenas um exemplo.
- Se existir `app/config.local.php`, os valores retornados por ele **substituem** os do `config.php`. É lá que cada pessoa informa a sua senha (`[nome]_senha_banco`). As demais chaves (`host`, `porta`, `banco`, `usuario`) também podem ser sobrescritas ali.
- O `config.local.php` está no `.gitignore` e **não deve ir para o Git nem para o ZIP de entrega**.
- Quem preferir pode editar a senha direto no `config.php`, sem criar o arquivo local.

Nas páginas PHP, a conexão é obtida assim:

```php
require_once __DIR__ . '/config.php';   // ajustar o caminho conforme a pasta
$pdo = conectar();
```

Para trocar a senha de um PostgreSQL já instalado, execute no *Query Tool*:

```sql
ALTER USER postgres PASSWORD 'seunome_senha_banco';
```

### Modelo de dados e normalização

Os dados do recorte R05 chegaram em uma **tabela única** (CSV), na qual curso, grau acadêmico, modalidade e dados do recorte se repetiam em todas as linhas (um registro por oferta e por ano). Para evitar essa repetição e possíveis inconsistências, o modelo foi normalizado:

- **1ª forma normal:** cada coluna guarda um valor atômico; cada linha é identificada por uma chave primária.
- **2ª forma normal:** as notas e os conceitos dependem de **oferta + ano**, e ficam em `resultado`. Curso, grau e modalidade dependem **só da oferta**, e foram para `oferta`.
- **3ª forma normal:** nome e foco do recorte dependem do recorte (e não da oferta), e foram para `recorte`. A **participação** (%) é um dado derivado e **não é gravada**: é calculada nas views (`participantes ÷ inscritos × 100`).

```
recorte 1 ─── N oferta 1 ─── N resultado
                     1 ─── N acao

usuario (independente)
```

| Tabela | Conteúdo | Regras principais |
|---|---|---|
| `recorte` | Recorte do grupo (R05 – História e Pedagogia) | `codigo` único |
| `oferta` | Curso + grau acadêmico + modalidade (3 ofertas) | combinação `(curso, grau, modalidade)` única |
| `resultado` | Um registro por oferta e por ano (edição) | `(oferta, ano)` único; `participantes <= inscritos`; notas entre 0 e 100; conceito contínuo entre 0 e 5; faixa em `1`–`5` ou `SC`; `origem_dado` obrigatória |
| `acao` | Ações de acompanhamento (CRUD) vinculadas a uma oferta | `situacao` em `Planejada`, `Em andamento` ou `Concluída`; os dois campos de anexo (nome original e nome armazenado) são opcionais e andam juntos |
| `usuario` | Login do sistema | `login` único; senha como hash |

As chaves estrangeiras usam `ON DELETE RESTRICT`: não é possível apagar uma oferta que tenha resultados ou ações, nem um recorte que tenha ofertas.

### Views

Uma **view** é uma consulta salva com nome, que se comporta como uma tabela somente de leitura. Ela **não armazena dados**: a consulta é executada a cada uso. As views concentram os JOINs e os cálculos em um só lugar, de modo que o PHP consulte `SELECT ... FROM vw_resultado WHERE ...` sem repetir a lógica. Os nomes das colunas das views funcionam como **contrato** entre o banco e o back-end/front.

> **Nenhuma view possui `ORDER BY`.** A ordenação é definida por quem consulta (por exemplo, `SELECT * FROM vw_anos ORDER BY ano DESC`).

| View | Uso no painel | Colunas principais |
|---|---|---|
| `vw_oferta` | Filtro de oferta | `id_oferta`, `rotulo` (`Curso · Grau · Modalidade`) |
| `vw_anos` | Filtro de edição | `ano` |
| `vw_resultado` | Cards, histórico, desempenho e síntese | `participacao_pct`, notas de Formação Geral e Componente Específico, `diferenca_fg_ce`, `conceito_continuo`, `conceito_faixa`, `variacao_faixa` (em relação à edição anterior), `ponto_forte`, `ponto_atencao`, `origem_dado` |
| `vw_cobertura` | Card "Cobertura do recorte" | `ofertas`, `resultados`, `resultados_oficiais`, `resultados_simulados`, `primeiro_ano`, `ultimo_ano`, `ofertas_serie_limitada`, `ofertas_sem_oficial` |
| `vw_acao` | Lista de ações e anexos | dados da ação, `oferta_rotulo`, `tem_anexo` |

**Estado "sem dados":** quando a consulta em `vw_resultado` para uma oferta/ano não retorna linhas, a aplicação exibe "Sem dados para esta oferta/edição".

### Origem dos dados (oficial × simulado)

Os dados vêm da **base didática híbrida** fornecida pelo professor (arquivos `R05_enade_2015_2025.csv`, `LEIA-ME.md` e `METODOLOGIA_SIMULACAO.md`):

- `OFICIAL_INEP`: valores publicados pelo Inep (3 registros no R05, todos de Pedagogia; a oferta de **História (EaD) não possui resultado oficial**). Esses registros também trazem a `fonte_oficial`.
- `SIMULADO_DIDATICO`: valores **artificiais**, gerados para o exercício (30 registros). **Não são resultados reais** da instituição ou do ENADE.

A coluna `origem_dado` é obrigatória e deve ser exibida no painel (badge ou legenda) para distinguir "Oficial" de "Simulado".

Os textos de `ponto_forte` e `ponto_atencao` dos registros oficiais foram escritos pelo grupo; nos demais, foram mantidos os textos sugeridos pela própria base didática.

### Autenticação

A tabela `usuario` guarda a senha como **hash bcrypt**, nunca em texto puro. No login, a conferência é feita em PHP:

```php
if ($usuario && password_verify($senhaDigitada, $usuario['senha'])) {
    // login válido
}
```

Para gerar o hash de uma nova senha: `echo password_hash('nova_senha', PASSWORD_DEFAULT);`.

### Problemas comuns

| Mensagem | Causa provável |
|---|---|
| `could not find driver` | `pdo_pgsql` não habilitado no `php.ini` correto, ou o Apache não foi reiniciado |
| `autenticação do tipo senha falhou` | A senha do `config.local.php` é diferente da senha do usuário `postgres` (ou o arquivo tem o nome errado). Ver o comando `ALTER USER` acima |
| `banco de dados "dw_projeto_enade" não existe` | O banco não foi criado no pgAdmin (passo 3) |
| `relação "resultado" não existe` | Os scripts não foram executados no banco `dw_projeto_enade` |
| `transação atual foi interrompida` (`25P02`) | Um comando anterior falhou dentro do script; execute `ROLLBACK;` e leia a **primeira** mensagem de erro. Geralmente é o `dados_iniciais.sql` sendo executado duas vezes: rode o `estrutura.sql` e depois o `dados_iniciais.sql`. |
