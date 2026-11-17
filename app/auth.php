<?php
/**
 * =====================================================================
 * auth.php - Middleware de Sessão, Autenticação e Helpers Globais
 * =====================================================================
 *
 * Responsabilidades deste arquivo:
 * 1. Garantir que a sessão PHP (session_start) esteja ativa.
 * 2. Carregar a conexão PDO (config.php).
 * 3. Prover funções de autenticação (login com bcrypt, verificação de
 *    usuário logado, proteção de rotas restritas e logout seguro).
 * 4. Prover helpers globais para as Views e Controllers:
 *    - caminhoBaseApp(): resolve caminhos relativos entre app/ e app/acoes/
 *    - e(): escapa strings contra XSS (Cross-Site Scripting)
 *    - definirMensagem() / obterMensagem(): mensagens flash na sessão
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

/**
 * Descobre o caminho base relativo até a pasta raiz `app/` ("." ou "..").
 * Permite que o mesmo header.php e footer.php funcione tanto quando
 * incluído por arquivos em `app/painel.php` quanto em `app/acoes/listar.php`.
 *
 * @return string "." se estiver na raiz de app/, ou ".." se estiver em app/acoes/
 */
function caminhoBaseApp(): string
{
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if (str_ends_with($scriptDir, '/acoes')) {
        return '..';
    }
    return '.';
}

/**
 * Escapa qualquer valor para exibição segura no HTML (prevenção contra XSS).
 * Deve ser utilizado em todas as variáveis impressas nas views: <?= e($var) ?>
 *
 * @param mixed $valor Valor a ser convertido e escapado.
 * @return string Texto seguro com caracteres especiais convertidos em entidades HTML.
 */
function e(mixed $valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Retorna os dados do usuário atualmente autenticado na sessão.
 *
 * @return array{id_usuario: int, nome: string, login: string}|null
 *         Array com dados do usuário ou null caso seja visitante anônimo.
 */
function usuarioLogado(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

/**
 * Middleware de proteção de rota: exige que o usuário esteja autenticado.
 * Caso não haja sessão ativa de usuário, redireciona imediatamente para `login.php`
 * e interrompe a execução do script.
 *
 * @return void
 */
function exigirLogin(): void
{
    if (usuarioLogado() === null) {
        $base = caminhoBaseApp();
        header("Location: {$base}/login.php");
        exit;
    }
}

/**
 * Valida as credenciais informadas contra a tabela `usuario` no PostgreSQL.
 * A comparação de senha utiliza `password_verify()` para conferir o hash bcrypt.
 * Em caso de sucesso, regenera o ID da sessão (evita Session Fixation) e
 * armazena os dados básicos do usuário em `$_SESSION['usuario']`.
 *
 * @param PDO    $pdo   Instância ativa da conexão PDO.
 * @param string $login Login informado no formulário (ex.: 'admin').
 * @param string $senha Senha em texto puro digitada no formulário (ex.: 'admin123').
 * @return bool         True se as credenciais forem válidas; False caso contrário.
 */
function autenticarUsuario(PDO $pdo, string $login, string $senha): bool
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
        return true;
    }

    return false;
}

/**
 * Encerra a sessão do usuário de forma completa:
 * limpa o array `$_SESSION`, expira o cookie de sessão no navegador
 * e destrói os dados da sessão no servidor.
 *
 * @return void
 */
function encerrarSessao(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Registra uma mensagem temporária ("Flash Message") na sessão para ser
 * exibida no topo da próxima página carregada (padrão Post-Redirect-Get).
 *
 * @param string $tipo  Tipo visual do alerta: 'sucesso' | 'erro' | 'aviso'.
 * @param string $texto Texto da mensagem a ser exibida ao usuário.
 * @return void
 */
function definirMensagem(string $tipo, string $texto): void
{
    $_SESSION['flash_mensagem'] = [
        'tipo'  => $tipo,
        'texto' => $texto,
    ];
}

/**
 * Recupera a mensagem flash armazenada na sessão e a remove imediatamente,
 * garantindo que o alerta apareça apenas uma única vez.
 *
 * @return array{tipo: string, texto: string}|null Dados da mensagem ou null se não houver.
 */
function obterMensagem(): ?array
{
    if (!isset($_SESSION['flash_mensagem'])) {
        return null;
    }
    $msg = $_SESSION['flash_mensagem'];
    unset($_SESSION['flash_mensagem']);
    return $msg;
}
