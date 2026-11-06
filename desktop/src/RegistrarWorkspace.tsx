import { useEffect, useRef, useState } from 'react';
import { api } from './api';
import type { User } from './types';
import AllStudents from './views/AllStudents';
import HelpRegistrar from './views/HelpRegistrar';
import Legal from './views/Legal';
import Overview from './views/Overview';
import Settings from './views/Settings';
import Users from './views/Users';

type View = 'overview' | 'users' | 'students' | 'settings' | 'legal' | 'help';

const LOGO = 'https://www.holyface.school/assets/img/branding/hf-logo.png';

export default function RegistrarWorkspace({
  token,
  user,
  logout,
}: {
  token: string;
  user: User;
  logout: () => Promise<void>;
}) {
  const [view, setView] = useState<View>('overview');
  const [navOpen, setNavOpen] = useState(false);
  const [inactivityMin, setInactivityMin] = useState(15);
  const lastActive = useRef(Date.now());

  useEffect(() => {
    api<{ settings: Record<string, { value: string }> }>('/admin/settings', token)
      .then((settings) => {
        const mins = Number(settings.settings.inactivity_minutes?.value ?? 15);
        if (mins > 0) setInactivityMin(mins);
      })
      .catch(() => undefined);
  }, [token]);

  useEffect(() => {
    const bump = () => {
      lastActive.current = Date.now();
    };
    window.addEventListener('keydown', bump);
    window.addEventListener('mousedown', bump);
    const id = window.setInterval(() => {
      if (Date.now() - lastActive.current > inactivityMin * 60 * 1000) {
        void logout();
      }
    }, 10000);
    return () => {
      window.removeEventListener('keydown', bump);
      window.removeEventListener('mousedown', bump);
      window.clearInterval(id);
    };
  }, [inactivityMin, logout]);

  return (
    <div className="app-shell">
      {navOpen ? (
        <button type="button" className="nav-backdrop" aria-label="Close menu" onClick={() => setNavOpen(false)} />
      ) : null}
      <aside className={navOpen ? 'sidebar open' : 'sidebar'}>
        <div className="brand-block">
          <img src={LOGO} alt="" className="logo-sm" />
          <div>
            <h1>Holy Face</h1>
            <p className="role">Registrar</p>
          </div>
        </div>
        <p className="user-name">{user.full_name}</p>
        <nav>
          {(
            [
              ['overview', 'Overview'],
              ['users', 'Accounts'],
              ['students', 'Students'],
              ['settings', 'System'],
              ['help', 'Help'],
              ['legal', 'Policies'],
            ] as [View, string][]
          ).map(([id, label]) => (
            <button
              key={id}
              type="button"
              className={view === id ? 'nav on' : 'nav'}
              onClick={() => {
                setView(id);
                setNavOpen(false);
              }}
            >
              {label}
            </button>
          ))}
        </nav>
        <button type="button" className="ghost logout" onClick={() => void logout()}>
          Log out
        </button>
      </aside>
      <main className="main">
        <button type="button" className="menu-btn" onClick={() => setNavOpen(true)}>
          Menu
        </button>
        {view === 'overview' && <Overview token={token} />}
        {view === 'users' && <Users token={token} />}
        {view === 'students' && <AllStudents token={token} />}
        {view === 'settings' && <Settings token={token} />}
        {view === 'legal' && <Legal token={token} />}
        {view === 'help' && <HelpRegistrar />}
      </main>
    </div>
  );
}
