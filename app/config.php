<?php
/**
 * config.php - conexão com o PostgreSQL via PDO.
 *
 * Uso nas outras páginas:
 *     require_once __DIR__ . '/config.php';   // ajuste o caminho conforme a pasta
 *     $pdo = conectar();
 *
 * Os valores abaixo são o padrão do projeto (ver README, seção "Banco de dados").
 * A senha aqui é apenas um EXEMPLO: cada pessoa define a sua em app/config.local.php,
 * arquivo que não vai para o Git nem para o ZIP.
 *
 * config.local.php:
 *     <?php
 *     return ['senha' => 'seunome_senha_banco'];
 */

function conectar(): PDO
{
    static $pdo = null;               // uma única conexão por requisição
    if ($pdo !== null) {
        return $pdo;
    }

    $cfg = [
        'host'    => 'localhost',
        'porta'   => '5432',
        'banco'   => 'dw_projeto_enade',
        'usuario' => 'postgres',
        'senha'   => 'SUA_SENHA_AQUI',
    ];

    $local = __DIR__ . '/config.local.php';
    if (is_file($local)) {
        $cfg = array_merge($cfg, require $local);
    }

    try {
        $pdo = new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', $cfg['host'], $cfg['porta'], $cfg['banco']),
            $cfg['usuario'],
            $cfg['senha'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (PDOException $e) {
        error_log('Falha na conexão: ' . $e->getMessage());
        die('Não foi possível conectar ao banco de dados. '
          . 'Verifique as configurações em app/config.php e o README.');
    }

    return $pdo;
}