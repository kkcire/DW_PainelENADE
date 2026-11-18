<?php
/**
 * views/painel.view.php - View pura do Painel de Indicadores ENADE.
 * Recebe $dadosPainel preparado pelo PainelEstatisticasService.
 */
require __DIR__ . '/../includes/header.php';

$ofertas           = $dadosPainel['ofertas'];
$anos              = $dadosPainel['anos'];
$idOfertaAtiva     = $dadosPainel['id_oferta_ativa'];
$anoAtivo          = $dadosPainel['ano_ativo'];
$ofertaSelecionada = $dadosPainel['oferta_selecionada'];
$cobertura         = $dadosPainel['cobertura'];
$resultado         = $dadosPainel['resultado'];
$cards             = $dadosPainel['cards'];
$barras            = $dadosPainel['barras'];
$historico         = $dadosPainel['historico'];
$sintese           = $dadosPainel['sintese'];
$acoesOferta       = $dadosPainel['acoes_oferta'];
$resumoAcoes       = $dadosPainel['resumo_acoes'];
?>

<section class="cabecalho-pagina">
    <div>
        <span class="tag-recorte">
            Recorte <?= e($cobertura['recorte'] ?? 'R05') ?> · <?= e($cobertura['nome'] ?? 'História e Pedagogia') ?>
        </span>
        <h1>Painel de Indicadores ENADE</h1>
        <?php if ($ofertaSelecionada !== null): ?>
            <p class="subtitulo-pagina">
                Exibindo: <strong><?= e($ofertaSelecionada['rotulo']) ?></strong> — Edição <strong><?= e($anoAtivo) ?></strong>
            </p>
        <?php endif; ?>
    </div>

    <form method="get" action="painel.php" class="barra-filtros">
        <div class="filtro-item">
            <label for="id_oferta">Oferta de Curso</label>
            <select name="id_oferta" id="id_oferta" onchange="this.form.submit()">
                <?php foreach ($ofertas as $oferta): ?>
                    <option value="<?= (int) $oferta['id_oferta'] ?>"
                        <?= ((int) $oferta['id_oferta'] === $idOfertaAtiva) ? 'selected' : '' ?>>
                        <?= e($oferta['rotulo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filtro-item">
            <label for="ano">Edição (Ano)</label>
            <select name="ano" id="ano" onchange="this.form.submit()">
                <?php foreach ($anos as $ano): ?>
                    <option value="<?= (int) $ano ?>" <?= ($ano === $anoAtivo) ? 'selected' : '' ?>>
                        <?= (int) $ano ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primario">Filtrar</button>
    </form>
</section>

<?php if (!$cards['tem_dados']): ?>
    <section class="card estado-vazio">
        <h2>Sem dados para esta oferta/edição</h2>
        <p>Não foram encontrados resultados registrados para a combinação de oferta e ano selecionada.</p>
    </section>
<?php else: ?>

    <!-- Faixa de identificação da origem do dado (obrigatória conforme README) -->
    <div class="faixa-origem <?= e($cards['origem_classe']) ?>">
        <div>
            <span class="badge <?= e($cards['origem_classe']) ?>">
                <?= e($cards['origem_rotulo']) ?>
            </span>
            <?php if ($cards['eh_oficial']): ?>
                <span>Resultado oficial publicado pelo Inep para esta oferta e edição.</span>
            <?php else: ?>
                <span>Registro da base didática híbrida (valor simulado para fins acadêmicos, não oficial do Inep).</span>
            <?php endif; ?>
        </div>
        <?php if (!empty($cards['fonte_oficial'])): ?>
            <a href="<?= e($cards['fonte_oficial']) ?>" target="_blank" rel="noopener noreferrer" class="link-fonte">
                Consultar planilha oficial do Inep ↗
            </a>
        <?php endif; ?>
    </div>

    <!-- 4 Cards Principais -->
    <section class="grade-cards">
        <article class="card card-indicador">
            <span class="card-titulo">Conceito ENADE (<?= e($anoAtivo) ?>)</span>
            <div class="card-valor-destaque">
                Faixa <?= e($cards['conceito_faixa']) ?>
                <small class="card-subvalor">Contínuo: <?= e($cards['conceito_continuo_fmt']) ?></small>
            </div>
            <span class="badge-variacao variacao-<?= e($cards['variacao_classe']) ?>">
                <?= e($cards['variacao_rotulo']) ?>
            </span>
        </article>

        <article class="card card-indicador">
            <span class="card-titulo">Participação de Concluintes</span>
            <div class="card-valor-destaque">
                <?= e($cards['participacao_pct_fmt']) ?>
            </div>
            <p class="card-detalhe">
                <strong><?= e($cards['participantes']) ?></strong> participantes de
                <strong><?= e($cards['inscritos']) ?></strong> concluintes inscritos
            </p>
        </article>

        <article class="card card-indicador">
            <span class="card-titulo">Notas dos Componentes</span>
            <div class="card-notas-duplas">
                <div>
                    <small>Formação Geral (FG)</small>
                    <strong><?= e($cards['nota_fg_fmt']) ?></strong>
                </div>
                <div>
                    <small>Comp. Específico (CE)</small>
                    <strong><?= e($cards['nota_ce_fmt']) ?></strong>
                </div>
            </div>
            <p class="card-detalhe"><?= e($sintese['leitura_diferenca']) ?></p>
        </article>

        <article class="card card-indicador">
            <span class="card-titulo">Cobertura do Recorte (<?= e($cobertura['recorte'] ?? 'R05') ?>)</span>
            <div class="card-valor-destaque">
                <?= e($cards['cobertura_total']) ?> <small class="card-subvalor">registros (<?= e($cards['cobertura_periodo']) ?>)</small>
            </div>
            <p class="card-detalhe">
                <strong><?= e($cards['cobertura_oficiais']) ?></strong> oficiais Inep ·
                <strong><?= e($cards['cobertura_simulados']) ?></strong> simulados ·
                <strong><?= (int) ($cobertura['ofertas_sem_oficial'] ?? 0) ?></strong> oferta sem dado oficial
            </p>
        </article>
    </section>

    <!-- Barras de Desempenho + Síntese Interpretativa -->
    <section class="grade-duas-colunas">
        <article class="card">
            <h2>Desempenho Detalhado na Edição <?= e($anoAtivo) ?></h2>
            <p class="texto-suave">Comparativo proporcional entre os indicadores da oferta selecionada.</p>

            <div class="lista-barras">
                <?php foreach ($barras as $barra): ?>
                    <div class="barra-item">
                        <div class="barra-topo">
                            <span><?= e($barra['rotulo']) ?></span>
                            <strong><?= e($barra['valor_fmt']) ?></strong>
                        </div>
                        <div class="barra-trilho">
                            <div class="barra-preenchimento <?= e($barra['classe']) ?>"
                                 style="width: <?= e($barra['percentual']) ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="card">
            <h2>Síntese e Leitura Pedagógica</h2>
            <p class="texto-suave">Análise consolidada registrada para a oferta e edição.</p>

            <div class="bloco-sintese sintese-forte">
                <h3>Ponto Forte</h3>
                <p><?= e($sintese['ponto_forte']) ?></p>
            </div>

            <div class="bloco-sintese sintese-atencao">
                <h3>Ponto de Atenção</h3>
                <p><?= e($sintese['ponto_atencao']) ?></p>
            </div>

            <div class="bloco-sintese sintese-diferenca">
                <h3>Comparativo FG × CE</h3>
                <p><?= e($sintese['leitura_diferenca']) ?></p>
            </div>
        </article>
    </section>

    <!-- Série Histórica (Gráfico de Colunas CSS + Tabela) -->
    <section class="card secao-bloco">
        <div class="secao-topo">
            <div>
                <h2>Série Histórica da Oferta (2015–2025)</h2>
                <p class="texto-suave">
                    Evolução do Conceito Contínuo (0 a 5) e das notas ao longo das edições. Clique em um ano para inspecionar.
                </p>
            </div>
            <div class="legenda-historico">
                <span class="legenda-item"><i class="ponto-cor cor-oficial"></i> Oficial INEP</span>
                <span class="legenda-item"><i class="ponto-cor cor-simulado"></i> Simulado Didático</span>
            </div>
        </div>

        <div class="grafico-colunas" role="region" aria-label="Evolução do Conceito Contínuo">
            <?php foreach ($historico as $item): ?>
                <a href="painel.php?id_oferta=<?= (int) $idOfertaAtiva ?>&amp;ano=<?= (int) $item['ano'] ?>"
                   class="coluna-wrapper <?= $item['selecionado'] ? 'coluna-ativa' : '' ?>"
                   title="Edição <?= (int) $item['ano'] ?>: Conceito <?= e($item['conceito_continuo_fmt']) ?> (<?= e($item['origem_rotulo']) ?>)">
                    <span class="coluna-valor"><?= e($item['conceito_continuo_fmt']) ?></span>
                    <div class="coluna-area">
                        <div class="coluna-barra <?= $item['eh_oficial'] ? 'cor-oficial' : 'cor-simulado' ?>"
                             style="height: <?= e($item['altura_pct']) ?>%;"></div>
                    </div>
                    <span class="coluna-ano"><?= (int) $item['ano'] ?></span>
                    <span class="coluna-faixa">F<?= e($item['conceito_faixa']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="tabela-responsiva">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Edição</th>
                        <th>Origem</th>
                        <th>Participação</th>
                        <th>Formação Geral</th>
                        <th>Comp. Específico</th>
                        <th>Conceito Contínuo</th>
                        <th>Faixa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historico as $item): ?>
                        <tr class="<?= $item['selecionado'] ? 'linha-destaque' : '' ?>">
                            <td>
                                <a href="painel.php?id_oferta=<?= (int) $idOfertaAtiva ?>&amp;ano=<?= (int) $item['ano'] ?>">
                                    <strong><?= (int) $item['ano'] ?></strong>
                                </a>
                            </td>
                            <td>
                                <span class="badge <?= $item['eh_oficial'] ? 'badge-oficial' : 'badge-simulado' ?>">
                                    <?= e($item['origem_rotulo']) ?>
                                </span>
                            </td>
                            <td><?= e($item['participacao_fmt']) ?></td>
                            <td><?= e($item['nota_fg_fmt']) ?></td>
                            <td><?= e($item['nota_ce_fmt']) ?></td>
                            <td><strong><?= e($item['conceito_continuo_fmt']) ?></strong></td>
                            <td><span class="badge-faixa"><?= e($item['conceito_faixa']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Resumo de Ações de Acompanhamento desta Oferta -->
    <section class="card secao-bloco">
        <div class="secao-topo">
            <div>
                <h2>Ações de Acompanhamento desta Oferta</h2>
                <p class="texto-suave">
                    Total: <strong><?= (int) ($resumoAcoes['total'] ?? 0) ?></strong>
                    (Planejadas: <?= (int) ($resumoAcoes['Planejada'] ?? 0) ?> ·
                    Em andamento: <?= (int) ($resumoAcoes['Em andamento'] ?? 0) ?> ·
                    Concluídas: <?= (int) ($resumoAcoes['Concluída'] ?? 0) ?>)
                </p>
            </div>
            <div class="acoes-botoes-topo">
                <a href="acoes/listar.php?id_oferta=<?= (int) $idOfertaAtiva ?>" class="btn btn-contorno">
                    Ver todas da oferta
                </a>
                <a href="acoes/criar.php?id_oferta=<?= (int) $idOfertaAtiva ?>" class="btn btn-primario">
                    + Nova Ação para esta Oferta
                </a>
            </div>
        </div>

        <?php if (empty($acoesOferta)): ?>
            <p class="texto-vazio">
                Nenhuma ação de acompanhamento cadastrada para esta oferta ainda.
                <a href="acoes/criar.php?id_oferta=<?= (int) $idOfertaAtiva ?>">Clique aqui para cadastrar a primeira ação.</a>
            </p>
        <?php else: ?>
            <div class="tabela-responsiva">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Responsável</th>
                            <th>Prazo</th>
                            <th>Situação</th>
                            <th>Evidência</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($acoesOferta as $acao): ?>
                            <tr>
                                <td><strong><?= e($acao['titulo']) ?></strong></td>
                                <td><?= e($acao['responsavel']) ?></td>
                                <td><?= e(date('d/m/Y', strtotime($acao['prazo']))) ?></td>
                                <td>
                                    <span class="badge-situacao situacao-<?= e(strtolower(str_replace([' ', 'í'], ['-', 'i'], $acao['situacao']))) ?>">
                                        <?= e($acao['situacao']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($acao['tem_anexo'])): ?>
                                        <a href="uploads/<?= e($acao['anexo_arquivo']) ?>" target="_blank" rel="noopener">
                                            📎 <?= e($acao['anexo_nome_original']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="texto-suave">Sem anexo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="celula-acoes">
                                    <a href="acoes/visualizar.php?id=<?= (int) $acao['id_acao'] ?>" class="btn btn-pequeno btn-contorno">Ver</a>
                                    <a href="acoes/editar.php?id=<?= (int) $acao['id_acao'] ?>" class="btn btn-pequeno btn-contorno">Editar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
