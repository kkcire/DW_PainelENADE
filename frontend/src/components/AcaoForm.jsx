
import { useEffect, useState } from 'react';

export default function AcaoForm({
  acao = null,
  aoSalvar,
  aoCancelar,
  carregando = false,
  erro = ''
}) {
  const [formulario, setFormulario] = useState({
    id_oferta: '',
    titulo: '',
    descricao: '',
    responsavel: '',
    prazo: '',
    situacao: 'Planejada'
  });

  const [anexo, setAnexo] = useState(null);

  useEffect(() => {
    if (acao) {
      setFormulario({
        id_oferta: acao.id_oferta ?? '',
        titulo: acao.titulo ?? '',
        descricao: acao.descricao ?? '',
        responsavel: acao.responsavel ?? '',
        prazo: acao.prazo ? String(acao.prazo).slice(0, 10) : '',
        situacao: acao.situacao ?? 'Planejada'
      });
    }
  }, [acao]);

  function atualizarCampo(evento) {
    const { name, value } = evento.target;

    setFormulario((anterior) => ({
      ...anterior,
      [name]: value
    }));
  }

  function enviarFormulario(evento) {
    evento.preventDefault();

    const dados = new FormData();

    Object.entries(formulario).forEach(([campo, valor]) => {
      dados.append(campo, valor);
    });

    if (anexo) {
      dados.append('anexo', anexo);
    }

    aoSalvar(dados);
  }

  return (
    <form className="form-padrao acao-form" onSubmit={enviarFormulario}>
      {erro && <div className="alerta-erro">{erro}</div>}

      <div className="campo">
        <label htmlFor="titulo">Título da ação</label>
        <input
          id="titulo"
          name="titulo"
          value={formulario.titulo}
          onChange={atualizarCampo}
          required
          maxLength={150}
          placeholder="Ex.: Reforço em conteúdos de formação geral"
        />
      </div>

      <div className="campo">
        <label htmlFor="id_oferta">ID da oferta</label>
        <input
          id="id_oferta"
          name="id_oferta"
          type="number"
          min="1"
          value={formulario.id_oferta}
          onChange={atualizarCampo}
          required
          placeholder="Informe o ID da oferta"
        />
      </div>

      <div className="campo">
        <label htmlFor="descricao">Descrição</label>
        <textarea
          id="descricao"
          name="descricao"
          value={formulario.descricao}
          onChange={atualizarCampo}
          rows="4"
          placeholder="Descreva os objetivos e atividades da ação"
        />
      </div>

      <div className="campo">
        <label htmlFor="responsavel">Responsável</label>
        <input
          id="responsavel"
          name="responsavel"
          value={formulario.responsavel}
          onChange={atualizarCampo}
          placeholder="Nome do responsável"
        />
      </div>

      <div className="campo">
        <label htmlFor="prazo">Prazo</label>
        <input
          id="prazo"
          name="prazo"
          type="date"
          value={formulario.prazo}
          onChange={atualizarCampo}
        />
      </div>

      <div className="campo">
        <label htmlFor="situacao">Situação</label>
        <select
          id="situacao"
          name="situacao"
          value={formulario.situacao}
          onChange={atualizarCampo}
        >
          <option value="Planejada">Planejada</option>
          <option value="Em andamento">Em andamento</option>
          <option value="Concluída">Concluída</option>
        </select>
      </div>

      <div className="campo">
        <label htmlFor="anexo">Anexo (opcional)</label>
        <input
          id="anexo"
          type="file"
          onChange={(evento) => setAnexo(evento.target.files[0] ?? null)}
        />
      </div>

      <div className="acoes-form-botoes">
        <button
          type="submit"
          className="btn-primario"
          disabled={carregando}
        >
          {carregando ? 'Salvando...' : 'Salvar ação'}
        </button>

        <button
          type="button"
          className="btn-secundario"
          onClick={aoCancelar}
          disabled={carregando}
        >
          Cancelar
        </button>
      </div>
    </form>
  );
}
