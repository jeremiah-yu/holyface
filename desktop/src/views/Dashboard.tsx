import { useEffect, useState } from 'react';
import { api } from '../api';
import type { Section } from '../types';

type Dash = {
  summary: {
    students: number;
    with_grades: number;
    average: number | null;
    highest: number | null;
    lowest: number | null;
    passing_rate: number | null;
    passing_mark: number;
  };
  distribution: Record<string, number>;
};

export default function Dashboard({ token, section, quarter }: { token: string; section: Section | null; quarter: number }) {
  const [data, setData] = useState<Dash | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!section) return;
    api<Dash>(
      `/grades/dashboard?class_subject_assignment_id=${section.class_subject_assignment_id}&quarter=${quarter}`,
      token
    )
      .then(setData)
      .catch((e: Error) => setError(e.message));
  }, [token, section, quarter]);

  if (!section) return <div className="card">Select a class first.</div>;
  if (error) return <div className="card error">{error}</div>;
  if (!data) return <div className="card">Loading dashboard…</div>;

  const maxBar = Math.max(1, ...Object.values(data.distribution));

  return (
    <div className="card">
      <h2>
        Overview · G{section.grade_level} {section.strand}-{section.section_code} · {section.subject_title} · Q{quarter}
      </h2>
      <div className="stat-row">
        <Stat label="Average" value={data.summary.average ?? '—'} />
        <Stat label="Highest" value={data.summary.highest ?? '—'} />
        <Stat label="Lowest" value={data.summary.lowest ?? '—'} />
        <Stat label="Passing rate" value={data.summary.passing_rate != null ? `${data.summary.passing_rate}%` : '—'} />
        <Stat label="Graded" value={`${data.summary.with_grades}/${data.summary.students}`} />
      </div>
      <h3>Distribution</h3>
      <div className="bars">
        {Object.entries(data.distribution).map(([label, n]) => (
          <div key={label} className="bar-row">
            <span>{label}</span>
            <div className="bar-track">
              <div className="bar-fill" style={{ width: `${(n / maxBar) * 100}%` }} />
            </div>
            <strong>{n}</strong>
          </div>
        ))}
      </div>
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="stat">
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}
