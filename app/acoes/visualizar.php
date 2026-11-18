<?php
/**
 * acoes/visualizar.php - Thin Controller para exibir detalhes de uma Ação e sua evidência.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Models/AcaoRepository.php';

exigirLogin();

$pdo      = conectar();
$acaoRepo = new AcaoRepository($pdo);

$idAcao = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$acao   = ($idAcao > 0) ? $acaoRepo->buscarPorId($idAcao) : null;

if ($acao === null) {
    definirMensagem('erro', 'Ação de acompanhamento não encontrada.');
    header('Location: listar.php');
    exit;
}

$tituloPagina = "Detalhes da Ação #{$idAcao}";
$paginaAtiva  = 'acoes';

require __DIR__ . '/../views/acoes/visualizar.view.php';
