
import { useEffect, useState } from 'react';
import { visualizarAcao } from '../../services/api';

export default function VisualizarAcaoPage({ id, aoVoltar, aoEditar }) {
  const [acao, setAcao] = useState(null);
  const [carregando, setCarregando] = useState(true);
  const [erro, setErro] = useState('');

  useEffect(() => {
    async function carregarAcao() {
      try {
        setCarregando(true);
        setErro('');

        const dados = await visualizarAcao(id);
        setAcao(dados.acao ?? dados);
      } catch (erroApi) {
        setErro(erroApi.message);
      } finally {
        setCarregando(false);
      }
    }

    if (id) {
      carregarAcao();
    }
  }, [id]);

  if (carregando) {
    return <p className="carregando-centro">Carregando ação...</p>;
  }

  if (!acao) {
    return (
      <section className="acoes-pagina">
        <div className="alerta-erro">
          {erro || 'Não foi possível encontrar esta ação.'}
        </div>

        <button className="btn-secundario" onClick={aoVoltar}>
          Voltar
        </button>
      </section>
    );
  }

  return (
    <section className="acoes-pagina">
      <div className="acoes-cabecalho">
        <div>
          <h2>Detalhes da ação</h2>
          <p>Consulte as informações cadastradas.</p>
        </div>

        <button
          className="btn-primario"
          onClick={() => aoEditar(id)}
        >
          Editar ação
        </button>
      </div>

      <div className="card detalhes-acao">
        <div className="detalhe-item">
          <span>Título</span>
          <strong>{acao.titulo || 'Não informado'}</strong>
        </div>

        <div className="detalhe-item">
          <span>Descrição</span>
          <p>{acao.descricao || 'Sem descrição cadastrada.'}</p>
        </div>

        <div className="detalhe-item">
          <span>Oferta</span>
          <strong>
            {acao.curso || acao.nome_curso || acao.id_oferta || 'Não informada'}
          </strong>
        </div>

        <div className="detalhe-item">
          <span>Responsável</span>
          <strong>{acao.responsavel || 'Não informado'}</strong>
        </div>

        <div className="detalhe-item">
          <span>Prazo</span>
          <strong>
            {acao.prazo
              ? new Date(`${String(acao.prazo).slice(0, 10)}T00:00:00`).toLocaleDateString('pt-BR')
              : 'Sem prazo definido'}
          </strong>
        </div>

        <div className="detalhe-item">
          <span>Situação</span>
          <strong>{acao.situacao || 'Não informada'}</strong>
        </div>

        <div className="detalhe-item">
          <span>Anexo</span>

          {acao.anexo_url ? (
            <a
              href={acao.anexo_url}
              target="_blank"
              rel="noreferrer"
            >
              {acao.anexo_nome_original || 'Abrir anexo'}
            </a>
          ) : (
            <strong>
              {acao.anexo_nome_original || 'Nenhum anexo disponível'}
            </strong>
          )}
        </div>

        <div className="detalhes-botoes">
          <button className="btn-secundario" onClick={aoVoltar}>
            Voltar para ações
          </button>
        </div>
      </div>
    </section>
  );
}
