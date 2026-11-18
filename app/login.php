<?php
/**
 * login.php - Thin Controller de autenticação.
 * Verifica credenciais via auth.php e carrega a View de login.
 */
require_once __DIR__ . '/auth.php';

if (usuarioLogado() !== null) {
    header('Location: painel.php');
    exit;
}

$erroLogin     = null;
$loginDigitado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginDigitado = trim((string) ($_POST['login'] ?? ''));
    $senhaDigitada = (string) ($_POST['senha'] ?? '');

    if ($loginDigitado === '' || $senhaDigitada === '') {
        $erroLogin = 'Informe o usuário e a senha para entrar.';
    } else {
        $pdo = conectar();
        if (autenticarUsuario($pdo, $loginDigitado, $senhaDigitada)) {
            definirMensagem('sucesso', 'Bem-vindo(a) ao Painel ENADE (Recorte R05)!');
            header('Location: painel.php');
            exit;
        }
        $erroLogin = 'Usuário ou senha inválidos. (Demonstração: admin / admin123)';
    }
}

$tituloPagina = 'Acesso ao Sistema';
require __DIR__ . '/views/login.view.php';
