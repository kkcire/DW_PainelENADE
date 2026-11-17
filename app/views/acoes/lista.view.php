<?php
/**
 * views/acoes/lista.view.php - View pura da listagem de Ações de Acompanhamento.
 */
require __DIR__ . '/../../includes/header.php';
?>

<section class="cabecalho-pagina">
    <div>
        <span class="tag-recorte">Gestão de Ações · CRUD Completo</span>
        <h1>Ações de Acompanhamento</h1>
        <p class="subtitulo-pagina">
            Gerencie planos de melhoria vinculados às ofertas do Recorte R05 e anexe evidências (PDF, PNG ou JPG).
        </p>
    </div>

    <div>
        <a href="criar.php<?= $idOfertaFiltro ? '?id_oferta=' . (int) $idOfertaFiltro : '' ?>" class="btn btn-primario">
            + Cadastrar Nova Ação
        </a>
    </div>
</section>

<!-- Mini-cards de resumo por situação -->
<section class="grade-cards grade-cards-compacta">
    <div class="card card-mini">
        <span>Total listado</span>
        <strong><?= (int) ($resumoAcoes['total'] ?? 0) ?></strong>
    </div>
    <div class="card card-mini">
        <span>Planejadas</span>
        <strong><?= (int) ($resumoAcoes['Planejada'] ?? 0) ?></strong>
    </div>
    <div class="card card-mini">
        <span>Em andamento</span>
        <strong><?= (int) ($resumoAcoes['Em andamento'] ?? 0) ?></strong>
    </div>
    <div class="card card-mini">
        <span>Concluídas</span>
        <strong><?= (int) ($resumoAcoes['Concluída'] ?? 0) ?></strong>
    </div>
</section>

<!-- Filtros de busca -->
<section class="card secao-bloco">
    <form method="get" action="listar.php" class="barra-filtros">
        <div class="filtro-item">
            <label for="id_oferta">Filtrar por Oferta</label>
            <select name="id_oferta" id="id_oferta">
                <option value="">Todas as ofertas do Recorte R05</option>
                <?php foreach ($ofertas as $oferta): ?>
                    <option value="<?= (int) $oferta['id_oferta'] ?>"
                        <?= ($idOfertaFiltro === (int) $oferta['id_oferta']) ? 'selected' : '' ?>>
                        <?= e($oferta['rotulo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filtro-item">
            <label for="situacao">Situação</label>
            <select name="situacao" id="situacao">
                <option value="">Todas as situações</option>
                <?php foreach ($situacoesPermitidas as $sit): ?>
                    <option value="<?= e($sit) ?>" <?= ($situacaoFiltro === $sit) ? 'selected' : '' ?>>
                        <?= e($sit) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filtro-botoes">
            <button type="submit" class="btn btn-primario">Aplicar Filtro</button>
            <?php if ($idOfertaFiltro !== null || $situacaoFiltro !== null): ?>
                <a href="listar.php" class="btn btn-contorno">Limpar</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- Tabela de Ações -->
<section class="card secao-bloco">
    <?php if (empty($acoes)): ?>
        <div class="estado-vazio">
            <h2>Nenhuma ação encontrada</h2>
            <p>Não há ações cadastradas para os filtros selecionados.</p>
            <a href="criar.php" class="btn btn-primario">+ Cadastrar Primeira Ação</a>
        </div>
    <?php else: ?>
        <div class="tabela-responsiva">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Oferta</th>
                        <th>Título</th>
                        <th>Responsável</th>
                        <th>Prazo</th>
                        <th>Situação</th>
                        <th>Evidência</th>
                        <th>Operações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($acoes as $acao): ?>
                        <?php
                        $slugSituacao = strtolower(str_replace([' ', 'í'], ['-', 'i'], $acao['situacao']));
                        ?>
                        <tr>
                            <td>#<?= (int) $acao['id_acao'] ?></td>
                            <td><?= e($acao['oferta_rotulo']) ?></td>
                            <td>
                                <strong><?= e($acao['titulo']) ?></strong>
                                <div class="texto-resumo"><?= e(strlen($acao['descricao']) > 80 ? substr($acao['descricao'], 0, 80) . '...' : $acao['descricao']) ?></div>
                            </td>
                            <td><?= e($acao['responsavel']) ?></td>
                            <td><?= e(date('d/m/Y', strtotime($acao['prazo']))) ?></td>
                            <td>
                                <span class="badge-situacao situacao-<?= e($slugSituacao) ?>">
                                    <?= e($acao['situacao']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($acao['tem_anexo'])): ?>
                                    <a href="../uploads/<?= e($acao['anexo_arquivo']) ?>"
                                       target="_blank"
                                       rel="noopener"
                                       class="link-anexo">
                                        📎 <?= e($acao['anexo_nome_original']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="texto-suave">Sem anexo</span>
                                <?php endif; ?>
                            </td>
                            <td class="celula-acoes">
                                <a href="visualizar.php?id=<?= (int) $acao['id_acao'] ?>" class="btn btn-pequeno btn-contorno">Ver</a>
                                <a href="editar.php?id=<?= (int) $acao['id_acao'] ?>" class="btn btn-pequeno btn-contorno">Editar</a>
                                <form method="post"
                                      action="excluir.php"
                                      class="form-inline js-confirmar-exclusao"
                                      data-titulo="<?= e($acao['titulo']) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $acao['id_acao'] ?>">
                                    <button type="submit" class="btn btn-pequeno btn-perigo">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
