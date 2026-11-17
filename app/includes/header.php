<?php
/**
 * header.php - Cabeçalho padrão e barra de navegação compartilhada.
 */
require_once __DIR__ . '/../auth.php';

$base          = caminhoBaseApp();
$usuarioAtual  = usuarioLogado();
$flashMensagem = obterMensagem();
$tituloPagina  = $tituloPagina ?? 'Painel ENADE · Recorte R05';
$paginaAtiva   = $paginaAtiva ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($tituloPagina) ?> | Painel ENADE R05</title>
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/style.css">
</head>
<body>
    <header class="topo-sistema">
        <div class="container topo-conteudo">
            <a href="<?= e($base) ?>/painel.php" class="marca-sistema">
                <span class="marca-sigla">ENADE · R05</span>
                <div class="marca-textos">
                    <strong>Painel de Acompanhamento Acadêmico</strong>
                    <small>História e Pedagogia · Licenciaturas</small>
                </div>
            </a>

            <?php if ($usuarioAtual !== null): ?>
                <nav class="menu-principal" aria-label="Navegação principal">
                    <a href="<?= e($base) ?>/painel.php"
                       class="menu-link <?= $paginaAtiva === 'painel' ? 'ativo' : '' ?>">
                        Painel de Indicadores
                    </a>
                    <a href="<?= e($base) ?>/acoes/listar.php"
                       class="menu-link <?= $paginaAtiva === 'acoes' ? 'ativo' : '' ?>">
                        Ações (CRUD)
                    </a>
                    <a href="<?= e($base) ?>/acoes/criar.php"
                       class="menu-link menu-link-destaque <?= $paginaAtiva === 'nova_acao' ? 'ativo' : '' ?>">
                        + Nova Ação
                    </a>
                </nav>

                <div class="usuario-barra">
                    <span class="usuario-nome" title="Usuário autenticado">
                        Olá, <strong><?= e($usuarioAtual['nome']) ?></strong>
                    </span>
                    <a href="<?= e($base) ?>/logout.php" class="btn btn-contorno btn-pequeno">Sair</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <main class="container conteudo-principal">
        <?php if ($flashMensagem !== null): ?>
            <div class="alerta alerta-<?= e($flashMensagem['tipo']) ?>" role="alert">
                <span><?= e($flashMensagem['texto']) ?></span>
            </div>
        <?php endif; ?>
