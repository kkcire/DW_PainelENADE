
import { useEffect, useMemo, useState } from 'react';
import {
  listarAcoes,
  excluirAcao,
} from '../../services/api';

export default function ListaAcoesPage() {
  const [acoes, setAcoes] = useState([]);
  const [busca, setBusca] = useState('');
  const [situacao, setSituacao] = useState('Todas');
  const [carregando, setCarregando] = useState(true);
  const [erro, setErro] = useState('');

  async function carregarAcoes() {
    try {
      setCarregando(true);
      setErro('');

      const dados = await listarAcoes();

      setAcoes(dados.acoes ?? []);
    } catch (erroApi) {
      setErro(erroApi.message);
    } finally {
      setCarregando(false);
    }
  }

  useEffect(() => {
    carregarAcoes();
  }, []);

  async function handleExcluir(id) {
    const confirmar = window.confirm(
      'Tem certeza de que deseja excluir esta ação?'
    );

    if (!confirmar) return;

    try {
      await excluirAcao(id);

      setAcoes((anteriores) =>
        anteriores.filter((acao) => acao.id_acao !== id)
      );
    } catch (erroApi) {
      setErro(erroApi.message);
    }
  }

  const acoesFiltradas = useMemo(() => {
    return acoes.filter((acao) => {
      const correspondeBusca = (acao.titulo ?? '')
        .toLowerCase()
        .includes(busca.toLowerCase());

      const correspondeSituacao =
        situacao === 'Todas' || acao.situacao === situacao;

      return correspondeBusca && correspondeSituacao;
    });
  }, [acoes, busca, situacao]);

  return (
    <section className="acoes-pagina">
      <div className="acoes-cabecalho">
        <div>
          <h2>Gerenciamento de ações</h2>
          <p>Consulte e acompanhe as ações cadastradas.</p>
        </div>

        <button
          className="btn-primario"
          onClick={() => window.dispatchEvent(new Event('nova-acao'))}
        >
          + Nova ação
        </button>
      </div>

      <div className="acoes-filtros">
        <div className="campo">
          <label htmlFor="busca-acao">Pesquisar ação</label>
          <input
            id="busca-acao"
            type="search"
            placeholder="Digite o título da ação..."
            value={busca}
            onChange={(evento) => setBusca(evento.target.value)}
          />
        </div>

        <div className="campo">
          <label htmlFor="filtro-situacao">Situação</label>
          <select
            id="filtro-situacao"
            value={situacao}
            onChange={(evento) => setSituacao(evento.target.value)}
          >
            <option value="Todas">Todas</option>
            <option value="Planejada">Planejada</option>
            <option value="Em andamento">Em andamento</option>
            <option value="Concluída">Concluída</option>
          </select>
        </div>
      </div>

      {erro && <div className="alerta-erro">{erro}</div>}

      {carregando ? (
        <p className="carregando-centro">Carregando ações...</p>
      ) : (
        <div className="tabela-acoes-container">
          <table className="tabela-acoes">
            <thead>
              <tr>
                <th>Ação</th>
                <th>Responsável</th>
                <th>Prazo</th>
                <th>Situação</th>
                <th>Operações</th>
              </tr>
            </thead>

            <tbody>
              {acoesFiltradas.length === 0 ? (
                <tr>
                  <td colSpan="5" className="sem-acoes">
                    Nenhuma ação encontrada.
                  </td>
                </tr>
              ) : (
                acoesFiltradas.map((acao) => (
                  <tr key={acao.id_acao}>
                    <td>{acao.titulo}</td>
                    <td>{acao.responsavel || 'Não informado'}</td>
                    <td>
                      {acao.prazo
                        ? new Date(`${acao.prazo}T00:00:00`).toLocaleDateString('pt-BR')
                        : 'Sem prazo'}
                    </td>
                    <td>
                      <span
                        className={`status-acao status-${acao.situacao
                          ?.toLowerCase()
                          .replaceAll(' ', '-')}`}
                      >
                        {acao.situacao}
                      </span>
                    </td>
                    <td>
                      <div className="acoes-operacoes">
                        <button
                          className="btn-visualizar"
                          onClick={() =>
                            window.dispatchEvent(
                              new CustomEvent('visualizar-acao', {
                                detail: acao.id_acao,
                              })
                            )
                          }
                        >
                          Ver
                        </button>

                        <button
                          className="btn-editar"
                          onClick={() =>
                            window.dispatchEvent(
                              new CustomEvent('editar-acao', {
                                detail: acao.id_acao,
                              })
                            )
                          }
                        >
                          Editar
                        </button>

                        <button
                          className="btn-excluir"
                          onClick={() => handleExcluir(acao.id_acao)}
                        >
                          Excluir
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}

      <p className="total-acoes">
        {acoesFiltradas.length} ação(ões) encontrada(s).
      </p>
    </section>
  );
}
