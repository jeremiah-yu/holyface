import { useEffect, useState } from 'react';
import { api } from '../api';
import Files from './Files';

type OverviewPayload = {
  counts: {
    students: number;
    teachers: number;
    admins: number;
    active_users: number;
    files: number;
    file_bytes: number;
    locked_periods: number;
    open_periods: number;
  };
};

function formatBytes(n: number): string {
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
  return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

export default function Overview({ token }: { token: string }) {
  const [data, setData] = useState<OverviewPayload | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api<OverviewPayload>('/admin/overview', token)
      .then(setData)
      .catch((e: Error) => setError(e.message));
  }, [token]);

  if (!data) return <div className="card">{error || 'Loading registrar overview…'}</div>;

  const c = data.counts;
  return (
    <>
    <div className="card">
      <h2>System overview</h2>
      <p className="hint">Accounts, file vault, and grading-period locks for Holy Face AMGS.</p>
      {error && <p className="error">{error}</p>}
      <div className="stat-row">
        <div className="stat">
          <span>Students</span>
          <strong>{c.students}</strong>
        </div>
        <div className="stat">
          <span>Teachers</span>
          <strong>{c.teachers}</strong>
        </div>
        <div className="stat">
          <span>Active accounts</span>
          <strong>{c.active_users}</strong>
        </div>
        <div className="stat">
          <span>Vault files</span>
          <strong>{c.files}</strong>
        </div>
        <div className="stat">
          <span>Vault size</span>
          <strong>{formatBytes(c.file_bytes)}</strong>
        </div>
        <div className="stat">
          <span>Locked periods</span>
          <strong>{c.locked_periods}</strong>
        </div>
        <div className="stat">
          <span>Open periods</span>
          <strong>{c.open_periods}</strong>
        </div>
      </div>
    </div>
    <Files token={token} />
    </>
  );
}
