<?php
/**
 * acoes/criar.php - Thin Controller para cadastro de nova Ação.
 * Recebe $_POST + $_FILES -> chama UploadEvidenciaService -> salva via AcaoRepository
 * e compartilha a view views/acoes/form.view.php com o editar.php.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Models/AcaoRepository.php';
require_once __DIR__ . '/../Models/IndicadorEnadeRepository.php';
require_once __DIR__ . '/../Services/UploadEvidenciaService.php';

exigirLogin();

$pdo           = conectar();
$acaoRepo      = new AcaoRepository($pdo);
$indicadorRepo = new IndicadorEnadeRepository($pdo);
$uploadService = new UploadEvidenciaService();

$ofertas             = $indicadorRepo->listarOfertas();
$situacoesPermitidas = ['Planejada', 'Em andamento', 'Concluída'];
$erros               = [];
$modoEdicao          = false;

$acaoForm = [
    'id_acao'             => null,
    'id_oferta'           => isset($_GET['id_oferta']) ? (int) $_GET['id_oferta'] : ($ofertas[0]['id_oferta'] ?? ''),
    'titulo'              => '',
    'descricao'           => '',
    'situacao'            => 'Planejada',
    'responsavel'         => '',
    'prazo'               => date('Y-m-d', strtotime('+30 days')),
    'anexo_nome_original' => null,
    'anexo_arquivo'       => null,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acaoForm['id_oferta']   = (int) ($_POST['id_oferta'] ?? 0);
    $acaoForm['titulo']      = trim((string) ($_POST['titulo'] ?? ''));
    $acaoForm['descricao']   = trim((string) ($_POST['descricao'] ?? ''));
    $acaoForm['situacao']    = (string) ($_POST['situacao'] ?? '');
    $acaoForm['responsavel'] = trim((string) ($_POST['responsavel'] ?? ''));
    $acaoForm['prazo']       = trim((string) ($_POST['prazo'] ?? ''));

    // Validações de campos obrigatórios
    if ($acaoForm['id_oferta'] <= 0) {
        $erros[] = 'Selecione uma oferta de curso válida.';
    }
    if ($acaoForm['titulo'] === '' || strlen($acaoForm['titulo']) > 150) {
        $erros[] = 'Informe um título para a ação (máximo de 150 caracteres).';
    }
    if ($acaoForm['descricao'] === '') {
        $erros[] = 'Informe a descrição detalhada da ação.';
    }
    if (!in_array($acaoForm['situacao'], $situacoesPermitidas, true)) {
        $erros[] = 'Selecione uma situação válida (Planejada, Em andamento ou Concluída).';
    }
    if ($acaoForm['responsavel'] === '' || strlen($acaoForm['responsavel']) > 100) {
        $erros[] = 'Informe o nome do responsável (máximo de 100 caracteres).';
    }
    if ($acaoForm['prazo'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $acaoForm['prazo'])) {
        $erros[] = 'Informe uma data de prazo válida.';
    }

    // Se campos básicos estiverem ok, processa o upload opcional da evidência
    if (empty($erros)) {
        $resultadoUpload = $uploadService->processarUpload($_FILES['anexo'] ?? null);

        if (!$resultadoUpload['sucesso']) {
            $erros[] = $resultadoUpload['erro'];
        } else {
            if ($resultadoUpload['enviado']) {
                $acaoForm['anexo_nome_original'] = $resultadoUpload['nome_original'];
                $acaoForm['anexo_arquivo']       = $resultadoUpload['nome_arquivo'];
            }

            $novoId = $acaoRepo->criar($acaoForm);
            definirMensagem('sucesso', "Ação #{$novoId} cadastrada com sucesso!");
            header('Location: listar.php');
            exit;
        }
    }
}

$tituloPagina = 'Nova Ação de Acompanhamento';
$paginaAtiva  = 'nova_acao';

require __DIR__ . '/../views/acoes/form.view.php';
