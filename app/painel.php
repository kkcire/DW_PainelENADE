<?php
/**
 * painel.php - Thin Controller (Endpoint API do Painel de Indicadores)
 *
 * Responsabilidades previstas na arquitetura:
 * - Validar sessão (auth.php)
 * - Ler filtros opcionais (?id_oferta=&ano=)
 * - Chamar PainelEstatisticasService e retornar JSON para o React (PainelPage.jsx)
 */
