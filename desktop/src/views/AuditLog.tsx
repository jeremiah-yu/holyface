import { useEffect, useState } from 'react';
import { api } from '../api';

type SysEntry = {
  id: number;
  action: string;
  entity_type: string | null;
  entity_id: string | null;
  created_at: string;
  actor?: string | null;
  ip_address?: string | null;
};

type GradeEntry = {
  id: number;
  quarter: number;
  field_name: string;
  old_value: string | null;
  new_value: string | null;
  created_at: string;
  actor: string;
  student_name: string;
  student_number: string;
  subject: string;
};

export default function AuditLog({ token }: { token: string }) {
  const [tab, setTab] = useState<'system' | 'grades'>('system');
  const [page, setPage] = useState(1);
  const [sys, setSys] = useState<{ total: number; entries: SysEntry[] } | null>(null);
  const [grades, setGrades] = useState<{ total: number; entries: GradeEntry[] } | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    setError('');
    if (tab === 'system') {
      api<{ total: number; entries: SysEntry[] }>(`/admin/audit?page=${page}&limit=40`, token)
        .then(setSys)
        .catch((e: Error) => setError(e.message));
    } else {
      api<{ total: number; entries: GradeEntry[] }>(`/admin/grade-audit?page=${page}&limit=40`, token)
        .then(setGrades)
        .catch((e: Error) => setError(e.message));
    }
  }, [token, tab, page]);

  const total = tab === 'system' ? sys?.total ?? 0 : grades?.total ?? 0;

  return (
    <div className="card">
      <div className="toolbar">
        <h2>Audit logs</h2>
        <button type="button" className={tab === 'system' ? '' : 'ghost'} onClick={() => { setTab('system'); setPage(1); }}>
          System
        </button>
        <button type="button" className={tab === 'grades' ? '' : 'ghost'} onClick={() => { setTab('grades'); setPage(1); }}>
          Grade changes
        </button>
      </div>
      <p className="hint">Who did what, and when. Grade changes store the old and new values. Student names stay on the school server.</p>
      {error && <p className="error">{error}</p>}

      {tab === 'system' && (
        <table>
          <thead>
            <tr>
              <th>When</th>
              <th>Who</th>
              <th>Action</th>
              <th>Record</th>
            </tr>
          </thead>
          <tbody>
            {(sys?.entries ?? []).map((r) => (
              <tr key={r.id}>
                <td>{r.created_at}</td>
                <td>{r.actor || '—'}</td>
                <td>{r.action.replace(/_/g, ' ')}</td>
                <td className="hint">
                  {[r.entity_type, r.entity_id].filter(Boolean).join(' #')}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {tab === 'grades' && (
        <table>
          <thead>
            <tr>
              <th>When</th>
              <th>Teacher</th>
              <th>Student</th>
              <th>Subject</th>
              <th>Field</th>
              <th>Old</th>
              <th>New</th>
            </tr>
          </thead>
          <tbody>
            {(grades?.entries ?? []).map((r) => (
              <tr key={r.id}>
                <td>{r.created_at}</td>
                <td>{r.actor}</td>
                <td>
                  {r.student_name}
                  <div className="hint">{r.student_number}</div>
                </td>
                <td>
                  {r.subject} · Q{r.quarter}
                </td>
                <td>{r.field_name}</td>
                <td>{r.old_value ?? '—'}</td>
                <td>{r.new_value ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      <div className="filters">
        <button type="button" className="ghost" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
          Previous
        </button>
        <span className="hint">
          Page {page} · {total} entries
        </span>
        <button type="button" className="ghost" disabled={page * 40 >= total} onClick={() => setPage((p) => p + 1)}>
          Next
        </button>
      </div>
    </div>
  );
}
