<?php
/**
 * acoes/listar.php - Thin Controller de listagem e filtro de Ações de Acompanhamento.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Models/AcaoRepository.php';
require_once __DIR__ . '/../Models/IndicadorEnadeRepository.php';

exigirLogin();

$pdo           = conectar();
$acaoRepo      = new AcaoRepository($pdo);
$indicadorRepo = new IndicadorEnadeRepository($pdo);

$idOfertaFiltro = isset($_GET['id_oferta']) && $_GET['id_oferta'] !== ''
    ? (int) $_GET['id_oferta']
    : null;

$situacoesPermitidas = ['Planejada', 'Em andamento', 'Concluída'];
$situacaoFiltro      = isset($_GET['situacao']) && in_array($_GET['situacao'], $situacoesPermitidas, true)
    ? $_GET['situacao']
    : null;

$ofertas     = $indicadorRepo->listarOfertas();
$acoes       = $acaoRepo->listar($idOfertaFiltro, $situacaoFiltro);
$resumoAcoes = $acaoRepo->contarPorSituacao($idOfertaFiltro);

$tituloPagina = 'Ações de Acompanhamento';
$paginaAtiva  = 'acoes';

require __DIR__ . '/../views/acoes/lista.view.php';
