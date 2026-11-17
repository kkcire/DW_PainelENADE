<?php
/**
 * views/acoes/form.view.php - View pura de formulário reaproveitada por criar.php e editar.php!
 */
require __DIR__ . '/../../includes/header.php';

$actionUrl = $modoEdicao
    ? 'editar.php?id=' . (int) $acaoForm['id_acao']
    : 'criar.php';
?>

<section class="cabecalho-pagina">
    <div>
        <span class="tag-recorte">
            <?= $modoEdicao ? 'Edição de Registro' : 'Novo Registro' ?>
        </span>
        <h1>
            <?= $modoEdicao ? 'Editar Ação #' . (int) $acaoForm['id_acao'] : 'Nova Ação de Acompanhamento' ?>
        </h1>
        <p class="subtitulo-pagina">
            Preencha os campos obrigatórios e, se desejar, anexe um documento comprobatório (PDF, PNG ou JPG até 2 MB).
        </p>
    </div>

    <div>
        <a href="listar.php" class="btn btn-contorno">← Voltar para a Lista</a>
    </div>
</section>

<?php if (!empty($erros)): ?>
    <div class="alerta alerta-erro" role="alert">
        <strong>Corrija os seguintes itens antes de continuar:</strong>
        <ul class="lista-erros">
            <?php foreach ($erros as $erro): ?>
                <li><?= e($erro) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<section class="card secao-bloco">
    <form method="post"
          action="<?= e($actionUrl) ?>"
          enctype="multipart/form-data"
          id="form-acao"
          class="formulario"
          novalidate>

        <?php if ($modoEdicao): ?>
            <input type="hidden" name="id_acao" value="<?= (int) $acaoForm['id_acao'] ?>">
        <?php endif; ?>

        <div class="grade-formulario">
            <div class="campo-grupo campo-largo">
                <label for="id_oferta">Oferta Vinculada *</label>
                <select name="id_oferta" id="id_oferta" required>
                    <option value="">Selecione uma oferta...</option>
                    <?php foreach ($ofertas as $oferta): ?>
                        <option value="<?= (int) $oferta['id_oferta'] ?>"
                            <?= ((int) $acaoForm['id_oferta'] === (int) $oferta['id_oferta']) ? 'selected' : '' ?>>
                            <?= e($oferta['rotulo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo-grupo campo-largo">
                <label for="titulo">Título da Ação * <small>(máx. 150 caracteres)</small></label>
                <input
                    type="text"
                    id="titulo"
                    name="titulo"
                    maxlength="150"
                    value="<?= e($acaoForm['titulo']) ?>"
                    placeholder="Ex.: Oficina de reforço em Formação Geral e leitura crítica"
                    required
                >
            </div>

            <div class="campo-grupo">
                <label for="responsavel">Responsável * <small>(máx. 100 caracteres)</small></label>
                <input
                    type="text"
                    id="responsavel"
                    name="responsavel"
                    maxlength="100"
                    value="<?= e($acaoForm['responsavel']) ?>"
                    placeholder="Ex.: Coordenação de Pedagogia / NDE"
                    required
                >
            </div>

            <div class="campo-grupo">
                <label for="situacao">Situação *</label>
                <select name="situacao" id="situacao" required>
                    <?php foreach ($situacoesPermitidas as $sit): ?>
                        <option value="<?= e($sit) ?>" <?= ($acaoForm['situacao'] === $sit) ? 'selected' : '' ?>>
                            <?= e($sit) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo-grupo">
                <label for="prazo">Prazo de Execução *</label>
                <input
                    type="date"
                    id="prazo"
                    name="prazo"
                    value="<?= e($acaoForm['prazo']) ?>"
                    required
                >
            </div>

            <div class="campo-grupo campo-largo">
                <label for="descricao">Descrição Detalhada *</label>
                <textarea
                    id="descricao"
                    name="descricao"
                    rows="5"
                    placeholder="Descreva os objetivos, etapas planejadas e indicadores que esta ação pretende melhorar..."
                    required
                ><?= e($acaoForm['descricao']) ?></textarea>
            </div>

            <div class="campo-grupo campo-largo caixa-upload">
                <label for="anexo">Evidência / Anexo (Opcional — PDF, PNG ou JPG até 2 MB)</label>
                <input
                    type="file"
                    id="anexo"
                    name="anexo"
                    accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg"
                >
                <small id="feedback-arquivo" class="texto-suave">
                    Formatos aceitos: <strong>.pdf, .png, .jpg, .jpeg</strong> · Tamanho máximo: <strong>2 MB</strong>.
                </small>

                <?php if ($modoEdicao && !empty($acaoForm['anexo_arquivo'])): ?>
                    <div class="anexo-atual">
                        <span>
                            Anexo atual:
                            <a href="../uploads/<?= e($acaoForm['anexo_arquivo']) ?>" target="_blank" rel="noopener">
                                📎 <?= e($acaoForm['anexo_nome_original']) ?>
                            </a>
                        </span>
                        <label class="checkbox-inline">
                            <input type="checkbox" name="remover_anexo" value="1">
                            Remover anexo atual (sem enviar substituto)
                        </label>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="barra-botoes-form">
            <a href="listar.php" class="btn btn-contorno">Cancelar</a>
            <button type="submit" class="btn btn-primario">
                <?= $modoEdicao ? 'Salvar Alterações' : 'Cadastrar Ação' ?>
            </button>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
