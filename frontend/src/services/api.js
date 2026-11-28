
/**
 * services/api.js
 * Centraliza as chamadas HTTP para o backend PHP.
 */

export const API_BASE_URL =
  import.meta.env.VITE_API_URL ||
  'http://localhost/DW_PainelENADE-main/app';

/**
 * Função auxiliar para comunicação com a API.
 * Aceita tanto JSON quanto FormData.
 */
export async function requisicaoApi(endpoint, opcoes = {}) {
  const ehFormData = opcoes.body instanceof FormData;

  const headers = {
    Accept: 'application/json',
    ...(opcoes.body && !ehFormData
      ? { 'Content-Type': 'application/json' }
      : {}),
    ...(opcoes.headers || {}),
  };

  const resposta = await fetch(`${API_BASE_URL}/${endpoint}`, {
    ...opcoes,
    credentials: 'include',
    headers,
  });

  const texto = await resposta.text();

  let dados = {};

  if (texto) {
    try {
      dados = JSON.parse(texto);
    } catch {
      throw new Error('O servidor retornou uma resposta inválida.');
    }
  }

  if (!resposta.ok || dados.sucesso === false) {
    throw new Error(dados.erro || 'Erro ao comunicar com o servidor.');
  }

  return dados;
}

// AUTENTICAÇÃO

export function verificarSessao() {
  return requisicaoApi('login.php', {
    method: 'GET',
  });
}

export function fazerLogin(login, senha) {
  return requisicaoApi('login.php', {
    method: 'POST',
    body: JSON.stringify({ login, senha }),
  });
}

export function fazerLogout() {
  return requisicaoApi('logout.php', {
    method: 'POST',
  });
}

// AÇÕES

export function listarAcoes() {
  return requisicaoApi('acoes/listar.php', {
    method: 'GET',
  });
}

export function criarAcao(dados) {
  return requisicaoApi('acoes/criar.php', {
    method: 'POST',
    body: dados,
  });
}

export function editarAcao(id, dados) {
  dados.append('id_acao', id);

  return requisicaoApi('acoes/editar.php', {
    method: 'POST',
    body: dados,
  });
}

export function visualizarAcao(id) {
  const parametros = new URLSearchParams({
    id_acao: id,
  });

  return requisicaoApi(`acoes/visualizar.php?${parametros}`, {
    method: 'GET',
  });
}

export function excluirAcao(id) {
  return requisicaoApi('acoes/excluir.php', {
    method: 'POST',
    body: JSON.stringify({ id_acao: id }),
  });
}
