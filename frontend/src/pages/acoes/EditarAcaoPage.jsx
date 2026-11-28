
import { useEffect, useState } from 'react';
import AcaoForm from '../../components/AcaoForm';
import {
  visualizarAcao,
  editarAcao,
} from '../../services/api';

export default function EditarAcaoPage({ id, aoVoltar, aoConcluir }) {
  const [acao, setAcao] = useState(null);
  const [carregando, setCarregando] = useState(true);
  const [salvando, setSalvando] = useState(false);
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

  async function salvarAlteracoes(dados) {
    try {
      setSalvando(true);
      setErro('');

      await editarAcao(id, dados);

      aoConcluir();
    } catch (erroApi) {
      setErro(erroApi.message);
    } finally {
      setSalvando(false);
    }
  }

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
          <h2>Editar ação</h2>
          <p>Altere as informações da ação selecionada.</p>
        </div>
      </div>

      <div className="card">
        <AcaoForm
          acao={acao}
          aoSalvar={salvarAlteracoes}
          aoCancelar={aoVoltar}
          carregando={salvando}
          erro={erro}
        />
      </div>
    </section>
  );
}
