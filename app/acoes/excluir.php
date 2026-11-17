<?php
/**
 * acoes/excluir.php - Thin Controller para exclusão de uma Ação e de seu anexo físico.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Models/AcaoRepository.php';
require_once __DIR__ . '/../Services/UploadEvidenciaService.php';

exigirLogin();

$idAcao = isset($_POST['id']) ? (int) $_POST['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);

if ($idAcao <= 0) {
    definirMensagem('erro', 'ID da ação inválido para exclusão.');
    header('Location: listar.php');
    exit;
}

$pdo           = conectar();
$acaoRepo      = new AcaoRepository($pdo);
$uploadService = new UploadEvidenciaService();

$acao = $acaoRepo->buscarPorId($idAcao);
if ($acao === null) {
    definirMensagem('erro', 'Ação não encontrada.');
    header('Location: listar.php');
    exit;
}

// Remove arquivo físico da pasta uploads/, se existir
if (!empty($acao['anexo_arquivo'])) {
    $uploadService->removerArquivo($acao['anexo_arquivo']);
}

$acaoRepo->excluir($idAcao);
definirMensagem('sucesso', "Ação #{$idAcao} excluída com sucesso.");
header('Location: listar.php');
exit;
