<?php
/**
 * =====================================================================
 * PainelEstatisticasService.php - Serviço de Estatísticas do Painel (SRP)
 * =====================================================================
 *
 * Camada Services (Single Responsibility Principle):
 * Orquestra a busca nos repositórios (`IndicadorEnadeRepository` e `AcaoRepository`)
 * e transforma os dados brutos das views em estruturas prontas para exibição
 * em `views/painel.view.php`:
 *
 * 1. Resolução segura dos filtros ativos (`id_oferta` e `ano`).
 * 2. Formatação dos 4 Cards de Indicadores (Conceito, Participação, Notas, Cobertura).
 * 3. Cálculo das larguras percentuais (0 a 100%) das barras de desempenho.
 * 4. Formatação da Série Histórica (2015–2025) para o gráfico de colunas e tabela.
 * 5. Elaboração da Síntese Interpretativa com leitura automática de `diferenca_fg_ce`.
 */

class PainelEstatisticasService
{
    /**
     * @param IndicadorEnadeRepository $indicadorRepo Repositório de indicadores ENADE.
     * @param AcaoRepository           $acaoRepo      Repositório de ações de acompanhamento.
     */
    public function __construct(
        private IndicadorEnadeRepository $indicadorRepo,
        private AcaoRepository $acaoRepo
    ) {
    }

    /**
     * Consolida todos os dados necessários para renderizar o painel principal.
     *
     * @param int|null $idOfertaFiltro ID da oferta vinda de `$_GET['id_oferta']` (ou null).
     * @param int|null $anoFiltro      Ano da edição vindo de `$_GET['ano']` (ou null).
     * @return array<string, mixed>    Pacote completo `$dadosPainel` consumido pela View.
     */
    public function montarDadosPainel(?int $idOfertaFiltro, ?int $anoFiltro): array
    {
        $ofertas   = $this->indicadorRepo->listarOfertas();
        $anos      = $this->indicadorRepo->listarAnos();
        $cobertura = $this->indicadorRepo->buscarCobertura();

        // Define oferta selecionada (usa a primeira disponível se não informada ou inválida)
        $idsOfertas    = array_map(fn($o) => (int) $o['id_oferta'], $ofertas);
        $idOfertaAtiva = ($idOfertaFiltro !== null && in_array($idOfertaFiltro, $idsOfertas, true))
            ? $idOfertaFiltro
            : ($idsOfertas[0] ?? 0);

        $ofertaSelecionada = null;
        foreach ($ofertas as $oferta) {
            if ((int) $oferta['id_oferta'] === $idOfertaAtiva) {
                $ofertaSelecionada = $oferta;
                break;
            }
        }

        // Define edição (ano) selecionada (usa o ano mais recente se não informado ou inválido)
        $anosInt  = array_map('intval', $anos);
        $anoAtivo = ($anoFiltro !== null && in_array($anoFiltro, $anosInt, true))
            ? $anoFiltro
            : ($anosInt[0] ?? 2025);

        $resultado = ($idOfertaAtiva > 0)
            ? $this->indicadorRepo->buscarResultado($idOfertaAtiva, $anoAtivo)
            : null;

        $historicoBruto = ($idOfertaAtiva > 0)
            ? $this->indicadorRepo->listarHistoricoPorOferta($idOfertaAtiva)
            : [];

        $cards     = $this->calcularCards($resultado, $cobertura);
        $barras    = $this->calcularBarrasDesempenho($resultado);
        $historico = $this->formatarSerieHistorica($historicoBruto, $anoAtivo);
        $sintese   = $this->gerarSinteseInterpretativa($resultado);

        // Ações de acompanhamento vinculadas à oferta selecionada
        $acoesOferta       = ($idOfertaAtiva > 0) ? $this->acaoRepo->listar($idOfertaAtiva) : [];
        $resumoAcoesOferta = ($idOfertaAtiva > 0) ? $this->acaoRepo->contarPorSituacao($idOfertaAtiva) : [];

        return [
            'ofertas'            => $ofertas,
            'anos'               => $anosInt,
            'id_oferta_ativa'    => $idOfertaAtiva,
            'ano_ativo'          => $anoAtivo,
            'oferta_selecionada' => $ofertaSelecionada,
            'cobertura'          => $cobertura,
            'resultado'          => $resultado,
            'cards'              => $cards,
            'barras'             => $barras,
            'historico'          => $historico,
            'sintese'            => $sintese,
            'acoes_oferta'       => array_slice($acoesOferta, 0, 5),
            'resumo_acoes'       => $resumoAcoesOferta,
        ];
    }

    /**
     * Formata os valores, percentuais e badges dos 4 cards principais do painel.
     *
     * @param array<string, mixed>|null $resultado Linha da view `vw_resultado` ou null.
     * @param array<string, mixed>|null $cobertura Linha da view `vw_cobertura` ou null.
     * @return array<string, mixed>
     */
    private function calcularCards(?array $resultado, ?array $cobertura): array
    {
        if ($resultado === null) {
            return ['tem_dados' => false];
        }

        $variacao = $resultado['variacao_faixa'];
        if ($variacao === null) {
            $rotuloVariacao = 'Sem edição anterior comparável';
            $classeVariacao = 'neutro';
        } elseif ((int) $variacao > 0) {
            $rotuloVariacao = '+' . (int) $variacao . ' faixa vs. ' . $resultado['ano_anterior'];
            $classeVariacao = 'positivo';
        } elseif ((int) $variacao < 0) {
            $rotuloVariacao = (int) $variacao . ' faixa vs. ' . $resultado['ano_anterior'];
            $classeVariacao = 'negativo';
        } else {
            $rotuloVariacao = 'Faixa mantida vs. ' . $resultado['ano_anterior'];
            $classeVariacao = 'neutro';
        }

        $ehOficial = ($resultado['origem_dado'] === 'OFICIAL_INEP');

        return [
            'tem_dados'             => true,
            'conceito_faixa'        => $resultado['conceito_faixa'],
            'conceito_continuo_fmt' => number_format((float) $resultado['conceito_continuo'], 2, ',', '.'),
            'variacao_rotulo'       => $rotuloVariacao,
            'variacao_classe'       => $classeVariacao,
            'participacao_pct_fmt'  => number_format((float) $resultado['participacao_pct'], 1, ',', '.') . '%',
            'participantes'         => (int) $resultado['participantes'],
            'inscritos'             => (int) $resultado['inscritos'],
            'nota_fg_fmt'           => number_format((float) $resultado['nota_formacao_geral'], 1, ',', '.'),
            'nota_ce_fmt'           => number_format((float) $resultado['nota_componente_especifico'], 1, ',', '.'),
            'eh_oficial'            => $ehOficial,
            'origem_rotulo'         => $ehOficial ? 'Oficial INEP' : 'Simulado Didático',
            'origem_classe'         => $ehOficial ? 'badge-oficial' : 'badge-simulado',
            'fonte_oficial'         => $resultado['fonte_oficial'] ?? null,
            'cobertura_total'       => (int) ($cobertura['resultados'] ?? 0),
            'cobertura_oficiais'    => (int) ($cobertura['resultados_oficiais'] ?? 0),
            'cobertura_simulados'   => (int) ($cobertura['resultados_simulados'] ?? 0),
            'cobertura_periodo'     => ($cobertura['primeiro_ano'] ?? '') . '–' . ($cobertura['ultimo_ano'] ?? ''),
        ];
    }

    /**
     * Calcula as larguras proporcionais (0% a 100%) para as barras horizontais
     * de comparação de desempenho na edição selecionada.
     *
     * @param array<string, mixed>|null $resultado Linha da view `vw_resultado`.
     * @return array<int, array{rotulo: string, valor_fmt: string, percentual: float, classe: string}>
     */
    private function calcularBarrasDesempenho(?array $resultado): array
    {
        if ($resultado === null) {
            return [];
        }

        $fg       = (float) $resultado['nota_formacao_geral'];
        $ce       = (float) $resultado['nota_componente_especifico'];
        $continuo = (float) $resultado['conceito_continuo'];
        $partPct  = (float) $resultado['participacao_pct'];

        return [
            [
                'rotulo'     => 'Formação Geral (FG)',
                'valor_fmt'  => number_format($fg, 1, ',', '.') . ' / 100',
                'percentual' => max(0, min(100, round($fg, 1))),
                'classe'     => 'barra-fg',
            ],
            [
                'rotulo'     => 'Componente Específico (CE)',
                'valor_fmt'  => number_format($ce, 1, ',', '.') . ' / 100',
                'percentual' => max(0, min(100, round($ce, 1))),
                'classe'     => 'barra-ce',
            ],
            [
                'rotulo'     => 'Conceito Contínuo (escala 0 a 5)',
                'valor_fmt'  => number_format($continuo, 2, ',', '.') . ' / 5,00',
                'percentual' => max(0, min(100, round(($continuo / 5.0) * 100, 1))),
                'classe'     => 'barra-conceito',
            ],
            [
                'rotulo'     => 'Taxa de Participação (concluintes)',
                'valor_fmt'  => number_format($partPct, 1, ',', '.') . '%',
                'percentual' => max(0, min(100, round($partPct, 1))),
                'classe'     => 'barra-participacao',
            ],
        ];
    }

    /**
     * Formata cada linha da série histórica (2015–2025) calculando a altura
     * percentual da coluna no gráfico (baseada na escala 0 a 5 do Conceito Contínuo).
     *
     * @param array<int, array<string, mixed>> $historicoBruto Linhas de `vw_resultado`.
     * @param int                              $anoAtivo       Ano selecionado no filtro.
     * @return array<int, array<string, mixed>>
     */
    private function formatarSerieHistorica(array $historicoBruto, int $anoAtivo): array
    {
        $itens = [];
        foreach ($historicoBruto as $linha) {
            $ano      = (int) $linha['ano'];
            $continuo = (float) $linha['conceito_continuo'];
            $fg       = (float) $linha['nota_formacao_geral'];
            $ce       = (float) $linha['nota_componente_especifico'];
            $oficial  = ($linha['origem_dado'] === 'OFICIAL_INEP');

            $itens[] = [
                'ano'                   => $ano,
                'selecionado'           => ($ano === $anoAtivo),
                'conceito_faixa'        => $linha['conceito_faixa'],
                'conceito_continuo_fmt' => number_format($continuo, 2, ',', '.'),
                'altura_pct'            => max(8, min(100, round(($continuo / 5.0) * 100, 1))),
                'nota_fg_fmt'           => number_format($fg, 1, ',', '.'),
                'nota_ce_fmt'           => number_format($ce, 1, ',', '.'),
                'participacao_fmt'      => number_format((float) $linha['participacao_pct'], 1, ',', '.') . '%',
                'eh_oficial'            => $oficial,
                'origem_rotulo'         => $oficial ? 'Oficial' : 'Simulado',
            ];
        }
        return $itens;
    }

    /**
     * Gera os textos da seção de Síntese e Leitura Pedagógica a partir de
     * `ponto_forte`, `ponto_atencao` e `diferenca_fg_ce` (FG - CE).
     *
     * @param array<string, mixed>|null $resultado Linha da view `vw_resultado`.
     * @return array<string, mixed>
     */
    private function gerarSinteseInterpretativa(?array $resultado): array
    {
        if ($resultado === null) {
            return [];
        }

        $dif    = (float) $resultado['diferenca_fg_ce'];
        $difFmt = number_format(abs($dif), 1, ',', '.');

        if ($dif > 2.0) {
            $leituraDiferenca = "Formação Geral está {$difFmt} pontos acima do Componente Específico (+{$difFmt} pts).";
            $statusDiferenca  = 'fg-acima';
        } elseif ($dif < -2.0) {
            $leituraDiferenca = "Componente Específico está {$difFmt} pontos acima da Formação Geral ({$difFmt} pts de vantagem para CE).";
            $statusDiferenca  = 'ce-acima';
        } else {
            $leituraDiferenca = "Equilíbrio entre Formação Geral e Componente Específico (diferença de {$difFmt} pts).";
            $statusDiferenca  = 'equilibrado';
        }

        return [
            'ponto_forte'       => $resultado['ponto_forte'],
            'ponto_atencao'     => $resultado['ponto_atencao'],
            'diferenca_fg_ce'   => $dif,
            'leitura_diferenca' => $leituraDiferenca,
            'status_diferenca'  => $statusDiferenca,
        ];
    }
}
