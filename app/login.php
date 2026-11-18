<?php
/**
 * login.php - Thin Controller (Endpoint de Login)
 *
 * - GET:  Verifica se já existe sessão ativa.
 * - POST: Valida login e senha enviados pelo React e inicia a sessão.
 */

require_once __DIR__ . '/auth.php';

iniciarApi();

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
    $usuario = usuarioLogado();
    responderJson([
        'sucesso'     => true,
        'autenticado' => ($usuario !== null),
        'usuario'     => $usuario,
    ]);
}

if ($metodo !== 'POST') {
    responderJson(['sucesso' => false, 'erro' => 'Método não permitido.'], 405);
}

$dados = lerDadosRequisicao();
$login = trim((string) ($dados['login'] ?? ''));
$senha = (string) ($dados['senha'] ?? '');

if ($login === '' || $senha === '') {
    responderJson([
        'sucesso' => false,
        'erro'    => 'Preencha o usuário e a senha.',
    ], 400);
}

$pdo     = conectar();
$usuario = autenticarUsuario($pdo, $login, $senha);

if ($usuario === null) {
    responderJson([
        'sucesso' => false,
        'erro'    => 'Usuário ou senha inválidos.',
    ], 401);
}

responderJson([
    'sucesso' => true,
    'usuario' => $usuario,
]);
