/**
 * services/api.js
 * Centraliza as chamadas HTTP para o backend PHP (app/).
 *
 * No XAMPP (Windows), a URL padrão costuma ser:
 *   http://localhost/DW_Grupo06_PainelENADE/app
 * No servidor embutido do PHP (php -S localhost:8000 -t app):
 *   http://localhost:8000
 */

export const API_BASE_URL =
  import.meta.env.VITE_API_URL || 'http://localhost/DW_Grupo06_PainelENADE/app';

/**
 * Função auxiliar padrão para requisições JSON com suporte a sessão PHP (cookies).
 */
export async function requisicaoApi(endpoint, opcoes = {}) {
  const resposta = await fetch(`${API_BASE_URL}/${endpoint}`, {
    credentials: 'include', // envia o cookie PHPSESSID automaticamente
    headers: {
      'Content-Type': 'application/json',
      ...(opcoes.headers || {}),
    },
    ...opcoes,
  });

  const dados = await resposta.json();

  if (!resposta.ok || dados.sucesso === false) {
    throw new Error(dados.erro || 'Erro ao comunicar com o servidor.');
  }

  return dados;
}

export function verificarSessao() {
  return requisicaoApi('login.php', { method: 'GET' });
}

export function fazerLogin(login, senha) {
  return requisicaoApi('login.php', {
    method: 'POST',
    body: JSON.stringify({ login, senha }),
  });
}

export function fazerLogout() {
  return requisicaoApi('logout.php', { method: 'POST' });
}
