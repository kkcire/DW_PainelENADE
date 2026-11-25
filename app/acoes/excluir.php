<?php
/**
 * acoes/excluir.php - Thin Controller (Endpoint API de Exclusão de Ação)
 *
 * Responsabilidade:
 * Excluir definitivamente uma ação de acompanhamento do banco de dados e, caso ela possua
 * um arquivo de evidência anexado, remover também o arquivo físico de `app/uploads/`.
 *
 * Ordem de segurança das operações:
 * 1. Busca a ação para confirmar que existe e guardar o nome do arquivo (`anexo_arquivo`).
 * 2. Exclui o registro no PostgreSQL via `AcaoRepository::excluir()`.
 * 3. Só depois remove o arquivo físico via `UploadEvidenciaService::remover()`.
 *    (Se o passo 2 falhar por qualquer motivo de banco, o arquivo físico continua intacto!)
 *
 * Exemplos de uso pelo React:
 * - DELETE /acoes/excluir.php?id=1
 * - POST   /acoes/excluir.php   com body JSON: { "id_acao": 1 }
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/Models/AcaoRepository.php';
require_once dirname(__DIR__) . '/Services/UploadEvidenciaService.php';

// 1. Cabeçalhos CORS/JSON, sessão e autenticação obrigatória
iniciarApi();
exigirLogin();

// 2. Aceita tanto DELETE (padrão REST) quanto POST (comum em formulários/clientes simples)
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($metodo, ['POST', 'DELETE'], true)) {
    responderJson([
        'sucesso' => false,
        'erro'    => 'Método não permitido. Use DELETE ou POST para excluir uma ação.',
    ], 405);
}

// 3. Aceita o ID via query string (?id= ou ?id_acao=) ou no corpo da requisição (JSON/$_POST)
$entrada = lerDadosRequisicao();
$idBruto = $_GET['id'] ?? $_GET['id_acao'] ?? $entrada['id_acao'] ?? $entrada['id'] ?? null;
$idAcao  = filter_var($idBruto, FILTER_VALIDATE_INT);

if ($idAcao === false || $idAcao <= 0) {
    responderJson([
        'sucesso' => false,
        'erro'    => 'Informe um ID de ação válido para exclusão.',
    ], 400);
}

// 4. Busca, exclui no banco e remove o arquivo físico de evidência (se existir)
try {
    $pdo           = conectar();
    $acaoRepo      = new AcaoRepository($pdo);
    $uploadService = new UploadEvidenciaService();

    // Primeiro verifica se a ação existe e captura o nome do anexo antes de apagar a linha
    $acao = $acaoRepo->buscarPorId($idAcao);
    if ($acao === null) {
        responderJson([
            'sucesso' => false,
            'erro'    => 'Ação não encontrada.',
        ], 404);
    }

    // Exclui o registro no PostgreSQL
    $acaoRepo->excluir($idAcao);

    // Remove o arquivo físico em app/uploads/ (se a ação tinha anexo)
    $anexoRemovido = false;
    if (!empty($acao['anexo_arquivo'])) {
        $anexoRemovido = $uploadService->remover($acao['anexo_arquivo']);
    }

    responderJson([
        'sucesso'        => true,
        'mensagem'       => 'Ação excluída com sucesso.',
        'id_acao'        => $idAcao,
        'anexo_removido' => $anexoRemovido,
    ]);
} catch (Throwable $e) {
    error_log('Erro em acoes/excluir.php: ' . $e->getMessage());
    responderJson([
        'sucesso' => false,
        'erro'    => 'Erro interno ao excluir a ação.',
    ], 500);
}
