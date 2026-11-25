<?php
/**
 * acoes/criar.php - Thin Controller (Endpoint API de Criação de Ação)
 *
 * Responsabilidade:
 * Receber os dados de uma nova ação (enviados pelo React como `FormData` quando há upload
 * de evidência, ou como JSON puro quando não há arquivo), validar os campos, salvar o
 * anexo opcional via `UploadEvidenciaService` e persistir o registro via `AcaoRepository`.
 *
 * Fluxo resumido:
 * POST (FormData ou JSON) -> Validação de campos -> UploadEvidenciaService -> AcaoRepository::criar() -> HTTP 201
 *
 * Campos esperados:
 * - id_oferta   (int, obrigatório)
 * - titulo      (string até 150 chars, obrigatório)
 * - descricao   (string, obrigatório)
 * - situacao    ('Planejada' | 'Em andamento' | 'Concluída', obrigatório)
 * - responsavel (string até 100 chars, obrigatório)
 * - prazo       ('AAAA-MM-DD', obrigatório)
 * - anexo       (arquivo opcional via multipart/form-data: PDF, PNG ou JPG até 2 MB)
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/Models/AcaoRepository.php';
require_once dirname(__DIR__) . '/Services/UploadEvidenciaService.php';

// 1. Cabeçalhos CORS/JSON, sessão e autenticação obrigatória
iniciarApi();
exigirLogin();

// 2. Criação de recurso deve ser feita via POST
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($metodo !== 'POST') {
    responderJson([
        'sucesso' => false,
        'erro'    => 'Método não permitido. Use POST para cadastrar uma ação.',
    ], 405);
}

// 3. Proteção contra um detalhe clássico do PHP:
//    Se o usuário enviar um arquivo maior que `post_max_size` do php.ini, o PHP descarta
//    todo o corpo da requisição e deixa $_POST e $_FILES completamente vazios!
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 0 && empty($_POST) && empty($_FILES) && file_get_contents('php://input') === '') {
    responderJson([
        'sucesso' => false,
        'erro'    => 'O volume de dados enviado excedeu o limite permitido pelo servidor (máx. 2 MB para o anexo).',
    ], 413);
}

// 4. Lê os dados enviados (lerDadosRequisicao() funciona tanto para JSON quanto para FormData/$_POST)
$entrada = lerDadosRequisicao();

$anexoSalvo    = null;
$uploadService = new UploadEvidenciaService();

try {
    $pdo      = conectar();
    $acaoRepo = new AcaoRepository($pdo);

    // 5. Valida todos os campos de formulário antes mesmo de tocar no disco (upload)
    $validacao = $acaoRepo->validarEntrada($entrada);
    if (!$validacao['valido']) {
        responderJson([
            'sucesso' => false,
            'erro'    => $validacao['erros'][0], // Primeira mensagem para alerta rápido no React
            'erros'   => $validacao['erros'],    // Lista completa caso o formulário exiba todos
        ], 400);
    }

    // 6. Processa o upload opcional da evidência ($_FILES['anexo'])
    //    Se o usuário não anexou arquivo, $anexoSalvo será null.
    //    Se o arquivo for inválido (ex.: > 2 MB ou .exe/.php), lança InvalidArgumentException.
    if (isset($_FILES['anexo'])) {
        $anexoSalvo = $uploadService->salvar($_FILES['anexo']);
    }

    // 7. Monta o array final respeitando a constraint `ck_anexo_completo` (ambos NULL ou ambos preenchidos)
    $dadosParaSalvar = $validacao['dados'];
    $dadosParaSalvar['anexo_nome_original'] = $anexoSalvo['anexo_nome_original'] ?? null;
    $dadosParaSalvar['anexo_arquivo']       = $anexoSalvo['anexo_arquivo'] ?? null;

    // 8. Insere no PostgreSQL e recupera a ação completa (com oferta_rotulo e tem_anexo via vw_acao)
    $novoId = $acaoRepo->criar($dadosParaSalvar);
    $acao   = $acaoRepo->buscarPorId($novoId);

    if ($acao !== null) {
        $acao['anexo_url']  = $uploadService->obterUrlRelativa($acao['anexo_arquivo']);
        $acao['anexo_tipo'] = $uploadService->classificarTipoPreview($acao['anexo_arquivo']);
    }

    // HTTP 201 Created: código padrão REST indicando que um novo registro foi criado
    responderJson([
        'sucesso'  => true,
        'mensagem' => 'Ação de acompanhamento cadastrada com sucesso.',
        'acao'     => $acao,
    ], 201);
} catch (InvalidArgumentException $e) {
    // Erros de validação do upload (tamanho, formato, MIME falso) -> HTTP 400 Bad Request
    responderJson([
        'sucesso' => false,
        'erro'    => $e->getMessage(),
    ], 400);
} catch (Throwable $e) {
    // Boa prática (compensação): se o arquivo já foi gravado em app/uploads/ mas o INSERT no
    // banco falhou logo depois, apagamos o arquivo para não deixar "lixo órfão" no servidor.
    if ($anexoSalvo !== null && !empty($anexoSalvo['anexo_arquivo'])) {
        $uploadService->remover($anexoSalvo['anexo_arquivo']);
    }

    error_log('Erro em acoes/criar.php: ' . $e->getMessage());
    responderJson([
        'sucesso' => false,
        'erro'    => 'Erro interno ao cadastrar a ação.',
    ], 500);
}
