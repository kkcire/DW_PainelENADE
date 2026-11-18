import { useEffect, useState } from 'react';
import LoginPage from './pages/LoginPage';
import { fazerLogout, verificarSessao } from './services/api';

export default function App() {
  const [usuario, setUsuario] = useState(null);
  const [verificando, setVerificando] = useState(true);

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

  async function handleLogout() {
    await fazerLogout();
    setUsuario(null);
  }

  if (verificando) {
    return <p className="carregando-centro">Carregando...</p>;
  }

  if (!usuario) {
    return <LoginPage aoLogar={(usr) => setUsuario(usr)} />;
  }

  return (
    <div className="container">
      <header className="topo">
        <strong>Painel ENADE · Recorte R05</strong>
        <div>
          <span>Olá, {usuario.nome} </span>
          <button onClick={handleLogout} className="btn-secundario">
            Sair
          </button>
        </div>
      </header>

      <main className="card">
        <h2>Login realizado com sucesso!</h2>
        <p>Próximo passo: implementar o Painel de Indicadores e o CRUD de Ações.</p>
      </main>
    </div>
  );
}
