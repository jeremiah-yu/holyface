import { useCallback, useEffect, useMemo, useRef, useState, type MutableRefObject } from 'react';
import { api } from './api';
import { enqueue, flushQueue, saveCache } from './sync';
import type { GridPayload, Section, SecureCache, SyncStatus, User } from './types';
import { calendarForGrade } from './calendar';
import Advisory from './views/Advisory';
import Dashboard from './views/Dashboard';
import GradeGrid from './views/GradeGrid';
import Help from './views/Help';
import Legal from './views/Legal';
import Reports from './views/Reports';
import Roster from './views/Roster';
import Settings from './views/Settings';

type View = 'roster' | 'grid' | 'dashboard' | 'reports' | 'advisory' | 'settings' | 'help' | 'legal';

const LOGO = 'https://www.holyface.school/assets/img/branding/hf-logo.png';

export default function TeacherWorkspace({
  token,
  user,
  cacheRef,
  persist,
  logout,
}: {
  token: string;
  user: User;
  cacheRef: MutableRefObject<SecureCache>;
  persist: (patch: Partial<SecureCache>) => Promise<void>;
  logout: () => Promise<void>;
}) {
  const [view, setView] = useState<View>('grid');
  const [sections, setSections] = useState<Section[]>([]);
  const [csaId, setCsaId] = useState<number | null>(null);
  const [quarter, setQuarter] = useState(1);
  const [grid, setGrid] = useState<GridPayload | null>(null);
  const [sync, setSync] = useState<SyncStatus>('offline');
  const [queueLen, setQueueLen] = useState(0);
  const [inactivityMin, setInactivityMin] = useState(15);
  const lastActive = useRef(Date.now());

  const section = useMemo(
    () => sections.find((s) => s.class_subject_assignment_id === csaId) ?? null,
    [sections, csaId]
  );
  const calendar = calendarForGrade(section?.grade_level ?? 12);
  const isAdviser = sections.some((s) => Number(s.is_adviser) === 1);

  useEffect(() => {
    if (section && quarter > calendar.max_period) {
      setQuarter(1);
    }
  }, [section, quarter, calendar.max_period]);

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
    [persist, cacheRef]
  );

  useEffect(() => {
    api<{ sections: Section[] }>('/teachers/sections', token).then((sec) => {
      setSections(sec.sections);
      const first = sec.sections[0];
      if (first) setCsaId((id) => id ?? first.class_subject_assignment_id);
    }).catch(() => undefined);
    api<{ settings: Record<string, { value: string }> }>('/admin/settings', token)
      .then((settings) => {
        const mins = Number(settings.settings.inactivity_minutes?.value ?? 15);
        if (mins > 0) setInactivityMin(mins);
      })
      .catch(() => undefined);
  }, [token]);

  useEffect(() => {
    if (!csaId) return;
    void loadGrid(token, csaId, quarter);
  }, [token, csaId, quarter, loadGrid]);

  useEffect(() => {
    const id = window.setInterval(async () => {
      try {
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
  }, [token, csaId, quarter, loadGrid, cacheRef]);

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

  function onOfflineSave(body: unknown) {
    const next = enqueue(cacheRef.current, { method: 'POST', path: '/grades/batch', body });
    void persist(next);
    setQueueLen(next.queue?.length ?? 0);
    setSync('offline');
  }

  const years = [...new Set(sections.map((s) => s.school_year))];
  const strands = [...new Set(sections.map((s) => s.strand))];
  const [year, setYear] = useState('');
  const [strand, setStrand] = useState('');
  const [navOpen, setNavOpen] = useState(false);

  const filteredSections = sections.filter((s) => {
    if (year && s.school_year !== year) return false;
    if (strand && s.strand !== strand) return false;
    return true;
  });

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
            <p className="role">Professor</p>
          </div>
        </div>
        <p className="user-name">{user.full_name}</p>
        <SyncBadge status={sync} queued={queueLen} />
        <nav>
          {(
            (
              [
                ['grid', 'Grades'],
                ['roster', 'Class list'],
                ['dashboard', 'Overview'],
                ['reports', 'Reports'],
                ...(isAdviser ? ([['advisory', 'Advisory']] as [View, string][]) : []),
                ['settings', 'Settings'],
                ['help', 'Help'],
                ['legal', 'Policies'],
              ] as [View, string][]
            )
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
            {calendar.mode === 'terms' ? 'Term' : 'Quarter'}
            <select value={quarter} onChange={(e) => setQuarter(Number(e.target.value))}>
              {calendar.periods.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.label}
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
        {view === 'dashboard' && <Dashboard token={token} section={section} quarter={quarter} />}
        {view === 'reports' && <Reports token={token} section={section} quarter={quarter} grid={grid} />}
        {view === 'advisory' && <Advisory token={token} />}
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
