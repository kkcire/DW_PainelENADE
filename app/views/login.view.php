<?php
/**
 * views/login.view.php - View pura da tela de login.
 */
require __DIR__ . '/../includes/header.php';
?>

<section class="secao-login">
    <div class="card card-login">
        <div class="card-cabecalho">
            <span class="tag-recorte">Recorte R05 · História e Pedagogia</span>
            <h1>Acesso ao Painel ENADE</h1>
            <p class="texto-suave">
                Entre com suas credenciais para consultar os indicadores e gerenciar ações de acompanhamento.
            </p>
        </div>

        <?php if (!empty($erroLogin)): ?>
            <div class="alerta alerta-erro" role="alert">
                <?= e($erroLogin) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="login.php" class="formulario">
            <div class="campo-grupo">
                <label for="login">Usuário</label>
                <input
                    type="text"
                    id="login"
                    name="login"
                    value="<?= e($loginDigitado) ?>"
                    placeholder="Ex.: admin"
                    required
                    autofocus
                >
            </div>

            <div class="campo-grupo">
                <label for="senha">Senha</label>
                <input
                    type="password"
                    id="senha"
                    name="senha"
                    placeholder="Digite sua senha"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primario btn-bloco">
                Entrar no Painel
            </button>
        </form>

        <div class="dica-credenciais">
            <strong>Credenciais de demonstração:</strong>
            <span>Login: <code>admin</code> | Senha: <code>admin123</code></span>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
