<?php
/**
 * =====================================================================
 * IndicadorEnadeRepository.php - Repositório de Indicadores ENADE
 * =====================================================================
 *
 * Camada Models (Pattern Repository):
 * Isola todas as consultas SQL (SELECT) nas views analíticas do banco:
 * - vw_oferta
 * - vw_anos
 * - vw_cobertura
 * - vw_resultado
 *
 * IMPORTANTE: Conforme definido na modelagem do banco (estrutura.sql),
 * nenhuma view possui cláusula ORDER BY fixa; a ordenação é definida
 * explicitamente em cada método deste repositório.
 */

class IndicadorEnadeRepository
{
    /**
     * Recebe a instância ativa do PDO por injeção de dependência.
     *
     * @param PDO $pdo Conexão PDO obtida via conectar().
     */
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Lista todas as ofertas vinculadas ao recorte do grupo (vw_oferta),
     * ordenadas por nome do curso e modalidade.
     *
     * Colunas retornadas:
     * - id_oferta (int)
     * - curso (string)
     * - grau_academico (string)
     * - modalidade (string)
     * - rotulo (string: "Curso · Grau · Modalidade")
     * - recorte (string: ex. "R05")
     *
     * @return array<int, array<string, mixed>> Lista de ofertas.
     */
    public function listarOfertas(): array
    {
        $sql = 'SELECT id_oferta, curso, grau_academico, modalidade, rotulo, recorte
                FROM vw_oferta
                ORDER BY curso ASC, modalidade ASC';
        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Lista todos os anos de edição existentes na base (vw_anos),
     * ordenados do ano mais recente para o mais antigo (ex.: 2025 -> 2015).
     *
     * @return array<int, int|string> Lista simples de anos.
     */
    public function listarAnos(): array
    {
        $sql = 'SELECT ano FROM vw_anos ORDER BY ano DESC';
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Busca a linha consolidada de cobertura do recorte (vw_cobertura).
     *
     * Colunas retornadas:
     * - id_recorte, recorte, nome
     * - ofertas (total de ofertas do recorte)
     * - resultados (total geral de edições registradas)
     * - resultados_oficiais (contagem onde origem_dado = 'OFICIAL_INEP')
     * - resultados_simulados (contagem onde origem_dado = 'SIMULADO_DIDATICO')
     * - primeiro_ano, ultimo_ano
     * - ofertas_serie_limitada, ofertas_sem_oficial
     *
     * @return array<string, mixed>|null Dados de cobertura ou null se vazio.
     */
    public function buscarCobertura(): ?array
    {
        $sql = 'SELECT id_recorte, recorte, nome, ofertas, resultados,
                       resultados_oficiais, resultados_simulados,
                       primeiro_ano, ultimo_ano,
                       ofertas_serie_limitada, ofertas_sem_oficial
                FROM vw_cobertura
                LIMIT 1';
        $linha = $this->pdo->query($sql)->fetch();
        return $linha ?: null;
    }

    /**
     * Busca o resultado consolidado de uma oferta em uma edição (ano) específica
     * a partir da view `vw_resultado`.
     *
     * A view já entrega calculados pelo PostgreSQL:
     * - participacao_pct (participantes / inscritos * 100)
     * - diferenca_fg_ce (nota_formacao_geral - nota_componente_especifico)
     * - ano_anterior, conceito_continuo_anterior, conceito_faixa_anterior (via LAG)
     * - variacao_faixa (diferença inteira entre a faixa atual e a faixa anterior)
     *
     * @param int $idOferta ID da oferta selecionada.
     * @param int $ano      Ano da edição selecionada (ex.: 2021).
     * @return array<string, mixed>|null Registro completo da edição ou null ("Sem dados").
     */
    public function buscarResultado(int $idOferta, int $ano): ?array
    {
        $sql = 'SELECT *
                FROM vw_resultado
                WHERE id_oferta = :id_oferta AND ano = :ano
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_oferta' => $idOferta,
            ':ano'       => $ano,
        ]);
        $linha = $stmt->fetch();
        return $linha ?: null;
    }

    /**
     * Lista toda a série histórica (2015 a 2025) de uma oferta específica,
     * ordenada cronologicamente (ano ASC) para alimentar o gráfico de evolução
     * e a tabela histórica do painel.
     *
     * @param int $idOferta ID da oferta selecionada.
     * @return array<int, array<string, mixed>> Lista de resultados anuais da oferta.
     */
    public function listarHistoricoPorOferta(int $idOferta): array
    {
        $sql = 'SELECT *
                FROM vw_resultado
                WHERE id_oferta = :id_oferta
                ORDER BY ano ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id_oferta' => $idOferta]);
        return $stmt->fetchAll();
    }
}
