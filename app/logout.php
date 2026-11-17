<?php
/**
 * logout.php - Encerra a sessão do usuário e redireciona para a tela de login.
 */
require_once __DIR__ . '/auth.php';

encerrarSessao();
header('Location: login.php');
exit;
