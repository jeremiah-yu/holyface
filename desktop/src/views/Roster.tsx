import { useMemo, useState } from 'react';
import type { GridPayload, GridStudent, Section } from '../types';

type SortKey = 'full_name' | 'lrn' | 'transmuted_grade';

export default function Roster({
  section,
  quarter,
  grid,
  onOpenGrid,
}: {
  section: Section | null;
  quarter: number;
  grid: GridPayload | null;
  onOpenGrid: () => void;
}) {
  const [q, setQ] = useState('');
  const [sort, setSort] = useState<SortKey>('full_name');
  const [dir, setDir] = useState<1 | -1>(1);

  const rows = useMemo(() => {
    const list = [...(grid?.students ?? [])];
    const needle = q.trim().toLowerCase();
    const filtered = needle
      ? list.filter((s) => s.full_name.toLowerCase().includes(needle) || s.lrn.includes(needle))
      : list;
    filtered.sort((a, b) => {
      const av = a[sort] ?? '';
      const bv = b[sort] ?? '';
      return String(av).localeCompare(String(bv), undefined, { numeric: true }) * dir;
    });
    return filtered;
  }, [grid, q, sort, dir]);

  function toggle(key: SortKey) {
    if (sort === key) setDir((d) => (d === 1 ? -1 : 1));
    else {
      setSort(key);
      setDir(1);
    }
  }

  if (!section) return <div className="card">Select school year, quarter, strand, section, and subject above.</div>;

  return (
    <div className="card">
      <div className="toolbar">
        <h2>
          Class list · G{section.grade_level} {section.strand}-{section.section_code} · {section.subject_title} · Q
          {quarter}
        </h2>
        <input placeholder="Search name or student number" value={q} onChange={(e) => setQ(e.target.value)} />
        <button type="button" onClick={onOpenGrid}>
          Enter grades
        </button>
      </div>
      {grid?.locked && <p className="warn">This quarter is locked. Registrar approval is required to edit.</p>}
      <table>
        <thead>
          <tr>
            <th>Photo</th>
            <th>
              <button type="button" className="link" onClick={() => toggle('lrn')}>
                Student number
              </button>
            </th>
            <th>
              <button type="button" className="link" onClick={() => toggle('full_name')}>
                Name
              </button>
            </th>
            <th>
              <button type="button" className="link" onClick={() => toggle('transmuted_grade')}>
                Current grade
              </button>
            </th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((s) => (
            <tr key={s.enrollment_id}>
              <td>
                <Avatar name={s.full_name} />
              </td>
              <td>{s.lrn}</td>
              <td>{s.full_name}</td>
              <td>{s.transmuted_grade ?? '—'}</td>
              <td>{s.remarks ?? '—'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export function Avatar({ name }: { name: string }) {
  const initials = name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0])
    .join('')
    .toUpperCase();
  return <span className="avatar">{initials}</span>;
}

export function studentSearch(list: GridStudent[], q: string): GridStudent[] {
  const needle = q.trim().toLowerCase();
  if (!needle) return list;
  return list.filter((s) => s.full_name.toLowerCase().includes(needle) || s.lrn.includes(needle));
}
