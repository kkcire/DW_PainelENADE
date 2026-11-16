-- =====================================================================
-- Painel ENADE - Grupo 06 - Recorte R05 (História e Pedagogia)
-- estrutura.sql  (PostgreSQL)
-- Pode ser executado várias vezes: recria tudo do zero.
-- Uso:  psql -U postgres -d dw_projeto_enade -f estrutura.sql
-- =====================================================================

DROP VIEW  IF EXISTS vw_acao       CASCADE;
DROP VIEW  IF EXISTS vw_cobertura  CASCADE;
DROP VIEW  IF EXISTS vw_anos       CASCADE;
DROP VIEW  IF EXISTS vw_resultado  CASCADE;
DROP VIEW  IF EXISTS vw_oferta     CASCADE;
DROP TABLE IF EXISTS acao          CASCADE;
DROP TABLE IF EXISTS resultado     CASCADE;
DROP TABLE IF EXISTS oferta        CASCADE;
DROP TABLE IF EXISTS recorte       CASCADE;
DROP TABLE IF EXISTS usuario       CASCADE;

-- ---------------------------------------------------------------------
-- usuario: login simples (um único tipo de usuário, sem perfis).
-- senha é VARCHAR(255) para aceitar qualquer formato (texto, hash...).
-- O formato final segue o exemplo da aula (ver dados_iniciais.sql).
-- ---------------------------------------------------------------------
CREATE TABLE usuario (
    id_usuario  SERIAL       PRIMARY KEY,
    nome        VARCHAR(100) NOT NULL,
    login       VARCHAR(50)  NOT NULL UNIQUE,
    senha       VARCHAR(255) NOT NULL
);

-- ---------------------------------------------------------------------
-- recorte: entidade raiz. Agrupa as ofertas atribuídas ao grupo (R05).
-- Hoje só há 1 linha, mas ela relaciona todo o resto do modelo.
-- ---------------------------------------------------------------------
CREATE TABLE recorte (
    id_recorte  SERIAL       PRIMARY KEY,
    codigo      VARCHAR(10)  NOT NULL UNIQUE,
    nome        VARCHAR(100) NOT NULL,
    foco        TEXT
);

-- ---------------------------------------------------------------------
-- oferta: curso + grau acadêmico + modalidade (identifica a oferta).
-- UNIQUE na combinação impede cadastrar a mesma oferta duas vezes.
-- RESTRICT: não é possível apagar um recorte que ainda tem ofertas.
-- ---------------------------------------------------------------------
CREATE TABLE oferta (
    id_oferta       SERIAL       PRIMARY KEY,
    id_recorte      INTEGER      NOT NULL
                    REFERENCES recorte (id_recorte) ON DELETE RESTRICT,
    curso           VARCHAR(100) NOT NULL,
    grau_academico  VARCHAR(30)  NOT NULL,
    modalidade      VARCHAR(30)  NOT NULL,
    CONSTRAINT uq_oferta UNIQUE (curso, grau_academico, modalidade)
);

-- ---------------------------------------------------------------------
-- resultado: um registro por oferta e por edição (ano).
--  * participação NÃO é gravada: é calculada em vw_resultado
--    (participantes / inscritos * 100) para não gerar inconsistência.
--  * conceito_faixa é texto porque o Inep admite 'SC' (sem conceito).
--  * origem_dado distingue dado OFICIAL_INEP de SIMULADO_DIDATICO
--    e nunca pode ficar vazio (exigência da base didática).
--  * fonte_oficial só existe (e é obrigatória) nos registros oficiais.
-- ---------------------------------------------------------------------
CREATE TABLE resultado (
    id_resultado                SERIAL        PRIMARY KEY,
    id_oferta                   INTEGER       NOT NULL
                                REFERENCES oferta (id_oferta) ON DELETE RESTRICT,
    ano                         SMALLINT      NOT NULL CHECK (ano BETWEEN 2000 AND 2100),
    inscritos                   INTEGER       NOT NULL CHECK (inscritos > 0),
    participantes               INTEGER       NOT NULL CHECK (participantes >= 0),
    nota_formacao_geral         NUMERIC(7,4)  NOT NULL CHECK (nota_formacao_geral BETWEEN 0 AND 100),
    nota_componente_especifico  NUMERIC(7,4)  NOT NULL CHECK (nota_componente_especifico BETWEEN 0 AND 100),
    conceito_continuo           NUMERIC(6,4)  NOT NULL CHECK (conceito_continuo BETWEEN 0 AND 5),
    conceito_faixa              VARCHAR(2)    NOT NULL
                                CHECK (conceito_faixa IN ('1','2','3','4','5','SC')),
    ponto_forte                 TEXT          NOT NULL,
    ponto_atencao               TEXT          NOT NULL,
    origem_dado                 VARCHAR(20)   NOT NULL
                                CHECK (origem_dado IN ('OFICIAL_INEP','SIMULADO_DIDATICO')),
    fonte_oficial               TEXT,
    CONSTRAINT uq_resultado_oferta_ano UNIQUE (id_oferta, ano),
    CONSTRAINT ck_participantes_ate_inscritos CHECK (participantes <= inscritos),
    CONSTRAINT ck_fonte_so_no_oficial
        CHECK ((origem_dado = 'OFICIAL_INEP') = (fonte_oficial IS NOT NULL))
);

-- ---------------------------------------------------------------------
-- acao: ações de acompanhamento (CRUD) vinculadas a uma oferta.
-- Os dois campos de anexo são opcionais, mas andam juntos:
-- ou a ação tem anexo (nome original + nome armazenado) ou não tem.
-- RESTRICT: o CRUD apaga ações, nunca ofertas.
-- ---------------------------------------------------------------------
CREATE TABLE acao (
    id_acao              SERIAL        PRIMARY KEY,
    id_oferta            INTEGER       NOT NULL
                         REFERENCES oferta (id_oferta) ON DELETE RESTRICT,
    titulo               VARCHAR(150)  NOT NULL,
    descricao            TEXT          NOT NULL,
    situacao             VARCHAR(20)   NOT NULL
                         CHECK (situacao IN ('Planejada','Em andamento','Concluída')),
    responsavel          VARCHAR(100)  NOT NULL,
    prazo                DATE          NOT NULL,
    anexo_nome_original  VARCHAR(255),
    anexo_arquivo        VARCHAR(255),
    CONSTRAINT ck_anexo_completo
        CHECK ((anexo_nome_original IS NULL) = (anexo_arquivo IS NULL))
);

CREATE INDEX ix_acao_oferta ON acao (id_oferta);

-- =====================================================================
-- VIEWS consumidas pelo back-end
-- Nenhuma view tem ORDER BY: o PHP ordena na consulta.
-- =====================================================================

-- Filtro de oferta: id + rótulo "Curso · Grau · Modalidade" (como no protótipo).
CREATE VIEW vw_oferta AS
SELECT o.id_oferta,
       o.curso,
       o.grau_academico,
       o.modalidade,
       o.curso || ' · ' || o.grau_academico || ' · ' || o.modalidade AS rotulo,
       r.codigo AS recorte
FROM oferta o
JOIN recorte r ON r.id_recorte = o.id_recorte;

-- Painel e histórico: resultado + campos calculados.
--  * participacao_pct      = participantes / inscritos * 100
--  * diferenca_fg_ce       = Formação Geral - Componente Específico
--                            (positivo: FG acima do CE; base da "Leitura do grupo")
--  * *_anterior            = edição imediatamente anterior da MESMA oferta
--                            (NULL na primeira edição)
--  * variacao_faixa        = faixa atual - faixa anterior (badge "+1 faixa");
--                            NULL se não há anterior ou se alguma faixa é 'SC'
-- Os cálculos "anteriores" usam LAG por oferta, então continuam corretos
-- quando o PHP filtra por id_oferta e ano.
-- Sem linha para a oferta/ano => o PHP exibe "Sem dados para esta oferta/edição".
CREATE VIEW vw_resultado AS
WITH base AS (
    SELECT res.*,
           LAG(res.ano)               OVER w AS ano_anterior,
           LAG(res.conceito_continuo) OVER w AS conceito_continuo_anterior,
           LAG(res.conceito_faixa)    OVER w AS conceito_faixa_anterior
    FROM resultado res
    WINDOW w AS (PARTITION BY res.id_oferta ORDER BY res.ano)
)
SELECT b.id_resultado,
       b.id_oferta,
       o.curso,
       o.grau_academico,
       o.modalidade,
       b.ano,
       b.inscritos,
       b.participantes,
       ROUND(b.participantes * 100.0 / b.inscritos, 2)           AS participacao_pct,
       b.nota_formacao_geral,
       b.nota_componente_especifico,
       ROUND(b.nota_formacao_geral - b.nota_componente_especifico, 2) AS diferenca_fg_ce,
       b.conceito_continuo,
       b.conceito_faixa,
       b.ano_anterior,
       b.conceito_continuo_anterior,
       b.conceito_faixa_anterior,
       CASE WHEN b.conceito_faixa <> 'SC' AND b.conceito_faixa_anterior <> 'SC'
            THEN b.conceito_faixa::INT - b.conceito_faixa_anterior::INT
       END                                                       AS variacao_faixa,
       b.ponto_forte,
       b.ponto_atencao,
       b.origem_dado,
       b.fonte_oficial
FROM base b
JOIN oferta o ON o.id_oferta = b.id_oferta;

-- Filtro de edição: anos existentes na base.
CREATE VIEW vw_anos AS
SELECT DISTINCT ano FROM resultado;

-- Card "Cobertura do recorte": uma linha por recorte.
--  * ofertas_serie_limitada   = ofertas com no máximo 1 edição na base
--  * ofertas_sem_oficial      = ofertas sem nenhum resultado OFICIAL_INEP
--                               (na base didática, História EaD é o caso do R05)
CREATE VIEW vw_cobertura AS
SELECT r.id_recorte,
       r.codigo AS recorte,
       r.nome,
       COUNT(DISTINCT o.id_oferta)                                              AS ofertas,
       COUNT(res.id_resultado)                                                  AS resultados,
       COUNT(res.id_resultado) FILTER (WHERE res.origem_dado = 'OFICIAL_INEP')      AS resultados_oficiais,
       COUNT(res.id_resultado) FILTER (WHERE res.origem_dado = 'SIMULADO_DIDATICO') AS resultados_simulados,
       MIN(res.ano)                                                             AS primeiro_ano,
       MAX(res.ano)                                                             AS ultimo_ano,
       (SELECT COUNT(*) FROM oferta o2
         WHERE o2.id_recorte = r.id_recorte
           AND (SELECT COUNT(*) FROM resultado x WHERE x.id_oferta = o2.id_oferta) <= 1)
                                                                                AS ofertas_serie_limitada,
       (SELECT COUNT(*) FROM oferta o3
         WHERE o3.id_recorte = r.id_recorte
           AND NOT EXISTS (SELECT 1 FROM resultado y
                            WHERE y.id_oferta = o3.id_oferta
                              AND y.origem_dado = 'OFICIAL_INEP'))
                                                                                AS ofertas_sem_oficial
FROM recorte r
LEFT JOIN oferta o     ON o.id_recorte = r.id_recorte
LEFT JOIN resultado res ON res.id_oferta = o.id_oferta
GROUP BY r.id_recorte, r.codigo, r.nome;

-- Tabela "Ações de acompanhamento" e página de anexos:
-- ação + rótulo da oferta + indicador de anexo.
CREATE VIEW vw_acao AS
SELECT a.id_acao,
       a.id_oferta,
       o.curso || ' · ' || o.grau_academico || ' · ' || o.modalidade AS oferta_rotulo,
       a.titulo,
       a.descricao,
       a.situacao,
       a.responsavel,
       a.prazo,
       a.anexo_nome_original,
       a.anexo_arquivo,
       (a.anexo_arquivo IS NOT NULL) AS tem_anexo
FROM acao a
JOIN oferta o ON o.id_oferta = a.id_oferta;