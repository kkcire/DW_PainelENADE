<?php
/**
 * footer.php - Rodapé padrão e carregamento do script de validação.
 */
$base = function_exists('caminhoBaseApp') ? caminhoBaseApp() : '.';
?>
    </main>

    <footer class="rodape-sistema">
        <div class="container rodape-conteudo">
            <p>
                <strong>Painel ENADE · Recorte R05 (História e Pedagogia)</strong> —
                Projeto de Desenvolvimento Web (PHP + PDO + PostgreSQL).
            </p>
            <p class="rodape-nota">
                Base didática híbrida: registros marcados como <em>Simulado Didático</em> são artificiais para fins de estudo e não representam resultados oficiais do Inep.
            </p>
        </div>
    </footer>

    <script src="<?= e($base) ?>/assets/js/validacao.js"></script>
</body>
</html>
