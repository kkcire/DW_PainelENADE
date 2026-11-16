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
2. **Colocar o projeto em `htdocs`:** copiar a pasta `DW_Grupo06_PainelENADE` para `C:\xampp\htdocs\`. No *XAMPP Control Panel*, iniciar o **Apache**. A aplicação fica em `http://localhost/DW_Grupo06_PainelENADE/app/`.
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