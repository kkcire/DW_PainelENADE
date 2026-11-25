<?php
/**
 * acoes/listar.php - Thin Controller (Endpoint API de Listagem de Ações)
 *
 * Responsabilidade:
 * Retornar a lista de Ações de Acompanhamento Pedagógico em JSON, com suporte a filtros
 * opcionais por oferta e por situação, além do resumo de contagem por situação e da lista
 * de ofertas (para popular o filtro <select> no React em uma única requisição).
 *
 * Exemplos de uso pelo React:
 * - GET /acoes/listar.php
 * - GET /acoes/listar.php?id_oferta=3
 * - GET /acoes/listar.php?situacao=Planejada
 * - GET /acoes/listar.php?id_oferta=3&situacao=Em%20andamento
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/Models/AcaoRepository.php';
require_once dirname(__DIR__) . '/Models/IndicadorEnadeRepository.php';
require_once dirname(__DIR__) . '/Services/UploadEvidenciaService.php';

// 1. Cabeçalhos CORS/JSON, sessão PHP e bloqueio de usuários não autenticados
iniciarApi();
exigirLogin();

// 2. Aceita apenas GET
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($metodo !== 'GET') {
    responderJson([
        'sucesso' => false,
        'erro'    => 'Método não permitido. Use GET para listar as ações.',
    ], 405);
}

// 3. Lê e valida o filtro opcional de oferta (?id_oferta=)
$idOferta = null;
if (isset($_GET['id_oferta']) && $_GET['id_oferta'] !== '') {
    $idValidado = filter_var($_GET['id_oferta'], FILTER_VALIDATE_INT);
    if ($idValidado === false || $idValidado <= 0) {
        responderJson([
            'sucesso' => false,
            'erro'    => 'Parâmetro id_oferta inválido.',
        ], 400);
    }
    $idOferta = $idValidado;
}

// 4. Lê e valida o filtro opcional de situação (?situacao=)
$situacao = null;
if (isset($_GET['situacao']) && trim((string) $_GET['situacao']) !== '') {
    $situacao = trim((string) $_GET['situacao']);
    if (!in_array($situacao, AcaoRepository::SITUACOES_VALIDAS, true)) {
        responderJson([
            'sucesso' => false,
            'erro'    => 'Situação inválida. Valores aceitos: ' . implode(', ', AcaoRepository::SITUACOES_VALIDAS) . '.',
        ], 400);
    }
}

// 5. Consulta os repositórios e devolve o payload completo para a tela de listagem
try {
    $pdo           = conectar();
    $acaoRepo      = new AcaoRepository($pdo);
    $indicadorRepo = new IndicadorEnadeRepository($pdo);
    $uploadService = new UploadEvidenciaService();

    $acoes   = $acaoRepo->listar($idOferta, $situacao);
    $resumo  = $acaoRepo->resumirPorSituacao($idOferta);
    $ofertas = $indicadorRepo->listarOfertas();

    // Enriquecemos cada ação com a URL relativa e o tipo do anexo ('pdf' ou 'imagem')
    $acoesEnriquecidas = array_map(function (array $acao) use ($uploadService): array {
        $acao['anexo_url']  = $uploadService->obterUrlRelativa($acao['anexo_arquivo']);
        $acao['anexo_tipo'] = $uploadService->classificarTipoPreview($acao['anexo_arquivo']);
        return $acao;
    }, $acoes);

    responderJson([
        'sucesso' => true,
        'filtros' => [
            'id_oferta'         => $idOferta,
            'situacao'          => $situacao,
            'situacoes_validas' => AcaoRepository::SITUACOES_VALIDAS,
        ],
        'resumo'  => $resumo,
        'ofertas' => $ofertas,
        'acoes'   => $acoesEnriquecidas,
    ]);
} catch (Throwable $e) {
    error_log('Erro em acoes/listar.php: ' . $e->getMessage());
    responderJson([
        'sucesso' => false,
        'erro'    => 'Erro interno ao listar as ações de acompanhamento.',
    ], 500);
}
