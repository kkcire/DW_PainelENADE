# Manual do Sistema, Rotas e Contratos de Dados

Este manual documenta o funcionamento de todas as telas, parâmetros de requisição (`GET`/`POST`/`FILES`), validações client-side e server-side, e os contratos de dados entre o PostgreSQL e o PHP.

---

## 1. Fluxo de Telas do Sistema

### 1.1. Tela de Login (`app/login.php` → `app/views/login.view.php`)
- **Acesso:** Público (usuários já autenticados são redirecionados automaticamente para `painel.php`).
- **Método `POST`:**
  - Campos recebidos: `login` (`string`) e `senha` (`string`).
  - Validação: Confere o registro na tabela `usuario` e compara a senha digitada com o hash `bcrypt` via `password_verify()`.
  - Credencial padrão de demonstração: `admin` / `admin123`.

### 1.2. Painel de Indicadores (`app/painel.php` → `app/views/painel.view.php`)
- **Acesso:** Restrito (`exigirLogin()`).
- **Parâmetros `GET` opcionais:**
  - `id_oferta` (`int`): ID da oferta selecionada (se omitido, seleciona automaticamente a primeira oferta da view `vw_oferta`).
  - `ano` (`int`): Ano da edição do ENADE (se omitido, seleciona o ano mais recente de `vw_anos`, ex.: `2025`).
- **Componentes exibidos na tela:**
  1. **Faixa de Origem do Dado:** Destaca visualmente se o registro da edição é `Oficial INEP` (`OFICIAL_INEP`, com link para a planilha oficial do Inep em `fonte_oficial`) ou `Simulado Didático` (`SIMULADO_DIDATICO`).
  2. **4 Cards de Indicadores:**
     - *Conceito ENADE:* Faixa (`1` a `5` ou `SC`), Conceito Contínuo (`0,00` a `5,00`) e badge de variação em relação à edição anterior (`variacao_faixa`).
     - *Participação de Concluintes:* Percentual (`participacao_pct`) e relação `participantes / inscritos`.
     - *Notas dos Componentes:* Nota de Formação Geral (`nota_formacao_geral`) e Nota de Componente Específico (`nota_componente_especifico`).
     - *Cobertura do Recorte:* Quantidade de ofertas, total de resultados (oficiais vs. simulados) e intervalo de anos (`2015–2025`).
  3. **Barras de Desempenho Detalhado:** Barras horizontais proporcionais para FG, CE, Conceito Contínuo e Taxa de Participação.
  4. **Síntese e Leitura Pedagógica:** Exibe `ponto_forte`, `ponto_atencao` e a leitura calculada de `diferenca_fg_ce`.
  5. **Série Histórica (2015–2025):** Gráfico vertical de colunas em CSS + tabela comparativa clicável de todas as edições da oferta.
  6. **Resumo de Ações de Acompanhamento da Oferta:** Contagem por situação e tabela com as últimas ações cadastradas para a oferta ativa.

---

## 2. Módulo CRUD de Ações de Acompanhamento (`app/acoes/`)

### 2.1. Listagem e Filtros (`app/acoes/listar.php` → `app/views/acoes/lista.view.php`)
- **Parâmetros `GET` opcionais:**
  - `id_oferta` (`int`): Filtra ações de uma oferta específica.
  - `situacao` (`string`): Filtra por `'Planejada'`, `'Em andamento'` ou `'Concluída'`.
- **Exibição:** Mini-cards com totalizadores por situação + tabela com dados da ação, link direto para o anexo (se houver) e botões `Ver`, `Editar` e `Excluir`.

### 2.2. Cadastro de Nova Ação (`app/acoes/criar.php` → `app/views/acoes/form.view.php`)
- **Parâmetro `GET` opcional:** `id_oferta` (pré-seleciona a oferta quando o usuário clica em "+ Nova Ação para esta Oferta" dentro do painel).
- **Campos `POST` obrigatórios:**
  - `id_oferta` (`int > 0`): Oferta vinculada.
  - `titulo` (`string`, máx. 150 caracteres): Título resumido da ação.
  - `responsavel` (`string`, máx. 100 caracteres): Nome ou setor responsável.
  - `situacao` (`'Planejada' | 'Em andamento' | 'Concluída'`).
  - `prazo` (`YYYY-MM-DD`): Data limite prevista.
  - `descricao` (`string`): Detalhamento completo da ação.
- **Campo `FILES` opcional (`anexo`):**
  - Aceita apenas `.pdf`, `.png`, `.jpg` ou `.jpeg` de até **2 MB**.

### 2.3. Edição de Ação (`app/acoes/editar.php` → `app/views/acoes/form.view.php`)
- **Parâmetro `GET`:** `id` (`int` — ID da ação a editar).
- **Comportamento do Anexo na Edição:**
  1. **Sem enviar novo arquivo e sem marcar "Remover anexo":** mantém o anexo atual intacto.
  2. **Enviando novo arquivo em `$_FILES['anexo']`:** valida o novo arquivo, apaga o arquivo antigo de `app/uploads/` e grava o novo.
  3. **Marcando o checkbox `remover_anexo = 1`:** apaga o arquivo antigo de `app/uploads/` e limpa `anexo_nome_original` e `anexo_arquivo` (`NULL`) no banco.

### 2.4. Visualização Detalhada (`app/acoes/visualizar.php` → `app/views/acoes/visualizar.view.php`)
- **Parâmetro `GET`:** `id` (`int`).
- Exibe todos os metadados da ação, texto completo com quebras de linha preservadas (`nl2br(e($acao['descricao']))`), botão de download do anexo e *preview* integrado caso a evidência seja uma imagem (`PNG` ou `JPG`).

### 2.5. Exclusão (`app/acoes/excluir.php`)
- **Parâmetro `POST` (ou `GET`):** `id` (`int`).
- Antes de remover o registro no PostgreSQL (`AcaoRepository::excluir`), verifica se a ação possui `anexo_arquivo` e remove o arquivo físico correspondente em `app/uploads/` (`UploadEvidenciaService::removerArquivo`).

---

## 3. Validações no Front-end (`app/assets/js/validacao.js`)

O script `validacao.js` atua em conjunto com a validação PHP do servidor:
1. **Inspeção imediata do `<input type="file" id="anexo">`:** Ao selecionar um arquivo, verifica a extensão (`.pdf`, `.png`, `.jpg`, `.jpeg`) e o tamanho (`file.size <= 2 * 1024 * 1024`). Caso inválido, limpa o campo e alerta o usuário antes do envio.
2. **Verificação de campos obrigatórios no `#form-acao`:** Impede submissão acidental com campos em branco.
3. **Confirmação de exclusão (`.js-confirmar-exclusao`):** Exibe um diálogo `window.confirm` com o título da ação antes de disparar o `POST` para `excluir.php`.
