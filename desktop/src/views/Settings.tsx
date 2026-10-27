import { FormEvent, useEffect, useState } from 'react';
import { api } from '../api';
import type { PolicyBundle, PolicyDoc } from '../legal';

type Profile = {
  id: number;
  code: string;
  name: string;
  written_work_pct: string;
  performance_task_pct: string;
  quarterly_assessment_pct: string;
  confirmed: number;
  source_note: string;
};

type Lock = {
  school_year_id: number;
  school_year: string;
  quarter: number;
  is_locked: number;
};

type SettingsPayload = {
  settings: Record<string, { value: string; source_note: string }>;
  weight_profiles: Profile[];
  locks: Lock[];
  transmutation_table: { id: number; initial_min: string; initial_max: string; transmuted_grade: number }[];
  can_edit: boolean;
};

export default function Settings({ token }: { token: string }) {
  const [data, setData] = useState<SettingsPayload | null>(null);
  const [msg, setMsg] = useState('');
  const [error, setError] = useState('');

  async function load() {
    const r = await api<SettingsPayload>('/admin/settings', token);
    setData(r);
  }

  useEffect(() => {
    load().catch((e: Error) => setError(e.message));
  }, [token]);

  if (!data) return <div className="card">{error || 'Loading settings…'}</div>;

  async function saveSettings(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    await api('/admin/settings', token, {
      method: 'PUT',
      body: JSON.stringify({
        passing_mark: fd.get('passing_mark'),
        drop_points: fd.get('drop_points'),
        inactivity_minutes: fd.get('inactivity_minutes'),
      }),
    });
    setMsg('Settings saved.');
    await load();
  }

  async function saveProfiles(e: FormEvent) {
    e.preventDefault();
    if (!data) return;
    await api('/admin/weight-profiles', token, {
      method: 'PUT',
      body: JSON.stringify({ profiles: data.weight_profiles }),
    });
    setMsg('Weight profiles saved. Confirmed profiles were pushed to class assignments.');
    await load();
  }

  async function toggleLock(lock: Lock, isLocked: boolean) {
    await api('/admin/locks', token, {
      method: 'POST',
      body: JSON.stringify({ school_year_id: lock.school_year_id, quarter: lock.quarter, is_locked: isLocked }),
    });
    await load();
  }

  return (
    <div className="card">
      <h2>Settings</h2>
      {!data.can_edit && <p className="warn">View only. The Registrar can edit weights, passing mark, and period locks.</p>}
      {msg && <p className="ok">{msg}</p>}
      {error && <p className="error">{error}</p>}

      <form onSubmit={saveSettings} className="filters">
        <label>
          Passing mark
          <input name="passing_mark" defaultValue={data.settings.passing_mark?.value} disabled={!data.can_edit} />
        </label>
        <label>
          Drop points
          <input name="drop_points" defaultValue={data.settings.drop_points?.value} disabled={!data.can_edit} />
        </label>
        <label>
          Auto-logout (minutes)
          <input name="inactivity_minutes" defaultValue={data.settings.inactivity_minutes?.value} disabled={!data.can_edit} />
        </label>
        {data.can_edit && <button type="submit">Save numbers</button>}
      </form>

      <h3>Component weights by subject category</h3>
      <p className="hint">
        Defaults pending school confirmation. Compare with DepEd DO 8 s.2015 Table 4. Work Immersion / Research /
        Exhibit / Performance must be confirmed — do not treat the seed mix as official.
      </p>
      <form onSubmit={saveProfiles}>
        <table>
          <thead>
            <tr>
              <th>Profile</th>
              <th>WW %</th>
              <th>PT %</th>
              <th>QA %</th>
              <th>Confirmed</th>
              <th>Source note</th>
            </tr>
          </thead>
          <tbody>
            {data.weight_profiles.map((p, idx) => (
              <tr key={p.id}>
                <td>{p.name}</td>
                <td>
                  <input
                    value={p.written_work_pct}
                    disabled={!data.can_edit}
                    onChange={(e) => {
                      const next = [...data.weight_profiles];
                      next[idx] = { ...p, written_work_pct: e.target.value };
                      setData({ ...data, weight_profiles: next });
                    }}
                  />
                </td>
                <td>
                  <input
                    value={p.performance_task_pct}
                    disabled={!data.can_edit}
                    onChange={(e) => {
                      const next = [...data.weight_profiles];
                      next[idx] = { ...p, performance_task_pct: e.target.value };
                      setData({ ...data, weight_profiles: next });
                    }}
                  />
                </td>
                <td>
                  <input
                    value={p.quarterly_assessment_pct}
                    disabled={!data.can_edit}
                    onChange={(e) => {
                      const next = [...data.weight_profiles];
                      next[idx] = { ...p, quarterly_assessment_pct: e.target.value };
                      setData({ ...data, weight_profiles: next });
                    }}
                  />
                </td>
                <td>
                  <input
                    type="checkbox"
                    checked={!!p.confirmed}
                    disabled={!data.can_edit}
                    onChange={(e) => {
                      const next = [...data.weight_profiles];
                      next[idx] = { ...p, confirmed: e.target.checked ? 1 : 0 };
                      setData({ ...data, weight_profiles: next });
                    }}
                  />
                </td>
                <td className="hint">{p.source_note}</td>
              </tr>
            ))}
          </tbody>
        </table>
        {data.can_edit && <button type="submit">Save weights</button>}
      </form>

      <h3>Lock / unlock grading periods</h3>
      <table>
        <thead>
          <tr>
            <th>School year</th>
            <th>Quarter</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {data.locks.map((l) => (
            <tr key={`${l.school_year_id}-${l.quarter}`}>
              <td>{l.school_year}</td>
              <td>Q{l.quarter}</td>
              <td>{l.is_locked ? 'Locked' : 'Open'}</td>
              <td>
                {data.can_edit && (
                  <button type="button" className="ghost" onClick={() => toggleLock(l, !l.is_locked)}>
                    {l.is_locked ? 'Unlock' : 'Lock'}
                  </button>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      <h3>Transmutation table (DepEd Order No. 8, s. 2015 Appendix 2)</h3>
      <table>
        <thead>
          <tr>
            <th>Initial min</th>
            <th>Initial max</th>
            <th>Quarterly grade</th>
          </tr>
        </thead>
        <tbody>
          {data.transmutation_table.slice(0, 12).map((r) => (
            <tr key={r.id}>
              <td>{r.initial_min}</td>
              <td>{r.initial_max}</td>
              <td>{r.transmuted_grade}</td>
            </tr>
          ))}
        </tbody>
      </table>
      <p className="hint">{data.transmutation_table.length} bands stored. Registrar can replace the table via API PUT /admin/transmutation.</p>

      {data.can_edit && <PolicyPublisher token={token} />}
    </div>
  );
}

function PolicyPublisher({ token }: { token: string }) {
  const [bundle, setBundle] = useState<PolicyBundle | null>(null);
  const [doc, setDoc] = useState<PolicyDoc | null>(null);
  const [version, setVersion] = useState('');
  const [msg, setMsg] = useState('');

  useEffect(() => {
    api<PolicyBundle>('/legal/policies', token).then((r) => {
      setBundle(r);
      setDoc(r.documents[0] ?? null);
      setVersion(r.documents[0]?.version ?? '');
    });
  }, [token]);

  async function publish(e: FormEvent) {
    e.preventDefault();
    if (!doc) return;
    if (!window.confirm('Publishing a new version will require every user to accept the policies again. Continue?')) {
      return;
    }
    await api('/admin/policies', token, {
      method: 'PUT',
      body: JSON.stringify({
        document_code: doc.code,
        version,
        title: doc.title,
        last_updated: new Date().toISOString().slice(0, 10),
        sections: doc.sections,
      }),
    });
    setMsg(`Published ${doc.code} v${version}. Users will be asked to accept again.`);
  }

  return (
    <>
      <h3>Publish legal documents</h3>
      <p className="hint">
        Material changes must bump the version. Students, teachers, and staff will be re-prompted on next login.
      </p>
      {msg && <p className="ok">{msg}</p>}
      <form onSubmit={publish} className="filters">
        <label>
          Document
          <select
            value={doc?.code ?? ''}
            onChange={(e) => {
              const next = bundle?.documents.find((d) => d.code === e.target.value) ?? null;
              setDoc(next);
              setVersion(next?.version ?? '');
            }}
          >
            {(bundle?.documents ?? []).map((d) => (
              <option key={d.code} value={d.code}>
                {d.title} (v{d.version})
              </option>
            ))}
          </select>
        </label>
        <label>
          New version
          <input value={version} onChange={(e) => setVersion(e.target.value)} />
        </label>
        <button type="submit">Publish and require re-acceptance</button>
      </form>
    </>
  );
}
