import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { api, getApiBase, probeApi, setApiBase } from './api';
import { enqueue, flushQueue, loadCache, saveCache } from './sync';
import type { GridPayload, Section, SecureCache, SyncStatus, User } from './types';
import AllStudents from './views/AllStudents';
import Dashboard from './views/Dashboard';
import GradeGrid from './views/GradeGrid';
import AcceptPolicies from './views/AcceptPolicies';
import Help from './views/Help';
import Legal from './views/Legal';
import Login from './views/Login';
import Reports from './views/Reports';
import Roster from './views/Roster';
import Settings from './views/Settings';

type View = 'roster' | 'grid' | 'all' | 'dashboard' | 'reports' | 'settings' | 'help' | 'legal';

const LOGO = 'https://www.holyface.school/assets/img/branding/hf-logo.png';

export default function App() {
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<User | null>(null);
  const [error, setError] = useState('');
  const [consentComplete, setConsentComplete] = useState(false);
  const [requiresGuardian, setRequiresGuardian] = useState(false);
  const [view, setView] = useState<View>('grid');
  const [sections, setSections] = useState<Section[]>([]);
  const [csaId, setCsaId] = useState<number | null>(null);
  const [quarter, setQuarter] = useState(1);
  const [grid, setGrid] = useState<GridPayload | null>(null);
  const [sync, setSync] = useState<SyncStatus>('offline');
  const [queueLen, setQueueLen] = useState(0);
  const [inactivityMin, setInactivityMin] = useState(15);
  const cacheRef = useRef<SecureCache>({});
  const lastActive = useRef(Date.now());

  const section = useMemo(
    () => sections.find((s) => s.class_subject_assignment_id === csaId) ?? null,
    [sections, csaId]
  );
  const isAdmin = user?.role === 'admin';

  const persist = useCallback(async (patch: Partial<SecureCache>) => {
    cacheRef.current = { ...cacheRef.current, ...patch };
    setQueueLen(cacheRef.current.queue?.length ?? 0);
    await saveCache(cacheRef.current);
  }, []);

  const logout = useCallback(async () => {
    setToken(null);
    setUser(null);
    setGrid(null);
    await persist({ token: null, user: null });
  }, [persist]);

  const loadGrid = useCallback(
    async (t: string, assignment: number, q: number) => {
      try {
        const g = await api<GridPayload>(`/grades/grid?class_subject_assignment_id=${assignment}&quarter=${q}`, t);
        setGrid(g);
        setSync('online');
        await persist({
          lastGrid: { key: `${assignment}:${q}`, payload: g, savedAt: new Date().toISOString() },
        });
      } catch {
        const cached = cacheRef.current.lastGrid;
        if (cached && cached.key === `${assignment}:${q}`) {
          setGrid(cached.payload);
          setSync('offline');
        }
      }
    },
    [persist]
  );

  const boot = useCallback(async () => {
    const cache = await loadCache();
    cacheRef.current = cache;
    setQueueLen(cache.queue?.length ?? 0);
    if (cache.apiBase) setApiBase(cache.apiBase);
    try {
      const base = await probeApi();
      await persist({ apiBase: base });
      setSync('online');
    } catch {
      setSync('offline');
    }
    if (cache.token && cache.user) {
      setToken(cache.token);
      setUser(cache.user);
    }
  }, [persist]);

  useEffect(() => {
    void boot();
  }, [boot]);

  const loadTeacherData = useCallback(
    async (t: string) => {
      const sec = await api<{ sections: Section[] }>('/teachers/sections', t);
      setSections(sec.sections);
      const first = sec.sections[0];
      if (first) {
        setCsaId((id) => id ?? first.class_subject_assignment_id);
      }
      try {
        const settings = await api<{ settings: Record<string, { value: string }> }>('/admin/settings', t);
        const mins = Number(settings.settings.inactivity_minutes?.value ?? 15);
        if (mins > 0) setInactivityMin(mins);
      } catch {
        /* keep default */
      }
    },
    []
  );

  useEffect(() => {
    if (!token) return;
    api<{ user: User; consent_complete: boolean; requires_guardian?: boolean }>('/auth/me', token)
      .then((r) => {
        if (r.user.role !== 'teacher' && r.user.role !== 'admin') {
          throw new Error('Teacher or Registrar account required.');
        }
        setUser(r.user);
        setConsentComplete(!!r.consent_complete);
        setRequiresGuardian(!!r.requires_guardian);
        return loadTeacherData(token);
      })
      .catch((e: Error) => {
        setError(e.message);
        void logout();
      });
  }, [token, loadTeacherData, logout]);

  useEffect(() => {
    if (!token || !csaId) return;
    void loadGrid(token, csaId, quarter);
  }, [token, csaId, quarter, loadGrid]);

  useEffect(() => {
    if (!token) return;
    const id = window.setInterval(async () => {
      try {
        await probeApi();
        setSync('syncing');
        const result = await flushQueue(cacheRef.current, token, () => setSync('conflict'));
        cacheRef.current = result.cache;
        await saveCache(result.cache);
        setQueueLen(result.cache.queue?.length ?? 0);
        setSync(result.status);
        if (csaId && (result.cache.queue?.length ?? 0) === 0) {
          void loadGrid(token, csaId, quarter);
        }
      } catch {
        setSync('offline');
      }
    }, 12000);
    return () => window.clearInterval(id);
  }, [token, csaId, quarter, loadGrid]);

  useEffect(() => {
    const bump = () => {
      lastActive.current = Date.now();
    };
    window.addEventListener('keydown', bump);
    window.addEventListener('mousedown', bump);
    const id = window.setInterval(() => {
      if (!token) return;
      if (Date.now() - lastActive.current > inactivityMin * 60 * 1000) {
        void logout();
      }
    }, 10000);
    return () => {
      window.removeEventListener('keydown', bump);
      window.removeEventListener('mousedown', bump);
      window.clearInterval(id);
    };
  }, [token, inactivityMin, logout]);

  async function onLogin(username: string, password: string) {
    setError('');
    try {
      await probeApi();
      const data = await api<{
        access_token: string;
        user: User;
        consent_complete: boolean;
        requires_guardian?: boolean;
      }>('/auth/login', null, {
        method: 'POST',
        body: JSON.stringify({ username, password }),
      });
      if (data.user.role !== 'teacher' && data.user.role !== 'admin') {
        throw new Error('Use the student mobile app for learner accounts.');
      }
      setToken(data.access_token);
      setUser(data.user);
      setConsentComplete(!!data.consent_complete);
      setRequiresGuardian(!!data.requires_guardian);
      await persist({ token: data.access_token, user: data.user, apiBase: getApiBase() });
      await loadTeacherData(data.access_token);
    } catch (err) {
      setError((err as Error).message);
    }
  }

  function onOfflineSave(body: unknown) {
    const next = enqueue(cacheRef.current, { method: 'POST', path: '/grades/batch', body });
    void persist(next);
    setSync('offline');
  }

  const years = [...new Set(sections.map((s) => s.school_year))];
  const strands = [...new Set(sections.map((s) => s.strand))];
  const [year, setYear] = useState('');
  const [strand, setStrand] = useState('');

  const filteredSections = sections.filter((s) => {
    if (year && s.school_year !== year) return false;
    if (strand && s.strand !== strand) return false;
    return true;
  });

  if (!token || !user) {
    return <Login onLogin={onLogin} error={error} />;
  }

  if (!consentComplete) {
    return (
      <AcceptPolicies
        token={token}
        requiresGuardian={requiresGuardian}
        onAccepted={() => setConsentComplete(true)}
      />
    );
  }

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="brand-block">
          <img src={LOGO} alt="" className="logo-sm" />
          <div>
            <h1>Holy Face</h1>
            <p className="role">{user.role === 'admin' ? 'Registrar' : 'Teacher'}</p>
          </div>
        </div>
        <p className="user-name">{user.full_name}</p>
        <SyncBadge status={sync} queued={queueLen} />
        <nav>
          {(
            [
              ['grid', 'Grades'],
              ['roster', 'Class list'],
              ['dashboard', 'Overview'],
              ['reports', 'Reports'],
              ...(isAdmin ? [['all', 'All students'] as const] : []),
              ['settings', 'Settings'],
              ['help', 'Help'],
              ['legal', 'Policies'],
            ] as [View, string][]
          ).map(([id, label]) => (
            <button key={id} type="button" className={view === id ? 'nav on' : 'nav'} onClick={() => setView(id)}>
              {label}
            </button>
          ))}
        </nav>
        <button type="button" className="ghost logout" onClick={() => void logout()}>
          Log out
        </button>
      </aside>
      <main className="main">
        <div className="topbar">
          <label>
            School year
            <select
              value={year || section?.school_year || ''}
              onChange={(e) => {
                setYear(e.target.value);
                const next = sections.find((s) => s.school_year === e.target.value);
                if (next) setCsaId(next.class_subject_assignment_id);
              }}
            >
              {years.map((y) => (
                <option key={y} value={y}>
                  {y}
                </option>
              ))}
            </select>
          </label>
          <label>
            Quarter
            <select value={quarter} onChange={(e) => setQuarter(Number(e.target.value))}>
              {[1, 2, 3, 4].map((q) => (
                <option key={q} value={q}>
                  Q{q}
                </option>
              ))}
            </select>
          </label>
          <label>
            Strand
            <select
              value={strand || section?.strand || ''}
              onChange={(e) => {
                setStrand(e.target.value);
                const next = sections.find((s) => s.strand === e.target.value);
                if (next) setCsaId(next.class_subject_assignment_id);
              }}
            >
              {strands.map((s) => (
                <option key={s} value={s}>
                  {s}
                </option>
              ))}
            </select>
          </label>
          <label>
            Section & subject
            <select value={csaId ?? ''} onChange={(e) => setCsaId(Number(e.target.value))}>
              {filteredSections.map((s) => (
                <option key={s.class_subject_assignment_id} value={s.class_subject_assignment_id}>
                  G{s.grade_level} {s.strand}-{s.section_code} · {s.subject_title}
                </option>
              ))}
            </select>
          </label>
        </div>

        {view === 'roster' && (
          <Roster section={section} quarter={quarter} grid={grid} onOpenGrid={() => setView('grid')} />
        )}
        {view === 'grid' && section && grid && (
          <GradeGrid
            token={token}
            section={section}
            quarter={quarter}
            grid={grid}
            setGrid={setGrid}
            locked={!!grid.locked}
            onOfflineSave={onOfflineSave}
            role={user.role}
          />
        )}
        {view === 'grid' && (!section || !grid) && <div className="card">Loading grades…</div>}
        {view === 'all' && isAdmin && <AllStudents token={token} />}
        {view === 'dashboard' && <Dashboard token={token} section={section} quarter={quarter} />}
        {view === 'reports' && <Reports token={token} section={section} quarter={quarter} grid={grid} />}
        {view === 'settings' && <Settings token={token} />}
        {view === 'legal' && <Legal token={token} />}
        {view === 'help' && <Help />}
      </main>
    </div>
  );
}

function SyncBadge({ status, queued }: { status: SyncStatus; queued: number }) {
  const label =
    status === 'online'
      ? 'Online'
      : status === 'syncing'
        ? 'Syncing…'
        : status === 'conflict'
          ? 'Conflict'
          : status === 'error'
            ? 'Sync error'
            : 'Offline';
  return (
    <div className={`sync ${status}`}>
      <span className="dot" />
      {label}
      {queued > 0 ? ` · ${queued} queued` : ''}
    </div>
  );
}
