<?php
/**
 * views/acoes/visualizar.view.php - View pura de visualização detalhada de uma Ação.
 */
require __DIR__ . '/../../includes/header.php';

$slugSituacao = strtolower(str_replace([' ', 'í'], ['-', 'i'], $acao['situacao']));
$extensaoAnexo = !empty($acao['anexo_arquivo'])
    ? strtolower(pathinfo($acao['anexo_arquivo'], PATHINFO_EXTENSION))
    : '';
$ehImagem = in_array($extensaoAnexo, ['png', 'jpg', 'jpeg'], true);
?>

<section class="cabecalho-pagina">
    <div>
        <span class="tag-recorte">Ação #<?= (int) $acao['id_acao'] ?> · <?= e($acao['oferta_rotulo']) ?></span>
        <h1><?= e($acao['titulo']) ?></h1>
    </div>

    <div class="acoes-botoes-topo">
        <a href="listar.php" class="btn btn-contorno">← Voltar</a>
        <a href="editar.php?id=<?= (int) $acao['id_acao'] ?>" class="btn btn-primario">Editar Ação</a>
    </div>
</section>

<section class="grade-duas-colunas">
    <article class="card">
        <h2>Informações da Ação</h2>

        <dl class="lista-definicao">
            <div>
                <dt>Oferta Vinculada</dt>
                <dd>
                    <a href="../painel.php?id_oferta=<?= (int) $acao['id_oferta'] ?>">
                        <?= e($acao['oferta_rotulo']) ?> ↗
                    </a>
                </dd>
            </div>
            <div>
                <dt>Situação Atual</dt>
                <dd>
                    <span class="badge-situacao situacao-<?= e($slugSituacao) ?>">
                        <?= e($acao['situacao']) ?>
                    </span>
                </dd>
            </div>
            <div>
                <dt>Responsável</dt>
                <dd><?= e($acao['responsavel']) ?></dd>
            </div>
            <div>
                <dt>Prazo Estabelecido</dt>
                <dd><?= e(date('d/m/Y', strtotime($acao['prazo']))) ?></dd>
            </div>
        </dl>

        <h3>Descrição Detalhada</h3>
        <div class="caixa-descricao">
            <?= nl2br(e($acao['descricao'])) ?>
        </div>
    </article>

    <article class="card">
        <h2>Evidência / Documento Comprobatório</h2>

        <?php if (!empty($acao['tem_anexo']) && !empty($acao['anexo_arquivo'])): ?>
            <div class="card-evidencia">
                <p>
                    <strong>Arquivo original:</strong> <?= e($acao['anexo_nome_original']) ?><br>
                    <small class="texto-suave">Armazenado como: <code><?= e($acao['anexo_arquivo']) ?></code></small>
                </p>

                <div class="acoes-evidencia">
                    <a href="../uploads/<?= e($acao['anexo_arquivo']) ?>"
                       target="_blank"
                       rel="noopener"
                       class="btn btn-primario">
                        Abrir / Baixar Evidência ↗
                    </a>
                </div>

                <?php if ($ehImagem): ?>
                    <div class="preview-imagem">
                        <img src="../uploads/<?= e($acao['anexo_arquivo']) ?>"
                             alt="Evidência anexada: <?= e($acao['anexo_nome_original']) ?>">
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="estado-vazio">
                <p>Esta ação não possui arquivo de evidência anexado.</p>
                <a href="editar.php?id=<?= (int) $acao['id_acao'] ?>" class="btn btn-contorno">
                    Anexar Evidência Agora
                </a>
            </div>
        <?php endif; ?>
    </article>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
