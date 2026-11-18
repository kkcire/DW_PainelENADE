<?php
/**
 * auth.php - Middleware simples de sessão, CORS e helpers de API JSON.
 */

require_once __DIR__ . '/config.php';

/**
 * Configura cabeçalhos padrão de API JSON e CORS (permitindo sessão/cookies do React)
 * e inicia a sessão PHP.
 */
function iniciarApi(): void
{
    $origem = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:5173';
    header("Access-Control-Allow-Origin: {$origem}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
    header('Content-Type: application/json; charset=utf-8');

    // Responde imediatamente às requisições de preflight (OPTIONS) do navegador
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Envia uma resposta padronizada em JSON e encerra o script.
 */
function responderJson(array $dados, int $statusHttp = 200): void
{
    http_response_code($statusHttp);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Lê os dados enviados pelo React (suporta tanto JSON puro no body quanto $_POST).
 */
function lerDadosRequisicao(): array
{
    $conteudoBruto = file_get_contents('php://input');
    if (!empty($conteudoBruto)) {
        $json = json_decode($conteudoBruto, true);
        if (is_array($json)) {
            return $json;
        }
    }
    return $_POST;
}

/**
 * Retorna o usuário salvo na sessão ou null se não estiver logado.
 */
function usuarioLogado(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

/**
 * Middleware: bloqueia o acesso caso o usuário não esteja logado na sessão.
 */
function exigirLogin(): array
{
    $usuario = usuarioLogado();
    if ($usuario === null) {
        responderJson([
            'sucesso' => false,
            'erro'    => 'Acesso não autorizado. Faça login para continuar.',
        ], 401);
    }
    return $usuario;
}

/**
 * Verifica login e senha no banco (tabela `usuario` com hash bcrypt).
 */
function autenticarUsuario(PDO $pdo, string $login, string $senha): ?array
{
    $sql = 'SELECT id_usuario, nome, login, senha FROM usuario WHERE login = :login LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':login' => trim($login)]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($senha, $usuario['senha'])) {
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id_usuario' => (int) $usuario['id_usuario'],
            'nome'       => $usuario['nome'],
            'login'      => $usuario['login'],
        ];
        return $_SESSION['usuario'];
    }

    return null;
}

/**
 * Destrói a sessão ativa do usuário.
 */
function encerrarSessao(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
