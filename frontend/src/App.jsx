
import { useEffect, useState } from 'react';

import LoginPage from './pages/LoginPage';
import PainelPage from './pages/PainelPage';
import ListaAcoesPage from './pages/acoes/ListaAcoesPage';
import CriarAcaoPage from './pages/acoes/CriarAcaoPage';
import EditarAcaoPage from './pages/acoes/EditarAcaoPage';
import VisualizarAcaoPage from './pages/acoes/VisualizarAcaoPage';

import { fazerLogout, verificarSessao } from './services/api';

export default function App() {
  const [usuario, setUsuario] = useState(null);
  const [verificando, setVerificando] = useState(true);

  const [pagina, setPagina] = useState('painel');
  const [acaoSelecionada, setAcaoSelecionada] = useState(null);
  const [atualizacaoAcoes, setAtualizacaoAcoes] = useState(0);

  useEffect(() => {
    verificarSessao()
      .then((dados) => {
        if (dados.autenticado) {
          setUsuario(dados.usuario);
        }
      })
      .catch(() => {
        setUsuario(null);
      })
      .finally(() => {
        setVerificando(false);
      });
  }, []);

  useEffect(() => {
    function abrirCadastro() {
      setPagina('criar-acao');
    }

    function abrirEdicao(evento) {
      setAcaoSelecionada(evento.detail);
      setPagina('editar-acao');
    }

    function abrirVisualizacao(evento) {
      setAcaoSelecionada(evento.detail);
      setPagina('visualizar-acao');
    }

    window.addEventListener('nova-acao', abrirCadastro);
    window.addEventListener('editar-acao', abrirEdicao);
    window.addEventListener('visualizar-acao', abrirVisualizacao);

    return () => {
      window.removeEventListener('nova-acao', abrirCadastro);
      window.removeEventListener('editar-acao', abrirEdicao);
      window.removeEventListener('visualizar-acao', abrirVisualizacao);
    };
  }, []);

  async function handleLogout() {
    await fazerLogout();
    setUsuario(null);
    setPagina('painel');
    setAcaoSelecionada(null);
  }

  function voltarParaLista() {
    setPagina('acoes');
    setAcaoSelecionada(null);
  }

  function concluirOperacao() {
    setAtualizacaoAcoes((anterior) => anterior + 1);
    voltarParaLista();
  }

  if (verificando) {
    return <p className="carregando-centro">Carregando...</p>;
  }

  if (!usuario) {
    return <LoginPage aoLogar={(usr) => setUsuario(usr)} />;
  }

  const paginasDeAcao = [
    'acoes',
    'criar-acao',
    'editar-acao',
    'visualizar-acao',
  ];

  return (
    <div className="container">
      <header className="topo">
        <strong>Painel ENADE · Recorte R05</strong>

        <div>
          <span>Olá, {usuario.nome}</span>
          <button onClick={handleLogout} className="btn-secundario">
            Sair
          </button>
        </div>
      </header>

      <nav className="navegacao">
        <button
          className={pagina === 'painel' ? 'nav-ativo' : ''}
          onClick={() => setPagina('painel')}
        >
          Painel
        </button>

        <button
          className={paginasDeAcao.includes(pagina) ? 'nav-ativo' : ''}
          onClick={() => setPagina('acoes')}
        >
          Gerenciar ações
        </button>
      </nav>

      <main>
        {pagina === 'painel' && (
          <PainelPage usuario={usuario} />
        )}

        {pagina === 'acoes' && (
          <ListaAcoesPage key={atualizacaoAcoes} />
        )}

        {pagina === 'criar-acao' && (
          <CriarAcaoPage
            aoVoltar={voltarParaLista}
            aoConcluir={concluirOperacao}
          />
        )}

        {pagina === 'editar-acao' && (
          <EditarAcaoPage
            id={acaoSelecionada}
            aoVoltar={voltarParaLista}
            aoConcluir={concluirOperacao}
          />
        )}

        {pagina === 'visualizar-acao' && (
          <VisualizarAcaoPage
            id={acaoSelecionada}
            aoVoltar={voltarParaLista}
            aoEditar={(id) => {
              setAcaoSelecionada(id);
              setPagina('editar-acao');
            }}
          />
        )}
      </main>
    </div>
  );
}
