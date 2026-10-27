import { FormEvent, useState } from 'react';

const LOGO = 'https://www.holyface.school/assets/img/branding/hf-logo.png';

export default function Login({
  onLogin,
  error,
}: {
  onLogin: (username: string, password: string) => Promise<void>;
  error: string;
}) {
  const [username, setUsername] = useState('mreyes');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    try {
      await onLogin(username, password);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="login-wrap">
      <div className="card login">
        <img src={LOGO} alt="Holy Face of Jesus Lyceum of San Jose" className="logo" />
        <h2>Holy Face AMGS</h2>
        <p>Teacher desktop</p>
        <form onSubmit={submit}>
          <label>
            Username
            <input value={username} onChange={(e) => setUsername(e.target.value)} autoComplete="username" autoFocus />
          </label>
          <label>
            Password
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoComplete="current-password"
            />
          </label>
          {error && <p className="error">{error}</p>}
          <button type="submit" disabled={busy}>
            {busy ? 'Signing in…' : 'Log In'}
          </button>
        </form>
        <p className="hint">Demo: mreyes or registrar · HolyFace@2026</p>
      </div>
    </div>
  );
}
