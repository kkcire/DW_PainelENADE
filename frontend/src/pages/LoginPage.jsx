import { useState } from 'react';
import { fazerLogin } from '../services/api';

/**
 * LoginPage.jsx - Tela básica de login seguindo um padrão limpo de formulário controlado.
 */
export default function LoginPage({ aoLogar }) {
  const [login, setLogin] = useState('');
  const [senha, setSenha] = useState('');
  const [erro, setErro] = useState('');
  const [carregando, setCarregando] = useState(false);

  async function handleSubmit(evento) {
    evento.preventDefault();
    setErro('');
    setCarregando(true);

    try {
      const resposta = await fazerLogin(login, senha);
      aoLogar(resposta.usuario);
    } catch (err) {
      setErro(err.message);
    } finally {
      setCarregando(false);
    }
  }

  return (
    <div className="login-container">
      <div className="card login-card">
        <h1>Painel ENADE · R05</h1>
        <p className="subtitulo">Entre com seu usuário e senha para acessar o sistema.</p>

        {erro && <div className="alerta-erro">{erro}</div>}

        <form onSubmit={handleSubmit} className="form-padrao">
          <div className="campo">
            <label htmlFor="login">Usuário</label>
            <input
              id="login"
              type="text"
              value={login}
              onChange={(e) => setLogin(e.target.value)}
              placeholder="admin"
              required
            />
          </div>

          <div className="campo">
            <label htmlFor="senha">Senha</label>
            <input
              id="senha"
              type="password"
              value={senha}
              onChange={(e) => setSenha(e.target.value)}
              placeholder="••••••••"
              required
            />
          </div>

          <button type="submit" className="btn-primario" disabled={carregando}>
            {carregando ? 'Entrando...' : 'Entrar'}
          </button>
        </form>

        <small className="dica-demo">
          Usuário de demonstração: <strong>admin</strong> | Senha: <strong>admin123</strong>
        </small>
      </div>
    </div>
  );
}
