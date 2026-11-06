import { useEffect, useState } from 'react';
import { api } from '../api';
import { toCsv } from '../compute';
import { calendarForGrade } from '../calendar';

type AdvisorySection = {
  id: number;
  section_code: string;
  grade_level: number;
  strand: string;
  school_year: string;
  adviser_name?: string;
  student_count: number;
};

type Desk = {
  section: AdvisorySection;
  subjects: {
    class_subject_assignment_id: number;
    title: string;
    teacher_name: string;
    submitted_count: string | number;
    student_count: string | number;
  }[];
  cards: { generated: number; released: number; students: number };
};

type SogRow = {
  enrollment_id: number;
  full_name: string;
  student_number: string;
  date_of_birth: string | null;
  age: number | null;
  general_average: number | null;
  complete: boolean;
  honor: string | null;
  rank: number | null;
  subjects: { title: string; final_rating: number | null; submitted: boolean }[];
};

type Card = {
  card_number: string;
  issued_at?: string;
  released_at?: string | null;
  general_average: number;
  remarks: string;
  honor?: string | null;
  section_rank?: number | null;
  school: { name: string; address: string };
  student: {
    full_name: string;
    student_number: string;
    strand_code: string;
    grade_level: number;
    section_code: string;
    school_year: string;
    date_of_birth?: string | null;
    age?: number | null;
    adviser_name?: string | null;
  };
  calendar?: { periods: { id: number; code: string; label: string }[] };
  subjects: {
    title: string;
    q1: number | null;
    q2: number | null;
    q3: number | null;
    q4: number | null;
    sem1: number | null;
    sem2: number | null;
    final_rating: number | null;
    periods?: { code: string; grade: number | null }[];
  }[];
};

export default function Advisory({ token }: { token: string }) {
  const [sections, setSections] = useState<AdvisorySection[]>([]);
  const [sectionId, setSectionId] = useState<number | null>(null);
  const [desk, setDesk] = useState<Desk | null>(null);
  const [sog, setSog] = useState<SogRow[]>([]);
  const [msg, setMsg] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    api<{ sections: AdvisorySection[] }>('/teachers/advisory', token)
      .then((r) => {
        setSections(r.sections);
        if (r.sections[0]) setSectionId((id) => id ?? r.sections[0].id);
      })
      .catch((e: Error) => setError(e.message));
  }, [token]);

  async function load(id: number) {
    setError('');
    const [d, s] = await Promise.all([
      api<Desk>(`/adviser/desk?class_section_id=${id}`, token),
      api<{ rows: SogRow[] }>(`/adviser/sog?class_section_id=${id}`, token),
    ]);
    setDesk(d);
    setSog(s.rows);
  }

  useEffect(() => {
    if (sectionId) void load(sectionId).catch((e: Error) => setError(e.message));
  }, [sectionId, token]);

  async function generate() {
    if (!sectionId) return;
    const r = await api<{ generated: number; waiting: number }>('/adviser/generate-cards', token, {
      method: 'POST',
      body: JSON.stringify({ class_section_id: sectionId }),
    });
    setMsg(`Generated ${r.generated} card(s). ${r.waiting} still waiting for subject finals.`);
    await load(sectionId);
  }

  async function release() {
    if (!sectionId) return;
    if (!window.confirm('Release official cards to students? They will see them on the mobile app.')) return;
    const r = await api<{ released: number }>('/adviser/release-cards', token, {
      method: 'POST',
      body: JSON.stringify({ class_section_id: sectionId }),
    });
    setMsg(`Released ${r.released} card(s). Students can open them now.`);
    await load(sectionId);
  }

  async function exportSog() {
    if (!desk) return;
    const titles = sog[0]?.subjects.map((s) => s.title) ?? [];
    const csv = toCsv([
      ['Rank', 'Student number', 'Name', 'Birthday', 'Age', ...titles, 'General average', 'Honor'],
      ...sog.map((row) => [
        row.rank == null ? '' : String(row.rank),
        row.student_number,
        row.full_name,
        row.date_of_birth ?? '',
        row.age == null ? '' : String(row.age),
        ...row.subjects.map((s) => (s.final_rating == null ? '' : String(s.final_rating))),
        row.general_average == null ? '' : String(row.general_average),
        row.honor ?? '',
      ]),
    ]);
    const name = `SOG-G${desk.section.grade_level}-${desk.section.strand}-${desk.section.section_code}.csv`;
    if (window.holyface?.exportFile) {
      await window.holyface.exportFile({ defaultName: name, content: csv, filters: [{ name: 'Excel CSV', extensions: ['csv'] }] });
    } else {
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = name;
      a.click();
    }
  }

  async function printCards() {
    if (!sectionId || !desk) return;
    const r = await api<{ cards: Card[] }>(`/adviser/cards?class_section_id=${sectionId}`, token);
    const html = r.cards.map((card) => cardHtml(card)).join('<div class="break"></div>');
    const w = window.open('', '_blank', 'width=900,height=700');
    if (!w) return;
    w.document.write(`<!doctype html><html><head><title>Official cards</title>
      <style>
        body{font-family:Georgia,serif;color:#0b1f3a;margin:0}
        .card{border:3px solid #c9a227;padding:18px 22px;margin:16px}
        h1{font-size:15px;margin:0;text-align:center}
        h2{font-size:13px;margin:6px 0 12px;text-align:center;color:#c9a227}
        table{width:100%;border-collapse:collapse;font-size:11px}
        th,td{border:1px solid #bbb;padding:4px 6px;text-align:center}
        th:first-child,td:first-child{text-align:left}
        .meta{font-size:12px;margin:8px 0}
        .sign{display:flex;justify-content:space-between;margin-top:28px;font-size:12px}
        .break{page-break-after:always}
      </style></head><body>${html || '<p>No generated cards yet.</p>'}</body></html>`);
    w.document.close();
    w.focus();
    w.print();
  }

  function printSog() {
    if (!desk) return;
    const titles = sog[0]?.subjects.map((s) => s.title) ?? [];
    const rows = sog
      .map(
        (row) =>
          `<tr><td>${row.rank ?? ''}</td><td>${row.student_number}</td><td>${row.full_name}</td>${row.subjects
            .map((s) => `<td>${s.final_rating ?? ''}</td>`)
            .join('')}<td>${row.general_average ?? ''}</td><td>${row.honor ?? ''}</td></tr>`
      )
      .join('');
    const w = window.open('', '_blank', 'width=1000,height=700');
    if (!w) return;
    w.document.write(`<!doctype html><html><head><title>Summary of Grades</title>
      <style>body{font-family:Segoe UI,sans-serif;color:#0b1f3a;padding:20px}
      table{width:100%;border-collapse:collapse;font-size:11px}
      th,td{border:1px solid #bbb;padding:4px}h1{font-size:18px}</style></head><body>
      <h1>Summary of Grades</h1>
      <p>G${desk.section.grade_level} ${desk.section.strand}-${desk.section.section_code} · ${desk.section.school_year}</p>
      <table><thead><tr><th>Rank</th><th>Student no.</th><th>Name</th>${titles
        .map((t) => `<th>${t}</th>`)
        .join('')}<th>GA</th><th>Honor</th></tr></thead><tbody>${rows}</tbody></table>
      </body></html>`);
    w.document.close();
    w.focus();
    w.print();
  }

  if (!sections.length) {
    return (
      <div className="card">
        <h2>Advisory</h2>
        <p>{error || 'You are not assigned as a class adviser.'}</p>
      </div>
    );
  }

  return (
    <div className="card">
      <h2>Advisory desk</h2>
      <p className="hint">
        Subject teachers encode. You generate the official cards and release them at PTC. The Registrar keeps the final
        records.
      </p>
      {msg && <p className="ok">{msg}</p>}
      {error && <p className="error">{error}</p>}
      <div className="filters">
        <label>
          Advisory class
          <select value={sectionId ?? ''} onChange={(e) => setSectionId(Number(e.target.value))}>
            {sections.map((s) => (
              <option key={s.id} value={s.id}>
                G{s.grade_level} {s.strand}-{s.section_code} ({s.student_count})
              </option>
            ))}
          </select>
        </label>
      </div>
      {desk && (
        <>
          <p>
            {desk.cards.generated} generated · {desk.cards.released} released · {desk.cards.students} students ·{' '}
            {calendarForGrade(desk.section.grade_level).mode === 'terms' ? 'Grade 11 terms' : 'Grade 12 quarters'}
          </p>
          <div className="toolbar">
            <button type="button" onClick={() => void generate()}>
              Generate cards
            </button>
            <button type="button" onClick={() => void release()}>
              Release at PTC
            </button>
            <button type="button" className="ghost" onClick={() => void printCards()}>
              Print cards
            </button>
            <button type="button" className="ghost" onClick={() => void exportSog()}>
              Excel SOG
            </button>
            <button type="button" className="ghost" onClick={printSog}>
              Print SOG
            </button>
          </div>
          <h3>Subject teachers</h3>
          <table>
            <thead>
              <tr>
                <th>Subject</th>
                <th>Teacher</th>
                <th>Finals submitted</th>
              </tr>
            </thead>
            <tbody>
              {desk.subjects.map((s) => (
                <tr key={s.class_subject_assignment_id}>
                  <td>{s.title}</td>
                  <td>{s.teacher_name}</td>
                  <td>
                    {s.submitted_count}/{s.student_count}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          <h3>Summary of Grades</h3>
          <div className="sheet-scroll">
            <table>
              <thead>
                <tr>
                  <th>Rank</th>
                  <th>Student no.</th>
                  <th>Name</th>
                  {(sog[0]?.subjects ?? []).map((s) => (
                    <th key={s.title}>{s.title}</th>
                  ))}
                  <th>GA</th>
                  <th>Honor</th>
                </tr>
              </thead>
              <tbody>
                {sog.map((row) => (
                  <tr key={row.enrollment_id}>
                    <td>{row.rank ?? ''}</td>
                    <td>{row.student_number}</td>
                    <td>{row.full_name}</td>
                    {row.subjects.map((s) => (
                      <td key={s.title}>{s.final_rating ?? '—'}</td>
                    ))}
                    <td>{row.general_average ?? '—'}</td>
                    <td>{row.honor ?? ''}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}

function cardHtml(card: Card): string {
  const periods = card.calendar?.periods ?? [];
  const heads = ['Subject', ...periods.map((p) => p.code), 'Final'].map((h) => `<th>${h}</th>`).join('');
  const rows = card.subjects
    .map((s) => {
      const grades =
        s.periods?.map((p) => `<td>${p.grade ?? ''}</td>`).join('') ??
        `<td>${s.q1 ?? ''}</td><td>${s.q2 ?? ''}</td><td>${s.q3 ?? ''}</td>`;
      return `<tr><td>${s.title}</td>${grades}<td>${s.final_rating ?? ''}</td></tr>`;
    })
    .join('');
  return `<section class="card">
    <h1>${card.school.name}</h1>
    <h2>OFFICIAL GRADING CARD · ${card.card_number}</h2>
    <div class="meta">
      <strong>${card.student.full_name}</strong><br/>
      Student no. ${card.student.student_number} · G${card.student.grade_level} ${card.student.strand_code}-${card.student.section_code}<br/>
      ${card.student.school_year}
      ${card.student.date_of_birth ? ` · Birthday ${card.student.date_of_birth}` : ''}
      ${card.student.age != null ? ` · Age ${card.student.age}` : ''}
    </div>
    <table><thead><tr>${heads}</tr></thead><tbody>${rows}</tbody></table>
    <p><strong>General average:</strong> ${card.general_average} · ${card.remarks}${card.honor ? ` · ${card.honor}` : ''}${
      card.section_rank ? ` · Rank ${card.section_rank}` : ''
    }</p>
    <div class="sign"><span>Adviser: ${card.student.adviser_name ?? '________________'}</span><span>Parent / Guardian</span></div>
  </section>`;
}
