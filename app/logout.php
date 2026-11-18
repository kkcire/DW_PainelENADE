<?php
/**
 * logout.php - Thin Controller (Endpoint de Logout)
 */

require_once __DIR__ . '/auth.php';

iniciarApi();
encerrarSessao();

responderJson([
    'sucesso'  => true,
    'mensagem' => 'Sessão encerrada com sucesso.',
]);
