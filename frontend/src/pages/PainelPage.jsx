
import { useState } from 'react';

import {
  ResponsiveContainer,
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  BarChart,
  Bar,
} from 'recharts';

const dadosEvolucao = [
  { ano: '2017', conceito: 2.4 },
  { ano: '2018', conceito: 2.6 },
  { ano: '2019', conceito: 2.8 },
  { ano: '2020', conceito: 2.7 },
  { ano: '2021', conceito: 3.0 },
  { ano: '2022', conceito: 3.2 },
  { ano: '2023', conceito: 3.1 },
];

const dadosDesempenho = [
  { ano: '2017', formacaoGeral: 45, componenteEspecifico: 52 },
  { ano: '2018', formacaoGeral: 48, componenteEspecifico: 55 },
  { ano: '2019', formacaoGeral: 50, componenteEspecifico: 58 },
  { ano: '2020', formacaoGeral: 47, componenteEspecifico: 56 },
  { ano: '2021', formacaoGeral: 53, componenteEspecifico: 62 },
  { ano: '2022', formacaoGeral: 55, componenteEspecifico: 64 },
  { ano: '2023', formacaoGeral: 57, componenteEspecifico: 66 },
];

export default function PainelPage({ usuario }) {
  const [periodo, setPeriodo] = useState('todos');

  const filtrarPeriodo = (dados) => {
    if (periodo === 'todos') return dados;

    if (periodo === '2017-2019') {
      return dados.filter(
        (item) => Number(item.ano) <= 2019
      );
    }

    return dados.filter(
      (item) => Number(item.ano) >= 2020
    );
  };

  const evolucaoFiltrada = filtrarPeriodo(dadosEvolucao);
  const desempenhoFiltrado = filtrarPeriodo(dadosDesempenho);

  return (
    <div className="painel">
      <section className="painel-cabecalho">
        <div>
          <h1>Visão geral</h1>
          <p>
            Acompanhe os indicadores e resultados do ENADE.
          </p>
        </div>

        <span className="painel-recorte">Recorte R05</span>
      </section>

      <section className="indicadores">
        <div className="card-indicador">
          <h3>Ofertas</h3>
          <p>—</p>
          <span>Cursos cadastrados</span>
        </div>

        <div className="card-indicador">
          <h3>Resultados</h3>
          <p>—</p>
          <span>Registros disponíveis</span>
        </div>

        <div className="card-indicador">
          <h3>Oficiais</h3>
          <p>—</p>
          <span>Dados do INEP</span>
        </div>

        <div className="card-indicador">
          <h3>Simulados</h3>
          <p>—</p>
          <span>Dados didáticos</span>
        </div>
      </section>

      <section className="filtros-painel">
        <div>
          <label htmlFor="filtro-periodo">Período de análise</label>

          <select
            id="filtro-periodo"
            value={periodo}
            onChange={(evento) => setPeriodo(evento.target.value)}
          >
            <option value="todos">Todos os anos</option>
            <option value="2017-2019">2017 a 2019</option>
            <option value="2020-2023">2020 a 2023</option>
          </select>
        </div>

        <span>
          Exibindo {evolucaoFiltrada.length} anos
        </span>
      </section>

      <section className="graficos">
        <div className="card-grafico">
          <div className="grafico-cabecalho">
            <h2>Evolução do conceito</h2>
            <span>Dados demonstrativos</span>
          </div>

          <ResponsiveContainer width="100%" height={300}>
            <LineChart data={evolucaoFiltrada}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="ano" />
              <YAxis domain={[0, 5]} />
              <Tooltip />
              <Line
                type="monotone"
                dataKey="conceito"
                name="Conceito"
                stroke="#2563eb"
                strokeWidth={3}
              />
            </LineChart>
          </ResponsiveContainer>
        </div>

        <div className="card-grafico">
          <div className="grafico-cabecalho">
            <h2>Desempenho por área</h2>
            <span>Dados demonstrativos</span>
          </div>

          <ResponsiveContainer width="100%" height={300}>
            <BarChart data={desempenhoFiltrado}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="ano" />
              <YAxis domain={[0, 100]} />
              <Tooltip />
              <Legend />

              <Bar
                dataKey="formacaoGeral"
                name="Formação Geral"
                fill="#2563eb"
              />

              <Bar
                dataKey="componenteEspecifico"
                name="Componente Específico"
                fill="#0f766e"
              />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </section>
    </div>
  );
}
