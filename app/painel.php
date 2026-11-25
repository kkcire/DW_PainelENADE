<?php
/**
 * painel.php - Thin Controller (Endpoint API do Painel de Indicadores ENADE)
 *
 * O que é um Thin Controller ("Controlador Magro")?
 * -------------------------------------------------
 * É um arquivo pequeno que NÃO contém SQL nem regras complexas de cálculo.
 * Ele apenas:
 * 1. Configura cabeçalhos CORS/JSON e valida a sessão do usuário logado (`auth.php`).
 * 2. Verifica o método HTTP permitido (somente GET).
 * 3. Lê e valida os parâmetros opcionais da URL (`?id_oferta=X&ano=Y`).
 * 4. Aciona o `PainelEstatisticasService` (que usa os Repositories) e devolve tudo em JSON.
 *
 * Exemplos de uso pelo React:
 * - GET /painel.php                       -> usa a 1ª oferta e o ano mais recente automaticamente
 * - GET /painel.php?id_oferta=2&ano=2021  -> carrega os dados da oferta 2 na edição de 2021
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Models/IndicadorEnadeRepository.php';
require_once __DIR__ . '/Models/AcaoRepository.php';
require_once __DIR__ . '/Services/PainelEstatisticasService.php';

// 1. Configura CORS, Content-Type: application/json e inicia a sessão PHP
iniciarApi();

// 2. Middleware de autenticação: se não houver usuário na sessão, já responde HTTP 401 e encerra
exigirLogin();

// 3. Este endpoint é somente leitura, portanto aceita apenas requisições GET
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($metodo !== 'GET') {
    responderJson([
        'sucesso' => false,
        'erro'    => 'Método não permitido. Use GET para consultar o painel.',
    ], 405);
}

// 4. Lê os filtros opcionais da query string (?id_oferta=&ano=)
//    Se vierem vazios, passamos null para que o Service escolha os valores padrão.
$idOferta = null;
if (isset($_GET['id_oferta']) && $_GET['id_oferta'] !== '') {
    $idValidado = filter_var($_GET['id_oferta'], FILTER_VALIDATE_INT);
    if ($idValidado === false || $idValidado <= 0) {
        responderJson([
            'sucesso' => false,
            'erro'    => 'Parâmetro id_oferta inválido. Informe um número inteiro positivo.',
        ], 400);
    }
    $idOferta = $idValidado;
}

$ano = null;
if (isset($_GET['ano']) && $_GET['ano'] !== '') {
    $anoValidado = filter_var($_GET['ano'], FILTER_VALIDATE_INT);
    if ($anoValidado === false || $anoValidado < 2000 || $anoValidado > 2100) {
        responderJson([
            'sucesso' => false,
            'erro'    => 'Parâmetro ano inválido. Informe um ano entre 2000 e 2100.',
        ], 400);
    }
    $ano = $anoValidado;
}

// 5. Monta as dependências (Conexão PDO -> Repositories -> Service) e retorna o JSON consolidado
try {
    $pdo           = conectar();
    $indicadorRepo = new IndicadorEnadeRepository($pdo);
    $acaoRepo      = new AcaoRepository($pdo);
    $painelService = new PainelEstatisticasService($indicadorRepo, $acaoRepo);

    $dadosPainel = $painelService->montarDadosPainel($idOferta, $ano);

    responderJson([
        'sucesso' => true,
        'dados'   => $dadosPainel,
    ]);
} catch (Throwable $e) {
    // Registra o erro técnico no log do servidor, mas devolve uma mensagem limpa ao navegador
    error_log('Erro em painel.php: ' . $e->getMessage());
    responderJson([
        'sucesso' => false,
        'erro'    => 'Erro interno ao carregar os indicadores do painel.',
    ], 500);
}
