<?php
/**
 * PainelEstatisticasService.php - Camada Services (SRP - Princípio da Responsabilidade Única)
 *
 * Por que existe esta camada Service?
 * -----------------------------------
 * Enquanto os Repositories (`app/Models/`) só sabem falar SQL com o PostgreSQL e os
 * Thin Controllers (`app/painel.php`) só lidam com HTTP e JSON, o Service concentra
 * toda a REGRA DE NEGÓCIO e os CÁLCULOS do painel:
 *
 * 1. Define qual oferta e qual ano devem aparecer selecionados (respeitando os filtros da URL
 *    ou escolhendo valores padrão inteligentes quando o usuário abre o painel pela primeira vez).
 * 2. Monta a estrutura pronta dos CARDS principais (conceito, faixa, variação de faixa,
 *    participação, notas FG/CE e badge de origem do dado: Oficial x Simulado).
 * 3. Prepara o comparativo visual entre Formação Geral (FG) e Componente Específico (CE).
 * 4. Gera a síntese interpretativa ("Leitura do grupo" + ponto forte + ponto de atenção).
 * 5. Consolida a série histórica (2015–2025) da oferta selecionada e calcula estatísticas da série
 *    (média histórica, melhor ano, pior ano, contagem de edições oficiais vs simuladas).
 * 6. Integra o resumo de Ações de Acompanhamento daquela oferta e a Cobertura do recorte R05.
 */

class PainelEstatisticasService
{
    private IndicadorEnadeRepository $indicadorRepo;
    private AcaoRepository $acaoRepo;

    public function __construct(
        IndicadorEnadeRepository $indicadorRepo,
        AcaoRepository $acaoRepo
    ) {
        $this->indicadorRepo = $indicadorRepo;
        $this->acaoRepo      = $acaoRepo;
    }

    /**
     * Monta o payload completo que o endpoint `app/painel.php` devolve em JSON para o React.
     *
     * @param int|null $idOfertaFiltro ID da oferta vinda de `?id_oferta=` (opcional)
     * @param int|null $anoFiltro      Ano vindo de `?ano=` (opcional)
     * @return array<string, mixed>
     */
    public function montarDadosPainel(?int $idOfertaFiltro = null, ?int $anoFiltro = null): array
    {
        // 1. Busca as opções dos filtros e a cobertura geral do recorte R05
        $ofertas   = $this->indicadorRepo->listarOfertas();
        $anos      = $this->indicadorRepo->listarAnos();
        $cobertura = $this->indicadorRepo->buscarCobertura();

        // Caso extremo: banco ainda sem dados iniciais carregados
        if (empty($ofertas) || empty($anos)) {
            return [
                'filtros' => [
                    'ofertas' => $ofertas,
                    'anos'    => $anos,
                ],
                'selecionado' => [
                    'id_oferta' => null,
                    'ano'       => null,
                    'oferta'    => null,
                ],
                'cobertura'          => $cobertura,
                'sem_dados'          => true,
                'mensagem_sem_dados' => 'Ainda não há dados de ofertas ou resultados cadastrados no banco.',
                'resultado'          => null,
                'cards'              => null,
                'comparativo_fg_ce'  => null,
                'sintese'            => null,
                'historico'          => [],
                'resumo_historico'   => null,
                'resumo_acoes'       => $this->acaoRepo->resumirPorSituacao(null),
            ];
        }

        // 2. Define a oferta selecionada:
        //    Se o usuário não informou `id_oferta`, seleciona automaticamente a primeira oferta da lista.
        $idOfertaSelecionada = $idOfertaFiltro ?? (int) $ofertas[0]['id_oferta'];
        $ofertaSelecionada   = $this->indicadorRepo->buscarOfertaPorId($idOfertaSelecionada);

        //    Se o usuário não informou `ano`, seleciona automaticamente o ano mais recente (índice 0, pois vem DESC).
        $anoSelecionado = $anoFiltro ?? (int) $anos[0];

        // 3. Busca os dados da oferta selecionada no banco:
        //    - Resultado da combinação (oferta + ano)
        //    - Série histórica completa da oferta (2015..2025)
        //    - Resumo de ações pedagógicas vinculadas à oferta
        $resultado    = $this->indicadorRepo->buscarResultadoPorOfertaEAno($idOfertaSelecionada, $anoSelecionado);
        $historicoRaw = $this->indicadorRepo->listarHistoricoPorOferta($idOfertaSelecionada);
        $resumoAcoes  = $this->acaoRepo->resumirPorSituacao($idOfertaSelecionada);

        // Enriquece cada item do histórico com o rótulo legível da origem do dado e da variação de faixa
        $historico = array_map(function (array $item): array {
            $item['origem_rotulo']         = $this->formatarRotuloOrigem($item['origem_dado']);
            $item['rotulo_variacao_faixa'] = $this->formatarVariacaoFaixa($item['variacao_faixa']);
            return $item;
        }, $historicoRaw);

        // Calcula estatísticas agregadas da série histórica da oferta (média, melhor ano, etc.)
        $resumoHistorico = $this->calcularResumoHistorico($historico);

        // 4. Trata o estado "Sem dados" previsto na documentação (quando oferta/ano não possui registro)
        if ($resultado === null) {
            return [
                'filtros' => [
                    'ofertas' => $ofertas,
                    'anos'    => $anos,
                ],
                'selecionado' => [
                    'id_oferta' => $idOfertaSelecionada,
                    'ano'       => $anoSelecionado,
                    'oferta'    => $ofertaSelecionada,
                ],
                'cobertura'          => $cobertura,
                'sem_dados'          => true,
                'mensagem_sem_dados' => 'Sem dados para esta oferta/edição.',
                'resultado'          => null,
                'cards'              => null,
                'comparativo_fg_ce'  => null,
                'sintese'            => null,
                'historico'          => $historico,
                'resumo_historico'   => $resumoHistorico,
                'resumo_acoes'       => $resumoAcoes,
            ];
        }

        // 5. Monta os blocos prontos para o frontend renderizar sem precisar repetir lógica de negócio
        $origemRotulo = $this->formatarRotuloOrigem($resultado['origem_dado']);
        $resultado['origem_rotulo']         = $origemRotulo;
        $resultado['rotulo_variacao_faixa'] = $this->formatarVariacaoFaixa($resultado['variacao_faixa']);

        $cards           = $this->montarCards($resultado);
        $comparativoFgCe = $this->montarComparativoFgCe($resultado);
        $sintese         = $this->montarSintese($resultado, $comparativoFgCe);

        return [
            'filtros' => [
                'ofertas' => $ofertas,
                'anos'    => $anos,
            ],
            'selecionado' => [
                'id_oferta' => $idOfertaSelecionada,
                'ano'       => $anoSelecionado,
                'oferta'    => $ofertaSelecionada,
            ],
            'cobertura'          => $cobertura,
            'sem_dados'          => false,
            'mensagem_sem_dados' => null,
            'resultado'          => $resultado,
            'cards'              => $cards,
            'comparativo_fg_ce'  => $comparativoFgCe,
            'sintese'            => $sintese,
            'historico'          => $historico,
            'resumo_historico'   => $resumoHistorico,
            'resumo_acoes'       => $resumoAcoes,
        ];
    }

    /**
     * Organiza os indicadores principais em um objeto focado nos KPI Cards do topo do painel.
     */
    private function montarCards(array $resultado): array
    {
        // Diferença numérica do conceito contínuo em relação à edição anterior (quando existe)
        $variacaoConceitoContinuo = null;
        if ($resultado['conceito_continuo_anterior'] !== null) {
            $variacaoConceitoContinuo = round(
                $resultado['conceito_continuo'] - $resultado['conceito_continuo_anterior'],
                4
            );
        }

        return [
            'conceito_continuo'          => $resultado['conceito_continuo'],
            'conceito_faixa'             => $resultado['conceito_faixa'],
            'ano_anterior'               => $resultado['ano_anterior'],
            'conceito_continuo_anterior' => $resultado['conceito_continuo_anterior'],
            'conceito_faixa_anterior'    => $resultado['conceito_faixa_anterior'],
            'variacao_faixa'             => $resultado['variacao_faixa'],
            'rotulo_variacao_faixa'      => $resultado['rotulo_variacao_faixa'],
            'variacao_conceito_continuo' => $variacaoConceitoContinuo,
            'inscritos'                  => $resultado['inscritos'],
            'participantes'              => $resultado['participantes'],
            'participacao_pct'           => $resultado['participacao_pct'],
            'nota_formacao_geral'        => $resultado['nota_formacao_geral'],
            'nota_componente_especifico' => $resultado['nota_componente_especifico'],
            'diferenca_fg_ce'            => $resultado['diferenca_fg_ce'],
            'origem_dado'                => $resultado['origem_dado'],
            'origem_rotulo'              => $resultado['origem_rotulo'],
            'eh_oficial'                 => $resultado['origem_dado'] === 'OFICIAL_INEP',
            'fonte_oficial'              => $resultado['fonte_oficial'],
        ];
    }

    /**
     * Prepara os dados para as barras comparativas entre Formação Geral (FG) e Componente Específico (CE).
     * As notas do ENADE vão de 0 a 100, então os percentuais de largura da barra correspondem diretamente à nota.
     */
    private function montarComparativoFgCe(array $resultado): array
    {
        $fg   = $resultado['nota_formacao_geral'];
        $ce   = $resultado['nota_componente_especifico'];
        $diff = $resultado['diferenca_fg_ce']; // FG - CE (já calculado na view vw_resultado)

        // Considera desempenho equilibrado quando a diferença absoluta é menor que 2 pontos
        if (abs($diff) < 2.0) {
            $destaque = 'EQUILIBRADO';
            $resumo   = 'Desempenho equilibrado entre Formação Geral e Componente Específico.';
        } elseif ($diff > 0) {
            $destaque = 'FG';
            $resumo   = sprintf(
                'Formação Geral superou o Componente Específico em %.2f pontos.',
                abs($diff)
            );
        } else {
            $destaque = 'CE';
            $resumo   = sprintf(
                'Componente Específico superou a Formação Geral em %.2f pontos.',
                abs($diff)
            );
        }

        return [
            'formacao_geral'        => round($fg, 2),
            'componente_especifico' => round($ce, 2),
            // Garante valores entre 0 e 100 para uso direto em style={{ width: `${pct}%` }} no React
            'barra_fg_pct'          => max(0.0, min(100.0, round($fg, 2))),
            'barra_ce_pct'          => max(0.0, min(100.0, round($ce, 2))),
            'diferenca_fg_ce'       => round($diff, 2),
            'destaque'              => $destaque,
            'resumo'                => $resumo,
        ];
    }

    /**
     * Monta o bloco de síntese qualitativa ("Leitura do grupo", ponto forte, ponto de atenção e aviso de origem).
     */
    private function montarSintese(array $resultado, array $comparativo): array
    {
        $partesLeitura = [$comparativo['resumo']];

        if ($resultado['ano_anterior'] !== null && $resultado['rotulo_variacao_faixa'] !== null) {
            $partesLeitura[] = sprintf(
                'Em relação à edição anterior (%d), a faixa ficou: %s.',
                $resultado['ano_anterior'],
                $resultado['rotulo_variacao_faixa']
            );
        }

        $avisoOrigem = $resultado['origem_dado'] === 'OFICIAL_INEP'
            ? 'Registro oficial publicado pelo Inep.'
            : 'Registro simulado didático (gerado para fins acadêmicos; não representa dado oficial do Inep).';

        return [
            'ponto_forte'   => $resultado['ponto_forte'],
            'ponto_atencao' => $resultado['ponto_atencao'],
            'leitura_grupo' => implode(' ', $partesLeitura),
            'origem_dado'   => $resultado['origem_dado'],
            'origem_rotulo' => $resultado['origem_rotulo'],
            'aviso_origem'  => $avisoOrigem,
            'fonte_oficial' => $resultado['fonte_oficial'],
        ];
    }

    /**
     * Calcula um resumo estatístico de toda a série histórica da oferta (2015–2025):
     * quantidade de edições, média do conceito contínuo, melhor/pior edição e origem dos dados.
     *
     * @param array<int, array<string, mixed>> $historico
     */
    private function calcularResumoHistorico(array $historico): ?array
    {
        $totalEdicoes = count($historico);
        if ($totalEdicoes === 0) {
            return null;
        }

        $somaConceito   = 0.0;
        $somaPart       = 0.0;
        $oficiais       = 0;
        $simulados      = 0;
        $melhorRegistro = $historico[0];
        $piorRegistro   = $historico[0];

        foreach ($historico as $item) {
            $somaConceito += $item['conceito_continuo'];
            $somaPart     += $item['participacao_pct'];

            if ($item['origem_dado'] === 'OFICIAL_INEP') {
                $oficiais++;
            } else {
                $simulados++;
            }

            if ($item['conceito_continuo'] > $melhorRegistro['conceito_continuo']) {
                $melhorRegistro = $item;
            }
            if ($item['conceito_continuo'] < $piorRegistro['conceito_continuo']) {
                $piorRegistro = $item;
            }
        }

        return [
            'total_edicoes'            => $totalEdicoes,
            'edicoes_oficiais'         => $oficiais,
            'edicoes_simulados'        => $simulados,
            'possui_dado_oficial'      => $oficiais > 0,
            'media_conceito_continuo'  => round($somaConceito / $totalEdicoes, 4),
            'media_participacao_pct'   => round($somaPart / $totalEdicoes, 2),
            'melhor_edicao'            => [
                'ano'               => $melhorRegistro['ano'],
                'conceito_continuo' => $melhorRegistro['conceito_continuo'],
                'conceito_faixa'    => $melhorRegistro['conceito_faixa'],
            ],
            'pior_edicao'              => [
                'ano'               => $piorRegistro['ano'],
                'conceito_continuo' => $piorRegistro['conceito_continuo'],
                'conceito_faixa'    => $piorRegistro['conceito_faixa'],
            ],
        ];
    }

    /**
     * Traduz o código da coluna `origem_dado` para um rótulo amigável de exibição em badge.
     */
    private function formatarRotuloOrigem(string $origemDado): string
    {
        return $origemDado === 'OFICIAL_INEP' ? 'Oficial (Inep)' : 'Simulado didático';
    }

    /**
     * Converte o inteiro `variacao_faixa` (ex.: 1, -1, 0, null) em texto pronto para badge na interface.
     */
    private function formatarVariacaoFaixa(?int $variacao): ?string
    {
        if ($variacao === null) {
            return null;
        }
        if ($variacao > 0) {
            return '+' . $variacao . ($variacao === 1 ? ' faixa' : ' faixas');
        }
        if ($variacao < 0) {
            return $variacao . ($variacao === -1 ? ' faixa' : ' faixas');
        }

        return 'Estável (0 faixa)';
    }
}
