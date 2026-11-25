<?php
/**
 * IndicadorEnadeRepository.php - Camada Models (Repository Pattern)
 *
 * O que é o Repository Pattern (Padrão Repositório)?
 * --------------------------------------------------
 * É um padrão de projeto que centraliza TODAS as consultas SQL relacionadas a um
 * domínio (aqui, os indicadores do ENADE) em uma única classe.
 *
 * Por que fazer isso?
 * - Os controllers (ex.: painel.php) e os services (ex.: PainelEstatisticasService)
 *   não precisam saber SQL nem o nome exato das colunas/views do banco.
 * - Todas as consultas usam PDO com prepared statements (:parametro), protegendo
 *   automaticamente contra SQL Injection.
 * - Como as views do banco NÃO possuem ORDER BY (regra definida em estrutura.sql),
 *   a ordenação é sempre explicitada aqui nas consultas PHP.
 *
 * Views consultadas (somente leitura):
 * - vw_oferta     -> lista de ofertas (curso · grau · modalidade)
 * - vw_anos       -> anos/edições existentes na base
 * - vw_resultado  -> indicadores detalhados + campos calculados (LAG, participação, etc.)
 * - vw_cobertura  -> resumo geral de cobertura do recorte R05
 */

class IndicadorEnadeRepository
{
    /**
     * Conexão PDO com o PostgreSQL.
     * Recebemos via construtor (Injeção de Dependência) em vez de criar aqui dentro,
     * deixando a classe mais organizada e fácil de testar.
     */
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Lista todas as ofertas cadastradas no recorte (ex.: História EaD, Pedagogia Presencial, Pedagogia EaD).
     * Usada para preencher o filtro de ofertas no painel e o <select> no cadastro de ações.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarOfertas(): array
    {
        $sql = 'SELECT id_oferta, curso, grau_academico, modalidade, rotulo, recorte
                FROM vw_oferta
                ORDER BY curso ASC, modalidade ASC';

        // Como não há parâmetros vindos do usuário nessa query, query() direto é seguro.
        $stmt = $this->pdo->query($sql);
        $linhas = $stmt->fetchAll();

        // Garante que id_oferta seja sempre inteiro no JSON retornado ao React
        return array_map(function (array $linha): array {
            $linha['id_oferta'] = (int) $linha['id_oferta'];
            return $linha;
        }, $linhas);
    }

    /**
     * Busca uma oferta específica pelo seu ID.
     * Retorna null caso o ID informado não exista no banco.
     */
    public function buscarOfertaPorId(int $idOferta): ?array
    {
        $sql = 'SELECT id_oferta, curso, grau_academico, modalidade, rotulo, recorte
                FROM vw_oferta
                WHERE id_oferta = :id_oferta
                LIMIT 1';

        // prepare() + execute() separa o comando SQL dos dados, impedindo SQL Injection
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id_oferta' => $idOferta]);
        $linha = $stmt->fetch();

        if (!$linha) {
            return null;
        }

        $linha['id_oferta'] = (int) $linha['id_oferta'];
        return $linha;
    }

    /**
     * Lista todos os anos (edições do ENADE) presentes na base, do mais recente para o mais antigo.
     * Exemplo de retorno: [2025, 2024, 2023, ..., 2015]
     *
     * @return array<int, int>
     */
    public function listarAnos(): array
    {
        $sql = 'SELECT ano FROM vw_anos ORDER BY ano DESC';
        $stmt = $this->pdo->query($sql);

        // PDO::FETCH_COLUMN traz apenas a primeira coluna (ano) em um array simples
        $anos = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_map('intval', $anos);
    }

    /**
     * Busca os dados do card "Cobertura do recorte" na view vw_cobertura.
     * Como o recorte do grupo é único (R05), retorna a primeira linha encontrada ou null.
     */
    public function buscarCobertura(): ?array
    {
        $sql = 'SELECT id_recorte,
                       recorte,
                       nome,
                       ofertas,
                       resultados,
                       resultados_oficiais,
                       resultados_simulados,
                       primeiro_ano,
                       ultimo_ano,
                       ofertas_serie_limitada,
                       ofertas_sem_oficial
                FROM vw_cobertura
                ORDER BY id_recorte ASC
                LIMIT 1';

        $stmt = $this->pdo->query($sql);
        $linha = $stmt->fetch();

        if (!$linha) {
            return null;
        }

        // Converte os contadores do PostgreSQL para inteiros puros (evita números como string no JSON)
        return [
            'id_recorte'             => (int) $linha['id_recorte'],
            'recorte'                => $linha['recorte'],
            'nome'                   => $linha['nome'],
            'ofertas'                => (int) $linha['ofertas'],
            'resultados'             => (int) $linha['resultados'],
            'resultados_oficiais'    => (int) $linha['resultados_oficiais'],
            'resultados_simulados'   => (int) $linha['resultados_simulados'],
            'primeiro_ano'           => $linha['primeiro_ano'] !== null ? (int) $linha['primeiro_ano'] : null,
            'ultimo_ano'             => $linha['ultimo_ano'] !== null ? (int) $linha['ultimo_ano'] : null,
            'ofertas_serie_limitada' => (int) $linha['ofertas_serie_limitada'],
            'ofertas_sem_oficial'    => (int) $linha['ofertas_sem_oficial'],
        ];
    }

    /**
     * Busca o resultado de UMA oferta em UM ano específico na view vw_resultado.
     *
     * Por que consultar vw_resultado e não a tabela resultado?
     * Porque a view já entrega prontos:
     * - participacao_pct (calculada dinamicamente: participantes / inscritos * 100)
     * - diferenca_fg_ce (nota_formacao_geral - nota_componente_especifico)
     * - dados da edição anterior (via window function LAG) e variacao_faixa
     *
     * Se não houver registro para essa combinação, retorna null (estado "sem dados").
     */
    public function buscarResultadoPorOfertaEAno(int $idOferta, int $ano): ?array
    {
        $sql = 'SELECT id_resultado,
                       id_oferta,
                       curso,
                       grau_academico,
                       modalidade,
                       ano,
                       inscritos,
                       participantes,
                       participacao_pct,
                       nota_formacao_geral,
                       nota_componente_especifico,
                       diferenca_fg_ce,
                       conceito_continuo,
                       conceito_faixa,
                       ano_anterior,
                       conceito_continuo_anterior,
                       conceito_faixa_anterior,
                       variacao_faixa,
                       ponto_forte,
                       ponto_atencao,
                       origem_dado,
                       fonte_oficial
                FROM vw_resultado
                WHERE id_oferta = :id_oferta
                  AND ano = :ano
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_oferta' => $idOferta,
            ':ano'       => $ano,
        ]);

        $linha = $stmt->fetch();
        if (!$linha) {
            return null;
        }

        return $this->normalizarResultado($linha);
    }

    /**
     * Busca toda a série histórica (ex.: 2015 a 2025) de uma oferta específica,
     * ordenada cronologicamente (do ano mais antigo para o mais recente).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarHistoricoPorOferta(int $idOferta): array
    {
        $sql = 'SELECT id_resultado,
                       id_oferta,
                       curso,
                       grau_academico,
                       modalidade,
                       ano,
                       inscritos,
                       participantes,
                       participacao_pct,
                       nota_formacao_geral,
                       nota_componente_especifico,
                       diferenca_fg_ce,
                       conceito_continuo,
                       conceito_faixa,
                       ano_anterior,
                       conceito_continuo_anterior,
                       conceito_faixa_anterior,
                       variacao_faixa,
                       ponto_forte,
                       ponto_atencao,
                       origem_dado,
                       fonte_oficial
                FROM vw_resultado
                WHERE id_oferta = :id_oferta
                ORDER BY ano ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id_oferta' => $idOferta]);

        return array_map([$this, 'normalizarResultado'], $stmt->fetchAll());
    }

    /**
     * Busca os resultados de TODAS as ofertas em um mesmo ano.
     * Útil caso o painel queira comparar História EaD x Pedagogia Presencial x Pedagogia EaD naquela edição.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarResultadosPorAno(int $ano): array
    {
        $sql = 'SELECT id_resultado,
                       id_oferta,
                       curso,
                       grau_academico,
                       modalidade,
                       ano,
                       inscritos,
                       participantes,
                       participacao_pct,
                       nota_formacao_geral,
                       nota_componente_especifico,
                       diferenca_fg_ce,
                       conceito_continuo,
                       conceito_faixa,
                       ano_anterior,
                       conceito_continuo_anterior,
                       conceito_faixa_anterior,
                       variacao_faixa,
                       ponto_forte,
                       ponto_atencao,
                       origem_dado,
                       fonte_oficial
                FROM vw_resultado
                WHERE ano = :ano
                ORDER BY curso ASC, modalidade ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':ano' => $ano]);

        return array_map([$this, 'normalizarResultado'], $stmt->fetchAll());
    }

    /**
     * Converte os tipos vindos do PostgreSQL/PDO para tipos nativos do PHP (int/float/string/null).
     *
     * Por que isso é importante?
     * O driver pdo_pgsql devolve colunas NUMERIC (como notas e médias) como strings (ex.: "52.3842").
     * Convertendo para float/int aqui, o json_encode() envia números de verdade para o React,
     * permitindo fazer contas, gráficos e formatações sem precisar de parseFloat() em todo lugar.
     */
    private function normalizarResultado(array $linha): array
    {
        return [
            'id_resultado'               => (int) $linha['id_resultado'],
            'id_oferta'                  => (int) $linha['id_oferta'],
            'curso'                      => $linha['curso'],
            'grau_academico'             => $linha['grau_academico'],
            'modalidade'                 => $linha['modalidade'],
            'ano'                        => (int) $linha['ano'],
            'inscritos'                  => (int) $linha['inscritos'],
            'participantes'              => (int) $linha['participantes'],
            'participacao_pct'           => (float) $linha['participacao_pct'],
            'nota_formacao_geral'        => (float) $linha['nota_formacao_geral'],
            'nota_componente_especifico' => (float) $linha['nota_componente_especifico'],
            'diferenca_fg_ce'            => (float) $linha['diferenca_fg_ce'],
            'conceito_continuo'          => (float) $linha['conceito_continuo'],
            // conceito_faixa permanece string porque o Inep admite '1'..'5' ou 'SC' (Sem Conceito)
            'conceito_faixa'             => (string) $linha['conceito_faixa'],
            'ano_anterior'               => $linha['ano_anterior'] !== null ? (int) $linha['ano_anterior'] : null,
            'conceito_continuo_anterior' => $linha['conceito_continuo_anterior'] !== null ? (float) $linha['conceito_continuo_anterior'] : null,
            'conceito_faixa_anterior'    => $linha['conceito_faixa_anterior'] !== null ? (string) $linha['conceito_faixa_anterior'] : null,
            'variacao_faixa'             => $linha['variacao_faixa'] !== null ? (int) $linha['variacao_faixa'] : null,
            'ponto_forte'                => $linha['ponto_forte'],
            'ponto_atencao'              => $linha['ponto_atencao'],
            'origem_dado'                => $linha['origem_dado'],
            'fonte_oficial'              => $linha['fonte_oficial'],
        ];
    }
}
