<?php
/**
 * acoes/editar.php - Thin Controller para edição de uma Ação existente.
 * Reaproveita o mesmo template views/acoes/form.view.php do criar.php.
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

$idAcao = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['id_acao'] ?? 0);
$acaoAtual = ($idAcao > 0) ? $acaoRepo->buscarPorId($idAcao) : null;

if ($acaoAtual === null) {
    definirMensagem('erro', 'Ação de acompanhamento não encontrada.');
    header('Location: listar.php');
    exit;
}

$ofertas             = $indicadorRepo->listarOfertas();
$situacoesPermitidas = ['Planejada', 'Em andamento', 'Concluída'];
$erros               = [];
$modoEdicao          = true;
$acaoForm            = $acaoAtual;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acaoForm['id_oferta']   = (int) ($_POST['id_oferta'] ?? 0);
    $acaoForm['titulo']      = trim((string) ($_POST['titulo'] ?? ''));
    $acaoForm['descricao']   = trim((string) ($_POST['descricao'] ?? ''));
    $acaoForm['situacao']    = (string) ($_POST['situacao'] ?? '');
    $acaoForm['responsavel'] = trim((string) ($_POST['responsavel'] ?? ''));
    $acaoForm['prazo']       = trim((string) ($_POST['prazo'] ?? ''));
    $removerAnexo            = !empty($_POST['remover_anexo']);

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

    if (empty($erros)) {
        $resultadoUpload = $uploadService->processarUpload($_FILES['anexo'] ?? null);

        if (!$resultadoUpload['sucesso']) {
            $erros[] = $resultadoUpload['erro'];
        } else {
            // Caso o usuário tenha enviado novo arquivo, substitui o antigo
            if ($resultadoUpload['enviado']) {
                if (!empty($acaoAtual['anexo_arquivo'])) {
                    $uploadService->removerArquivo($acaoAtual['anexo_arquivo']);
                }
                $acaoForm['anexo_nome_original'] = $resultadoUpload['nome_original'];
                $acaoForm['anexo_arquivo']       = $resultadoUpload['nome_arquivo'];
            } elseif ($removerAnexo) {
                // Caso tenha marcado para remover o anexo existente sem enviar outro
                if (!empty($acaoAtual['anexo_arquivo'])) {
                    $uploadService->removerArquivo($acaoAtual['anexo_arquivo']);
                }
                $acaoForm['anexo_nome_original'] = null;
                $acaoForm['anexo_arquivo']       = null;
            }

            $acaoRepo->atualizar($idAcao, $acaoForm);
            definirMensagem('sucesso', "Ação #{$idAcao} atualizada com sucesso!");
            header('Location: listar.php');
            exit;
        }
    }
}

$tituloPagina = "Editar Ação #{$idAcao}";
$paginaAtiva  = 'acoes';

require __DIR__ . '/../views/acoes/form.view.php';
