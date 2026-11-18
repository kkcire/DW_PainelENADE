<?php
/**
 * acoes/criar.php - Thin Controller (Endpoint API de Criação de Ação)
 *
 * Responsabilidades previstas na arquitetura:
 * - Receber FormData ($_POST + $_FILES['anexo']) enviado pelo React
 * - Chamar UploadEvidenciaService (valida PDF/PNG/JPG até 2 MB)
 * - Persistir via AcaoRepository e retornar resultado em JSON
 */
