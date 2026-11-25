<?php
/**
 * acoes/editar.php - Thin Controller (Endpoint API de Edição de Ação)
 *
 * Responsabilidade:
 * Atualizar uma ação existente (dados + gerenciamento opcional do anexo).
 *
 * Por que este endpoint aceita POST (além de PUT)?
 * -------------------------------------------------
 * No PHP, o envio de arquivos (`multipart/form-data` com `$_POST` e `$_FILES`) só é
 * processado nativamente em requisições `POST`. Por isso, quando o React envia `FormData`
 * (especialmente com novo anexo), ele chama `POST /acoes/editar.php?id=X`.
 * Também aceitamos `PUT` caso o cliente envie apenas JSON sem arquivo.
 *
 * Cenários suportados para o anexo na edição:
 * 1. Enviou um novo arquivo em `$_FILES['anexo']`:
 *    -> Salva o novo arquivo, atualiza o banco e, se tudo der certo, remove o arquivo antigo do disco.
 * 2. Enviou `remover_anexo = true` (ou `'1'` / `'true'`) sem novo arquivo:
 *    -> Limpa os campos de anexo no banco e remove o arquivo físico antigo de `app/uploads/`.
 * 3. Não enviou novo arquivo e não pediu para remover:
 *    -> Mantém o anexo que já estava salvo na ação.
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/Models/AcaoRepository.php';
require_once dirname(__DIR__) . '/Services/UploadEvidenciaService.php';

// 1. Cabeçalhos CORS/JSON, sessão e autenticação obrigatória
iniciarApi();
exigirLogin();

// 2. Aceita POST (FormData ou JSON) e PUT (JSON)
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($metodo, ['POST', 'PUT'], true)) {
    responderJson([
        'sucesso' => false,
        'erro'    => 'Método não permitido. Use POST ou PUT para editar uma ação.',
    ], 405);
}

// 3. Proteção contra estouro de `post_max_size` no PHP
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 0 && empty($_POST) && empty($_FILES) && file_get_contents('php://input') === '') {
    responderJson([
        'sucesso' => false,
        'erro'    => 'O volume de dados enviado excedeu o limite permitido pelo servidor (máx. 2 MB para o anexo).',
    ], 413);
}

// 4. Lê os dados enviados no corpo (FormData/$_POST ou JSON) e identifica o ID da ação
$entrada = lerDadosRequisicao();
$idBruto = $_GET['id'] ?? $_GET['id_acao'] ?? $entrada['id_acao'] ?? $entrada['id'] ?? null;
$idAcao  = filter_var($idBruto, FILTER_VALIDATE_INT);

if ($idAcao === false || $idAcao <= 0) {
    responderJson([
        'sucesso' => false,
        'erro'    => 'Informe um ID de ação válido para edição.',
    ], 400);
}

$novoAnexo     = null;
$uploadService = new UploadEvidenciaService();

try {
    $pdo      = conectar();
    $acaoRepo = new AcaoRepository($pdo);

    // 5. Verifica se a ação realmente existe no banco antes de tentar editar
    $acaoAtual = $acaoRepo->buscarPorId($idAcao);
    if ($acaoAtual === null) {
        responderJson([
            'sucesso' => false,
            'erro'    => 'Ação não encontrada.',
        ], 404);
    }

    // 6. Valida os novos valores dos campos do formulário
    $validacao = $acaoRepo->validarEntrada($entrada);
    if (!$validacao['valido']) {
        responderJson([
            'sucesso' => false,
            'erro'    => $validacao['erros'][0],
            'erros'   => $validacao['erros'],
        ], 400);
    }

    // 7. Decide o que fazer com o anexo (manter, substituir ou remover)
    $arquivoAntigo       = $acaoAtual['anexo_arquivo'];
    $nomeOriginalFinal   = $acaoAtual['anexo_nome_original'];
    $arquivoFinal        = $arquivoAntigo;
    $deveApagarAntigo    = false;

    $pediuRemoverAnexo = filter_var($entrada['remover_anexo'] ?? false, FILTER_VALIDATE_BOOLEAN);

    // Se veio um novo arquivo em $_FILES['anexo'], ele tem prioridade e substitui o antigo
    if (isset($_FILES['anexo'])) {
        $novoAnexo = $uploadService->salvar($_FILES['anexo']);
    }

    if ($novoAnexo !== null) {
        // Cenário 1: substituição (ou primeiro upload em uma ação que não tinha anexo)
        $nomeOriginalFinal = $novoAnexo['anexo_nome_original'];
        $arquivoFinal      = $novoAnexo['anexo_arquivo'];
        $deveApagarAntigo  = ($arquivoAntigo !== null);
    } elseif ($pediuRemoverAnexo) {
        // Cenário 2: usuário marcou a opção de remover o anexo atual
        $nomeOriginalFinal = null;
        $arquivoFinal      = null;
        $deveApagarAntigo  = ($arquivoAntigo !== null);
    }
    // Cenário 3: nenhum novo arquivo e não pediu remoção -> mantém $nomeOriginalFinal e $arquivoFinal antigos

    // 8. Persiste a atualização no PostgreSQL
    $dadosAtualizados = $validacao['dados'];
    $dadosAtualizados['anexo_nome_original'] = $nomeOriginalFinal;
    $dadosAtualizados['anexo_arquivo']       = $arquivoFinal;

    $acaoRepo->atualizar($idAcao, $dadosAtualizados);

    // 9. Só apagamos o arquivo antigo DEPOIS que o UPDATE no banco foi confirmado com sucesso!
    //    Assim, se o banco falhasse, não perderíamos o arquivo antigo.
    if ($deveApagarAntigo && $arquivoAntigo !== null) {
        $uploadService->remover($arquivoAntigo);
    }

    // 10. Recarrega a ação atualizada da view `vw_acao` para devolver o estado mais fresco ao React
    $acaoAtualizada = $acaoRepo->buscarPorId($idAcao);
    if ($acaoAtualizada !== null) {
        $acaoAtualizada['anexo_url']  = $uploadService->obterUrlRelativa($acaoAtualizada['anexo_arquivo']);
        $acaoAtualizada['anexo_tipo'] = $uploadService->classificarTipoPreview($acaoAtualizada['anexo_arquivo']);
    }

    responderJson([
        'sucesso'  => true,
        'mensagem' => 'Ação atualizada com sucesso.',
        'acao'     => $acaoAtualizada,
    ]);
} catch (InvalidArgumentException $e) {
    responderJson([
        'sucesso' => false,
        'erro'    => $e->getMessage(),
    ], 400);
} catch (Throwable $e) {
    // Se gravamos um arquivo novo em disco mas o UPDATE no banco falhou, removemos o novo arquivo órfão
    if ($novoAnexo !== null && !empty($novoAnexo['anexo_arquivo'])) {
        $uploadService->remover($novoAnexo['anexo_arquivo']);
    }

    error_log('Erro em acoes/editar.php: ' . $e->getMessage());
    responderJson([
        'sucesso' => false,
        'erro'    => 'Erro interno ao atualizar a ação.',
    ], 500);
}
