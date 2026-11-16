-- Gerado por gerar_dados_iniciais.py a partir de /mnt/project/R05_enade_2015_2025.csv
-- Base didática híbrida: a coluna origem_dado distingue OFICIAL_INEP de SIMULADO_DIDATICO.
-- Registros SIMULADO_DIDATICO NÃO são resultados reais do ENADE.

BEGIN;

INSERT INTO recorte (codigo, nome, foco) VALUES
  ('R05', 'História e Pedagogia', 'Analisar licenciaturas e diferenças entre modalidades.');

INSERT INTO oferta (id_recorte, curso, grau_academico, modalidade) VALUES
  ((SELECT id_recorte FROM recorte WHERE codigo = 'R05'), 'História', 'Licenciatura', 'Educação a Distância'),
  ((SELECT id_recorte FROM recorte WHERE codigo = 'R05'), 'Pedagogia', 'Licenciatura', 'Educação Presencial'),
  ((SELECT id_recorte FROM recorte WHERE codigo = 'R05'), 'Pedagogia', 'Licenciatura', 'Educação a Distância');

INSERT INTO resultado (id_oferta, ano, inscritos, participantes,
    nota_formacao_geral, nota_componente_especifico, conceito_continuo,
    conceito_faixa, ponto_forte, ponto_atencao, origem_dado, fonte_oficial) VALUES
  ((SELECT id_oferta FROM oferta WHERE curso = 'História' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2015, 18, 16, 62.14, 44.56, 3.4091, '4', 'Formação Geral com resultado superior ao Componente Específico', 'Acompanhar o desempenho no Componente Específico', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'História' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2016, 20, 17, 62.87, 47.07, 3.4002, '4', 'Formação Geral com resultado superior ao Componente Específico', 'Acompanhar o desempenho no Componente Específico', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'História' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2017, 14, 12, 61.21, 47.86, 3.4107, '4', 'Formação Geral com resultado superior ao Componente Específico', 'Acompanhar o desempenho no Componente Específico', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'História' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2018, 13, 11, 56.29, 48.86, 3.3359, '4', 'Formação Geral com resultado superior ao Componente Específico', 'Acompanhar o desempenho no Componente Específico', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'História' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2019, 14, 12, 51.86, 50.54, 3.2657, '4', 'Desempenho equilibrado entre Formação Geral e Componente Específico', 'Manter acompanhamento da participação e da evolução histórica', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'História' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2020, 11, 10, 46.79, 51.63, 3.2417, '4', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'História' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2021, 11, 10, 43.17, 53.09, 3.1601, '4', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'História' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2022, 8, 7, 42.33, 52.46, 3.0716, '4', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'História' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2023, 13, 12, 41.35, 53.61, 2.9759, '4', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'História' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2024, 13, 12, 39.37, 54.07, 2.9456, '4', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'História' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2025, 10, 9, 38.93, 54.48, 2.8612, '3', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação Presencial'), 2015, 66, 66, 54.97, 49.0, 3.0835, '4', 'Formação Geral com resultado superior ao Componente Específico', 'Acompanhar o desempenho no Componente Específico', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação Presencial'), 2016, 64, 63, 54.6, 48.39, 3.1518, '4', 'Formação Geral com resultado superior ao Componente Específico', 'Acompanhar o desempenho no Componente Específico', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação Presencial'), 2017, 58, 57, 52.3842, 49.1175, 3.1565, '4', 'Participação muito alta (98,3%) e conceito contínuo de 3,16 (faixa 4).', 'Componente Específico (49,1) abaixo da Formação Geral (52,4): acompanhar a evolução.', 'OFICIAL_INEP', 'https://download.inep.gov.br/educacao_superior/indicadores/legislacao/2018/resultados_conceito_enade_2017.csv'),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação Presencial'), 2018, 51, 50, 48.67, 48.91, 3.1881, '4', 'Desempenho equilibrado entre Formação Geral e Componente Específico', 'Manter acompanhamento da participação e da evolução histórica', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação Presencial'), 2019, 42, 41, 44.81, 49.4, 3.1957, '4', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação Presencial'), 2020, 36, 35, 40.56, 48.79, 3.2005, '4', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação Presencial'), 2021, 28, 27, 36.733, 49.17, 3.242, '4', 'Componente Específico (49,2) bem acima da Formação Geral (36,7), com participação de 96,4%.', 'Formação Geral caiu de 52,4 (2017) para 36,7 (2021): investigar a queda.', 'OFICIAL_INEP', 'https://download.inep.gov.br/educacao_superior/indicadores/resultados/2021/conceito_enade_2021.xlsx'),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação Presencial'), 2022, 19, 18, 35.15, 49.7, 3.2362, '4', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação Presencial'), 2023, 13, 12, 35.36, 48.29, 3.2171, '4', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação Presencial'), 2024, 8, 8, 35.75, 47.99, 3.0927, '4', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação Presencial'), 2025, 8, 8, 35.14, 46.72, 3.151, '4', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2015, 111, 108, 39.51, 40.59, 2.5847, '3', 'Desempenho equilibrado entre Formação Geral e Componente Específico', 'Manter acompanhamento da participação e da evolução histórica', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2016, 110, 106, 38.79, 40.9, 2.5374, '3', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2017, 120, 114, 36.85, 42.33, 2.5596, '3', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2018, 129, 123, 34.71, 42.16, 2.4287, '3', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2019, 141, 135, 33.22, 41.57, 2.4734, '3', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2020, 146, 139, 33.17, 41.78, 2.4148, '3', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2021, 159, 151, 30.903, 43.058, 2.419, '3', 'Participação elevada (95,0%) em turma grande, com 159 inscritos.', 'Formação Geral (30,9) muito abaixo do Componente Específico (43,1); conceito 2,42 (faixa 3).', 'OFICIAL_INEP', 'https://download.inep.gov.br/educacao_superior/indicadores/resultados/2021/conceito_enade_2021.xlsx'),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2022, 157, 150, 30.55, 42.52, 2.4362, '3', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2023, 150, 141, 31.92, 42.41, 2.3762, '3', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2024, 139, 129, 33.0, 42.69, 2.4214, '3', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL),
  ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND grau_academico = 'Licenciatura' AND modalidade = 'Educação a Distância'), 2025, 136, 125, 33.55, 43.0, 2.3508, '3', 'Componente Específico com resultado superior à Formação Geral', 'Acompanhar o desempenho em Formação Geral', 'SIMULADO_DIDATICO', NULL);

-- ---------------------------------------------------------------------
-- Usuário de demonstração (login: admin | senha: admin123).
-- A senha é gravada como hash bcrypt, gerado como o password_hash() do PHP.
-- No login use: password_verify($senhaDigitada, $usuario['senha']).
INSERT INTO usuario (nome, login, senha) VALUES
  ('Administrador', 'admin', '$2y$10$uH62JigRqR6bDCJTg41rAetIohGRJ7AZXRfKPrimj1XMHwHujZh/.');

-- ---------------------------------------------------------------------
-- PENDENTE: pelo menos 1 ação COM anexo. O arquivo precisa existir
-- em app/uploads/
-- INSERT INTO acao (id_oferta, titulo, descricao, situacao, responsavel, prazo,
--                   anexo_nome_original, anexo_arquivo)
-- VALUES ((SELECT id_oferta FROM oferta WHERE curso = 'Pedagogia' AND modalidade = 'Educação a Distância'),
--         'Plano de reforço em Formação Geral', 'Descrição...', 'Planejada', 'Responsável', DATE '2026-12-15',
--         'plano.pdf', '<nome_armazenado>.pdf');
-- ---------------------------------------------------------------------

COMMIT;