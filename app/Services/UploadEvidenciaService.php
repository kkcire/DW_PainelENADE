<?php
/**
 * UploadEvidenciaService.php - Camada Services (SRP - Princípio da Responsabilidade Única)
 *
 * Responsabilidade única desta classe:
 * Gerenciar com segurança o ciclo de vida dos arquivos de evidência (anexos das ações)
 * salvos em `app/uploads/`:
 * 1. Validar se o upload ocorreu sem erros do PHP ($_FILES['anexo']['error']).
 * 2. Validar o tamanho máximo permitido: 2 MB (2 * 1024 * 1024 bytes).
 * 3. Dupla validação de formato:
 *    - Extensão do nome do arquivo (.pdf, .png, .jpg, .jpeg)
 *    - MIME type REAL inspecionando os bytes do arquivo via `finfo_file()`
 *      (impede que alguém renomeie `virus.php` para `comprovante.pdf` e tente enviar).
 * 4. Renomear o arquivo para um identificador seguro e único (`evidencia_YYYYmmdd_HHMMSS_random.ext`),
 *    evitando sobrescritas, espaços/acentos quebrados e ataques de Path Traversal (`../../`).
 * 5. Remover arquivos antigos ou excluídos com segurança (sempre passando por `basename()`).
 */

class UploadEvidenciaService
{
    /**
     * Limite máximo de tamanho por arquivo: 2 megabytes (2.097.152 bytes).
     */
    public const TAMANHO_MAXIMO_BYTES = 2 * 1024 * 1024;

    /**
     * Mapa de MIME types reais aceitos -> lista de extensões válidas para aquele MIME.
     *
     * Por que cruzar MIME x extensão?
     * Para garantir consistência: um arquivo cujo conteúdo real é `application/pdf`
     * precisa ter extensão `.pdf` (e uma imagem JPEG precisa ser `.jpg` ou `.jpeg`).
     */
    private const TIPOS_PERMITIDOS = [
        'application/pdf' => ['pdf'],
        'image/png'       => ['png'],
        'image/jpeg'      => ['jpg', 'jpeg'],
    ];

    /**
     * Caminho absoluto no disco para a pasta `app/uploads`.
     */
    private string $diretorioUploads;

    public function __construct(?string $diretorioUploads = null)
    {
        // Por padrão, resolve automaticamente para `app/uploads` a partir de `app/Services/`
        $this->diretorioUploads = $diretorioUploads ?? dirname(__DIR__) . '/uploads';
    }

    /**
     * Valida e salva o arquivo recebido via $_FILES['anexo'].
     *
     * Retornos possíveis:
     * - null  -> nenhum arquivo foi enviado (como o anexo é opcional, isso NÃO é erro).
     * - array -> ['anexo_nome_original' => string, 'anexo_arquivo' => string]
     *
     * Exceções lançadas:
     * - InvalidArgumentException -> erro de validação do usuário (arquivo grande, tipo proibido, etc.) -> HTTP 400.
     * - RuntimeException         -> erro interno de servidor/disco (pasta sem permissão, etc.) -> HTTP 500.
     *
     * @param array<string, mixed>|null $arquivo Item vindo de $_FILES['anexo']
     * @return array{anexo_nome_original: string, anexo_arquivo: string}|null
     */
    public function salvar(?array $arquivo): ?array
    {
        // 1. Se o campo não veio ou o usuário não escolheu nenhum arquivo, apenas retorna null
        if ($arquivo === null || !isset($arquivo['error'])) {
            return null;
        }

        $codigoErro = (int) $arquivo['error'];
        if ($codigoErro === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        // 2. Verifica erros nativos do PHP durante o envio (ex.: estourou upload_max_filesize no php.ini)
        if ($codigoErro !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException($this->traduzirErroUpload($codigoErro));
        }

        $caminhoTemporario = (string) ($arquivo['tmp_name'] ?? '');
        $tamanhoBytes      = (int) ($arquivo['size'] ?? 0);
        $nomeOriginalBruto = (string) ($arquivo['name'] ?? '');

        // 3. Confirma que o arquivo temporário realmente veio de um upload HTTP válido
        if ($caminhoTemporario === '' || !is_uploaded_file($caminhoTemporario)) {
            throw new InvalidArgumentException('Arquivo de upload inválido ou não recebido corretamente.');
        }

        // 4. Valida tamanho (não pode ser vazio nem ultrapassar 2 MB)
        if ($tamanhoBytes <= 0) {
            throw new InvalidArgumentException('O arquivo enviado está vazio.');
        }

        if ($tamanhoBytes > self::TAMANHO_MAXIMO_BYTES) {
            throw new InvalidArgumentException('O arquivo ultrapassa o limite máximo permitido de 2 MB.');
        }

        // 5. Higieniza o nome original (remove diretórios caso o navegador envie caminho completo)
        $nomeOriginal = basename($nomeOriginalBruto);
        if (strlen($nomeOriginal) > 255) {
            $nomeOriginal = substr($nomeOriginal, 0, 255);
        }

        // Extrai a extensão em minúsculas (ex.: "Relatorio.PDF" -> "pdf")
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

        // 6. Inspeção do MIME type REAL lendo os bytes (magic bytes) do arquivo temporário
        $mimeReal = $this->detectarMimeReal($caminhoTemporario);

        // Verifica se o MIME real está na lista branca (whitelist)
        if (!array_key_exists($mimeReal, self::TIPOS_PERMITIDOS)) {
            throw new InvalidArgumentException(
                'Formato de arquivo não permitido. Envie apenas PDF, PNG ou JPG/JPEG.'
            );
        }

        // Verifica se a extensão do nome bate com o conteúdo real detectado
        $extensoesAceitasParaMime = self::TIPOS_PERMITIDOS[$mimeReal];
        if (!in_array($extensao, $extensoesAceitasParaMime, true)) {
            throw new InvalidArgumentException(
                'A extensão do arquivo não corresponde ao conteúdo real. Envie um PDF, PNG ou JPG válido.'
            );
        }

        // 7. Garante que a pasta app/uploads/ existe e permite escrita
        if (!is_dir($this->diretorioUploads)) {
            if (!mkdir($this->diretorioUploads, 0755, true) && !is_dir($this->diretorioUploads)) {
                throw new RuntimeException('Não foi possível criar a pasta de uploads no servidor.');
            }
        }

        if (!is_writable($this->diretorioUploads)) {
            throw new RuntimeException('A pasta de uploads não possui permissão de escrita.');
        }

        // 8. Gera um nome único e imprevisível: evidencia_20261124_215500_a1b2c3d4.pdf
        //    Isso evita sobrescrever arquivos de outras ações e bloqueia nomes maliciosos.
        $aleatorio      = bin2hex(random_bytes(4));
        $nomeArmazenado = sprintf('evidencia_%s_%s.%s', date('Ymd_His'), $aleatorio, $extensao);
        $destinoFinal   = $this->diretorioUploads . DIRECTORY_SEPARATOR . $nomeArmazenado;

        // 9. Move o arquivo da pasta temporária do PHP para app/uploads/
        if (!move_uploaded_file($caminhoTemporario, $destinoFinal)) {
            throw new RuntimeException('Falha ao salvar o arquivo de evidência no servidor.');
        }

        return [
            'anexo_nome_original' => $nomeOriginal,
            'anexo_arquivo'       => $nomeArmazenado,
        ];
    }

    /**
     * Remove um arquivo físico da pasta `app/uploads/` de forma segura.
     * Usado ao excluir uma ação, ao substituir um anexo ou em caso de rollback se o banco falhar.
     */
    public function remover(?string $nomeArquivo): bool
    {
        if ($nomeArquivo === null || trim($nomeArquivo) === '') {
            return false;
        }

        // basename() garante que apenas o nome do arquivo seja usado (impede Path Traversal como "../config.php")
        $nomeSeguro = basename($nomeArquivo);
        $caminho    = $this->diretorioUploads . DIRECTORY_SEPARATOR . $nomeSeguro;

        if (is_file($caminho)) {
            return unlink($caminho);
        }

        return false;
    }

    /**
     * Monta o caminho relativo público para o React exibir ou baixar o anexo
     * (ex.: "uploads/evidencia_20261124_215500_a1b2c3d4.pdf").
     */
    public function obterUrlRelativa(?string $nomeArquivo): ?string
    {
        if ($nomeArquivo === null || trim($nomeArquivo) === '') {
            return null;
        }

        return 'uploads/' . rawurlencode(basename($nomeArquivo));
    }

    /**
     * Identifica de forma simples se o anexo é 'pdf' ou 'imagem' para facilitar o preview no React
     * (ex.: renderizar <img> para PNG/JPG e link/<iframe> para PDF).
     */
    public function classificarTipoPreview(?string $nomeArquivo): ?string
    {
        if ($nomeArquivo === null || trim($nomeArquivo) === '') {
            return null;
        }

        $ext = strtolower(pathinfo($nomeArquivo, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            return 'pdf';
        }
        if (in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
            return 'imagem';
        }

        return 'outro';
    }

    /**
     * Usa a extensão `fileinfo` do PHP para ler os primeiros bytes do arquivo e descobrir seu MIME real.
     */
    private function detectarMimeReal(string $caminhoTemporario): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new RuntimeException('Extensão fileinfo indisponível no PHP para validar o arquivo.');
        }

        $mime = finfo_file($finfo, $caminhoTemporario);
        // No PHP 8.5+, finfo_close() está obsoleto porque o objeto fecha automaticamente ao sair do escopo.
        unset($finfo);

        return is_string($mime) ? $mime : '';
    }

    /**
     * Converte os códigos numéricos de erro do $_FILES['anexo']['error'] em mensagens amigáveis.
     */
    private function traduzirErroUpload(int $codigoErro): string
    {
        switch ($codigoErro) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'O arquivo enviado ultrapassa o tamanho máximo permitido (2 MB).';
            case UPLOAD_ERR_PARTIAL:
                return 'O envio do arquivo foi interrompido antes de concluir. Tente novamente.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Pasta temporária de upload não configurada no servidor.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Não foi possível gravar o arquivo temporário no disco do servidor.';
            case UPLOAD_ERR_EXTENSION:
                return 'O upload foi bloqueado por uma extensão do servidor.';
            default:
                return 'Erro desconhecido ao processar o envio do arquivo.';
        }
    }
}
