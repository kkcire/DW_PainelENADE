<?php
/**
 * =====================================================================
 * UploadEvidenciaService.php - Serviço de Upload de Evidências (SRP)
 * =====================================================================
 *
 * Camada Services (Single Responsibility Principle):
 * Centraliza todas as regras de validação, armazenamento e exclusão física
 * de arquivos comprobatórios anexados às Ações de Acompanhamento:
 *
 * 1. Tamanho máximo: 2 MB (2 * 1024 * 1024 bytes).
 * 2. Extensões permitidas: .pdf, .png, .jpg, .jpeg.
 * 3. Verificação de conteúdo real (MIME type via finfo_file):
 *    application/pdf, image/png, image/jpeg.
 * 4. Renomeação segura: gera identificador único (evidencia_DATA_HASH.ext)
 *    para impedir sobrescrita de arquivos e ataques de Path Traversal.
 */

class UploadEvidenciaService
{
    /** Limite máximo de tamanho de arquivo em bytes (2 MB). */
    public const TAMANHO_MAXIMO_BYTES = 2 * 1024 * 1024;

    /** Lista de extensões de arquivo aceitas. */
    public const EXTENSOES_PERMITIDAS = ['pdf', 'png', 'jpg', 'jpeg'];

    /** Lista de MIME types reais aceitos na inspeção binária do arquivo. */
    public const MIMES_PERMITIDOS = [
        'application/pdf',
        'image/png',
        'image/jpeg',
    ];

    /** Caminho absoluto para o diretório `app/uploads/`. */
    private string $diretorioDestino;

    /**
     * @param string|null $diretorioDestino Caminho customizado ou null para usar `app/uploads`.
     */
    public function __construct(?string $diretorioDestino = null)
    {
        $this->diretorioDestino = $diretorioDestino ?? dirname(__DIR__) . '/uploads';
    }

    /**
     * Valida e armazena um arquivo recebido via `$_FILES['anexo']`.
     * Caso nenhum arquivo tenha sido selecionado pelo usuário (`UPLOAD_ERR_NO_FILE`),
     * retorna `sucesso = true` e `enviado = false` (pois o anexo é opcional).
     *
     * @param array<string, mixed>|null $arquivo Elemento de `$_FILES['anexo']`.
     * @return array{
     *   sucesso: bool,
     *   enviado: bool,
     *   nome_original: string|null,
     *   nome_arquivo: string|null,
     *   erro: string|null
     * }
     */
    public function processarUpload(?array $arquivo): array
    {
        // Anexo é opcional: se não enviou arquivo, retorna sucesso sem arquivo
        if (empty($arquivo) || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [
                'sucesso'       => true,
                'enviado'       => false,
                'nome_original' => null,
                'nome_arquivo'  => null,
                'erro'          => null,
            ];
        }

        // Verifica códigos de erro nativos do PHP no upload HTTP
        $codigoErro = $arquivo['error'] ?? UPLOAD_ERR_OK;
        if ($codigoErro !== UPLOAD_ERR_OK) {
            $mensagem = match ($codigoErro) {
                UPLOAD_ERR_INI_SIZE,
                UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo permitido de 2 MB.',
                UPLOAD_ERR_PARTIAL   => 'O envio do arquivo foi interrompido. Tente novamente.',
                default              => 'Falha ao enviar o arquivo (código ' . $codigoErro . ').',
            };
            return $this->falha($mensagem);
        }

        // Valida tamanho em bytes (maior que zero e no máximo 2 MB)
        $tamanho = (int) ($arquivo['size'] ?? 0);
        if ($tamanho <= 0) {
            return $this->falha('O arquivo enviado está vazio.');
        }

        if ($tamanho > self::TAMANHO_MAXIMO_BYTES) {
            return $this->falha('O arquivo excede o limite máximo de 2 MB.');
        }

        // Extrai e valida a extensão do nome original
        $nomeOriginal = basename((string) ($arquivo['name'] ?? ''));
        $extensao     = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

        if (!in_array($extensao, self::EXTENSOES_PERMITIDAS, true)) {
            return $this->falha('Formato inválido. Envie apenas arquivos PDF, PNG ou JPG.');
        }

        $caminhoTemp = (string) ($arquivo['tmp_name'] ?? '');
        if ($caminhoTemp === '' || !is_uploaded_file($caminhoTemp)) {
            return $this->falha('Arquivo temporário de upload inválido.');
        }

        // Valida o MIME real inspecionando os bytes do arquivo (evita .php renomeado para .jpg)
        if (function_exists('finfo_open')) {
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeReal = $finfo ? finfo_file($finfo, $caminhoTemp) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
            if (!in_array($mimeReal, self::MIMES_PERMITIDOS, true)) {
                return $this->falha('Conteúdo do arquivo não corresponde a um PDF, PNG ou JPG válido.');
            }
        }

        if (!is_dir($this->diretorioDestino)) {
            mkdir($this->diretorioDestino, 0775, true);
        }

        // Gera nome único para armazenamento em disco
        $nomeArmazenado = sprintf(
            'evidencia_%s_%s.%s',
            date('Ymd_His'),
            bin2hex(random_bytes(4)),
            $extensao
        );

        $destinoFinal = rtrim($this->diretorioDestino, '/\\') . DIRECTORY_SEPARATOR . $nomeArmazenado;

        if (!move_uploaded_file($caminhoTemp, $destinoFinal)) {
            return $this->falha('Não foi possível salvar o arquivo na pasta de uploads.');
        }

        return [
            'sucesso'       => true,
            'enviado'       => true,
            'nome_original' => substr($nomeOriginal, 0, 255),
            'nome_arquivo'  => $nomeArmazenado,
            'erro'          => null,
        ];
    }

    /**
     * Remove um arquivo físico de evidência da pasta `app/uploads/` quando uma ação
     * é excluída ou quando o usuário substitui/remove o anexo na edição.
     * Usa `basename()` para impedir qualquer tentativa de apagar arquivos fora de `uploads/`
     * e protege explicitamente o arquivo `.htaccess`.
     *
     * @param string|null $nomeArquivo Nome do arquivo armazenado (`anexo_arquivo`).
     * @return void
     */
    public function removerArquivo(?string $nomeArquivo): void
    {
        if (empty($nomeArquivo)) {
            return;
        }

        $nomeSeguro = basename($nomeArquivo);
        if ($nomeSeguro === '.htaccess') {
            return;
        }

        $caminho = rtrim($this->diretorioDestino, '/\\') . DIRECTORY_SEPARATOR . $nomeSeguro;
        if (is_file($caminho)) {
            @unlink($caminho);
        }
    }

    /**
     * Monta estrutura padronizada de retorno de erro.
     *
     * @param string $mensagem Mensagem explicativa do erro de validação.
     * @return array{sucesso: false, enviado: false, nome_original: null, nome_arquivo: null, erro: string}
     */
    private function falha(string $mensagem): array
    {
        return [
            'sucesso'       => false,
            'enviado'       => false,
            'nome_original' => null,
            'nome_arquivo'  => null,
            'erro'          => $mensagem,
        ];
    }
}
