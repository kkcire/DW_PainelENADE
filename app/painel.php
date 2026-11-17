<?php
/**
 * painel.php - Thin Controller do Painel Principal de Indicadores ENADE.
 * Instancia os repositórios, chama PainelEstatisticasService e carrega a View.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Models/IndicadorEnadeRepository.php';
require_once __DIR__ . '/Models/AcaoRepository.php';
require_once __DIR__ . '/Services/PainelEstatisticasService.php';

exigirLogin();

$pdo           = conectar();
$indicadorRepo = new IndicadorEnadeRepository($pdo);
$acaoRepo      = new AcaoRepository($pdo);
$painelService = new PainelEstatisticasService($indicadorRepo, $acaoRepo);

$idOfertaFiltro = isset($_GET['id_oferta']) && $_GET['id_oferta'] !== ''
    ? (int) $_GET['id_oferta']
    : null;

$anoFiltro = isset($_GET['ano']) && $_GET['ano'] !== ''
    ? (int) $_GET['ano']
    : null;

$dadosPainel  = $painelService->montarDadosPainel($idOfertaFiltro, $anoFiltro);
$tituloPagina = 'Painel de Indicadores';
$paginaAtiva  = 'painel';

require __DIR__ . '/views/painel.view.php';
