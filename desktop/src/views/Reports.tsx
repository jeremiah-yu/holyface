import { useEffect, useState } from 'react';
import { api } from '../api';
import { toCsv } from '../compute';
import type { GridPayload, Section } from '../types';

type Missing = { full_name: string; lrn: string; item_label: string; component_type: string };

export default function Reports({
  token,
  section,
  quarter,
  grid,
}: {
  token: string;
  section: Section | null;
  quarter: number;
  grid: GridPayload | null;
}) {
  const [missing, setMissing] = useState<Missing[]>([]);

  useEffect(() => {
    if (!section) return;
    api<{ missing: Missing[] }>(
      `/grades/missing?class_subject_assignment_id=${section.class_subject_assignment_id}&quarter=${quarter}`,
      token
    ).then((r) => setMissing(r.missing));
  }, [token, section, quarter]);

  if (!section || !grid) return <div className="card">Select a class to print reports.</div>;

  async function saveCsv(name: string, rows: string[][]) {
    const csv = toCsv(rows);
    if (window.holyface?.exportFile) {
      await window.holyface.exportFile({ defaultName: name, content: csv, filters: [{ name: 'Excel CSV', extensions: ['csv'] }] });
    }
  }

  function printHtml(title: string, body: string) {
    const w = window.open('', '_blank', 'width=900,height=700');
    if (!w) return;
    w.document.write(`<!doctype html><html><head><title>${title}</title>
      <style>body{font-family:Segoe UI,sans-serif;color:#0b1f3a;padding:24px}
      table{width:100%;border-collapse:collapse;font-size:12px}
      th,td{border:1px solid #bbb;padding:5px;text-align:left}h1{font-size:18px}h2{font-size:14px}</style>
      </head><body>${body}</body></html>`);
    w.document.close();
    w.focus();
    w.print();
  }

  const cls = `G${section.grade_level} ${section.strand}-${section.section_code} · ${section.subject_title} · Q${quarter}`;

  return (
    <div className="card">
      <h2>Reports</h2>
      <p>{cls}</p>
      <div className="toolbar">
        <button
          type="button"
          onClick={() =>
            printHtml(
              'Class record',
              `<h1>Holy Face AMGS — Class Record</h1><h2>${cls}</h2>
               <table><thead><tr><th>Student number</th><th>Name</th>${grid.items.map((i) => `<th>${i.item_label}<br/>/${i.max_score}</th>`).join('')}
               <th>WW%</th><th>PT%</th><th>QA%</th><th>IG</th><th>QG</th></tr></thead><tbody>
               ${grid.students
                 .map((s) => {
                   const cells = grid.items
                     .map((i) => {
                       const c = s.cells[String(i.id)];
                       if (!c) return '<td></td>';
                       if (c.entry_status === 'absent') return '<td>ABS</td>';
                       if (c.entry_status === 'excused') return '<td>EXC</td>';
                       return `<td>${c.score ?? ''}</td>`;
                     })
                     .join('');
                   return `<tr><td>${s.lrn}</td><td>${s.full_name}</td>${cells}<td>${s.written_work_avg ?? ''}</td><td>${s.performance_task_avg ?? ''}</td><td>${s.quarterly_assessment_score ?? ''}</td><td>${s.initial_grade ?? ''}</td><td>${s.transmuted_grade ?? ''}</td></tr>`;
                 })
                 .join('')}</tbody></table>`
            )
          }
        >
          Print class record
        </button>
        <button
          type="button"
          className="ghost"
          onClick={() =>
            saveCsv('class-record.csv', [
              ['Student number', 'Name', ...grid.items.map((i) => `${i.item_label}/${i.max_score}`), 'WW%', 'PT%', 'QA%', 'IG', 'QG'],
              ...grid.students.map((s) => [
                s.lrn,
                s.full_name,
                ...grid.items.map((i) => {
                  const c = s.cells[String(i.id)];
                  if (!c) return '';
                  if (c.entry_status === 'absent') return 'ABS';
                  if (c.entry_status === 'excused') return 'EXC';
                  return c.score == null ? '' : String(c.score);
                }),
                String(s.written_work_avg ?? ''),
                String(s.performance_task_avg ?? ''),
                String(s.quarterly_assessment_score ?? ''),
                String(s.initial_grade ?? ''),
                String(s.transmuted_grade ?? ''),
              ]),
            ])
          }
        >
          Excel class record
        </button>
        <button
          type="button"
          onClick={() =>
            printHtml(
              'Report of grades',
              `<h1>Report of Grades</h1><h2>${cls}</h2>
               <table><thead><tr><th>Student number</th><th>Name</th><th>Q1</th><th>Q2</th><th>Sem 1</th><th>Q3</th><th>Q4</th><th>Sem 2</th><th>Final</th><th>Action</th></tr></thead><tbody>
               ${grid.students
                 .map(
                   (s) =>
                     `<tr><td>${s.lrn}</td><td>${s.full_name}</td><td>${s.q1 ?? ''}</td><td>${s.q2 ?? ''}</td><td>${s.sem1 ?? ''}</td><td>${s.q3 ?? ''}</td><td>${s.q4 ?? ''}</td><td>${s.sem2 ?? ''}</td><td>${s.final_rating ?? ''}</td><td>${s.action_taken ?? ''}</td></tr>`
                 )
                 .join('')}</tbody></table>`
            )
          }
        >
          Print report of grades
        </button>
        <button
          type="button"
          className="ghost"
          onClick={() =>
            printHtml(
              'Missing grades',
              `<h1>Missing grades</h1><h2>${cls}</h2>
               <table><thead><tr><th>Name</th><th>Student number</th><th>Component</th><th>Item</th></tr></thead><tbody>
               ${missing.map((m) => `<tr><td>${m.full_name}</td><td>${m.lrn}</td><td>${m.component_type}</td><td>${m.item_label}</td></tr>`).join('') || '<tr><td colspan="4">None</td></tr>'}
               </tbody></table>`
            )
          }
        >
          Missing-grades report
        </button>
      </div>
      <h3>Missing scores ({missing.length})</h3>
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Student number</th>
            <th>Item</th>
          </tr>
        </thead>
        <tbody>
          {missing.slice(0, 40).map((m, i) => (
            <tr key={i}>
              <td>{m.full_name}</td>
              <td>{m.lrn}</td>
              <td>
                {m.component_type}: {m.item_label}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
