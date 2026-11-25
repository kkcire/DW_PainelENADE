<?php
/**
 * acoes/visualizar.php - Thin Controller (Endpoint API de Detalhes de uma Ação)
 *
 * Responsabilidade:
 * Buscar uma ação específica pelo seu ID (`?id=X` ou `?id_acao=X`) e devolver todos os
 * detalhes em JSON, incluindo os metadados e o caminho público do anexo (se houver)
 * para visualização ou download na tela `VisualizarAcaoPage.jsx`.
 *
 * Exemplo de uso pelo React:
 * - GET /acoes/visualizar.php?id=1
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/Models/AcaoRepository.php';
require_once dirname(__DIR__) . '/Services/UploadEvidenciaService.php';

// 1. Cabeçalhos CORS/JSON, sessão e autenticação obrigatória
iniciarApi();
exigirLogin();

// 2. Aceita apenas GET
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($metodo !== 'GET') {
    responderJson([
        'sucesso' => false,
        'erro'    => 'Método não permitido. Use GET para visualizar uma ação.',
    ], 405);
}

// 3. Lê e valida o ID da ação na URL (aceita tanto ?id= quanto ?id_acao= por praticidade)
$idBruto = $_GET['id'] ?? $_GET['id_acao'] ?? null;
$idAcao  = filter_var($idBruto, FILTER_VALIDATE_INT);

if ($idAcao === false || $idAcao <= 0) {
    responderJson([
        'sucesso' => false,
        'erro'    => 'Informe um ID de ação válido na URL (ex.: ?id=1).',
    ], 400);
}

// 4. Busca no repositório e devolve 404 caso o registro não exista
try {
    $pdo           = conectar();
    $acaoRepo      = new AcaoRepository($pdo);
    $uploadService = new UploadEvidenciaService();

    $acao = $acaoRepo->buscarPorId($idAcao);

    if ($acao === null) {
        responderJson([
            'sucesso' => false,
            'erro'    => 'Ação não encontrada.',
        ], 404);
    }

    // Adiciona campos auxiliares para o React montar o link de download ou preview da evidência
    $acao['anexo_url']  = $uploadService->obterUrlRelativa($acao['anexo_arquivo']);
    $acao['anexo_tipo'] = $uploadService->classificarTipoPreview($acao['anexo_arquivo']);

    responderJson([
        'sucesso' => true,
        'acao'    => $acao,
    ]);
} catch (Throwable $e) {
    error_log('Erro em acoes/visualizar.php: ' . $e->getMessage());
    responderJson([
        'sucesso' => false,
        'erro'    => 'Erro interno ao buscar os detalhes da ação.',
    ], 500);
}
