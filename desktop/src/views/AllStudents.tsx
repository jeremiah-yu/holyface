import { useEffect, useState } from 'react';
import { api } from '../api';
import { toCsv } from '../compute';
import type { StudentRow } from '../types';
import { Avatar } from './Roster';

type Filters = {
  school_years: { id: number; label: string }[];
  strands: { code: string; name: string }[];
  sections: { section_code: string; grade_level: number }[];
};

export default function AllStudents({ token }: { token: string }) {
  const [filters, setFilters] = useState<Filters | null>(null);
  const [strand, setStrand] = useState('');
  const [grade, setGrade] = useState('');
  const [section, setSection] = useState('');
  const [q, setQ] = useState('');
  const [rows, setRows] = useState<StudentRow[]>([]);
  const [error, setError] = useState('');

  useEffect(() => {
    api<Filters>('/teachers/filters', token).then(setFilters).catch((e: Error) => setError(e.message));
  }, [token]);

  useEffect(() => {
    const params = new URLSearchParams();
    if (strand) params.set('strand', strand);
    if (grade) params.set('grade_level', grade);
    if (section) params.set('section', section);
    if (q) params.set('q', q);
    api<{ students: StudentRow[] }>(`/teachers/students?${params.toString()}`, token)
      .then((r) => setRows(r.students))
      .catch((e: Error) => setError(e.message));
  }, [token, strand, grade, section, q]);

  async function exportCsv() {
    const csv = toCsv([
      ['Student number', 'Name', 'Strand', 'Grade', 'Section', 'School year'],
      ...rows.map((s) => [s.lrn, s.full_name, s.strand ?? '', String(s.grade_level ?? ''), s.section_code ?? '', s.school_year ?? '']),
    ]);
    if (window.holyface?.exportFile) {
      await window.holyface.exportFile({
        defaultName: 'holyface-all-students.csv',
        content: csv,
        filters: [{ name: 'Excel CSV', extensions: ['csv'] }],
      });
    } else {
      download('holyface-all-students.csv', csv);
    }
  }

  function exportPdf() {
    const w = window.open('', '_blank', 'width=900,height=700');
    if (!w) return;
    w.document.write(`<!doctype html><html><head><title>All students</title>
      <style>body{font-family:Segoe UI,sans-serif;color:#0b1f3a}table{width:100%;border-collapse:collapse}
      th,td{border:1px solid #ccc;padding:6px;text-align:left}h1{font-size:18px}</style></head><body>
      <h1>Holy Face AMGS — All students</h1>
      <table><thead><tr><th>Student number</th><th>Name</th><th>Strand</th><th>Grade</th><th>Section</th></tr></thead><tbody>
      ${rows.map((s) => `<tr><td>${s.lrn}</td><td>${s.full_name}</td><td>${s.strand ?? ''}</td><td>${s.grade_level ?? ''}</td><td>${s.section_code ?? ''}</td></tr>`).join('')}
      </tbody></table></body></html>`);
    w.document.close();
    w.print();
  }

  return (
    <div className="card">
      <div className="toolbar">
        <h2>Show all students (Registrar / Admin)</h2>
        <button type="button" onClick={exportCsv}>
          Export Excel
        </button>
        <button type="button" className="ghost" onClick={exportPdf}>
          Export PDF
        </button>
      </div>
      <div className="filters">
        <select value={strand} onChange={(e) => setStrand(e.target.value)}>
          <option value="">All strands</option>
          {filters?.strands.map((s) => (
            <option key={s.code} value={s.code}>
              {s.code} — {s.name}
            </option>
          ))}
        </select>
        <select value={grade} onChange={(e) => setGrade(e.target.value)}>
          <option value="">All grade levels</option>
          <option value="11">Grade 11</option>
          <option value="12">Grade 12</option>
        </select>
        <select value={section} onChange={(e) => setSection(e.target.value)}>
          <option value="">All sections</option>
          {filters?.sections.map((s) => (
            <option key={`${s.grade_level}-${s.section_code}`} value={s.section_code}>
              G{s.grade_level} {s.section_code}
            </option>
          ))}
        </select>
        <input placeholder="Search name or student number" value={q} onChange={(e) => setQ(e.target.value)} />
      </div>
      {error && <p className="error">{error}</p>}
      <table>
        <thead>
          <tr>
            <th>Photo</th>
            <th>Student number</th>
            <th>Name</th>
            <th>Strand</th>
            <th>Grade</th>
            <th>Section</th>
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
              <td>{s.strand}</td>
              <td>{s.grade_level}</td>
              <td>{s.section_code}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function download(name: string, content: string) {
  const blob = new Blob([content], { type: 'text/csv;charset=utf-8' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = name;
  a.click();
}
