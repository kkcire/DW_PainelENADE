<?php
require_once __DIR__ . '/config.php';
$pdo = conectar();

echo $pdo->query('SELECT count(*) FROM vw_resultado')->fetchColumn();  // 33

$st = $pdo->prepare('SELECT senha FROM usuario WHERE login = ?');
$st->execute(['admin']);
echo ' | ';
var_dump(password_verify('admin123', $st->fetchColumn()));             // bool(true)