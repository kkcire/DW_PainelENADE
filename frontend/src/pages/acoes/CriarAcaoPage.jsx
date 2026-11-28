
import { useState } from 'react';
import AcaoForm from '../../components/AcaoForm';
import { criarAcao } from '../../services/api';

export default function CriarAcaoPage({ aoVoltar, aoConcluir }) {
  const [carregando, setCarregando] = useState(false);
  const [erro, setErro] = useState('');

  async function salvarAcao(dados) {
    try {
      setCarregando(true);
      setErro('');

      await criarAcao(dados);

      aoConcluir();
    } catch (erroApi) {
      setErro(erroApi.message);
    } finally {
      setCarregando(false);
    }
  }

  return (
    <section className="acoes-pagina">
      <div className="acoes-cabecalho">
        <div>
          <h2>Nova ação</h2>
          <p>Preencha os dados para cadastrar uma nova ação.</p>
        </div>
      </div>

      <div className="card">
        <AcaoForm
          aoSalvar={salvarAcao}
          aoCancelar={aoVoltar}
          carregando={carregando}
          erro={erro}
        />
      </div>
    </section>
  );
}
