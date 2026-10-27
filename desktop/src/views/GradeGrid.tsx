import { ClipboardEvent, KeyboardEvent, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { api, ApiError } from '../api';
import { initialGrade, parseTsv, percentageScore, validateScore } from '../compute';
import type { Cell, GradeItem, GridPayload, GridStudent, Section } from '../types';

type Focus = { row: number; col: number };

const TYPES: { key: GradeItem['component_type']; label: string; short: string; group: string }[] = [
  { key: 'written_work', label: 'Written Work', short: '+ WW', group: 'ww' },
  { key: 'performance_task', label: 'Performance Task', short: '+ PT', group: 'pt' },
  { key: 'quarterly_assessment', label: 'Quarterly Assessment', short: '+ QA', group: 'qa' },
];

export default function GradeGrid({
  token,
  section,
  quarter,
  grid,
  setGrid,
  locked,
  onOfflineSave,
  role,
}: {
  token: string;
  section: Section;
  quarter: number;
  grid: GridPayload;
  setGrid: (g: GridPayload) => void;
  locked: boolean;
  onOfflineSave: (body: unknown) => void;
  role: string;
}) {
  const items = grid.items;
  const [focus, setFocus] = useState<Focus>({ row: 0, col: 0 });
  const [drafts, setDrafts] = useState<Record<string, string>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [msg, setMsg] = useState('');
  const dirty = useRef<Record<string, { enrollment_id: number; grade_item_id: number; score: number | null; entry_status: Cell['entry_status']; client_updated_at: string }>>({});
  const timer = useRef<number | null>(null);
  const readOnly = locked && role !== 'admin';

  const colCount = items.length;

  useEffect(() => {
    return () => {
      if (timer.current) window.clearTimeout(timer.current);
    };
  }, []);

  useLayoutEffect(() => {
    const el = document.querySelector<HTMLInputElement>(`input[data-cell="${focus.row}-${focus.col}"]`);
    if (el && document.activeElement !== el) el.focus();
  }, [focus]);

  function cellKey(enroll: number, itemId: number) {
    return `${enroll}:${itemId}`;
  }

  function display(student: GridStudent, item: GradeItem): string {
    const k = cellKey(student.enrollment_id, item.id);
    if (k in drafts) return drafts[k];
    const cell = student.cells[String(item.id)];
    if (!cell) return '';
    if (cell.entry_status === 'absent') return 'ABS';
    if (cell.entry_status === 'excused') return 'EXC';
    return cell.score == null ? '' : String(cell.score);
  }

  function scheduleFlush() {
    if (timer.current) window.clearTimeout(timer.current);
    timer.current = window.setTimeout(() => {
      void flush();
    }, 700);
  }

  async function flush() {
    const cells = Object.values(dirty.current);
    if (!cells.length) return;
    dirty.current = {};
    const body = {
      class_subject_assignment_id: section.class_subject_assignment_id,
      quarter,
      cells,
    };
    try {
      const res = await api<{ conflicts?: unknown[]; errors?: { message: string }[] }>('/grades/batch', token, {
        method: 'POST',
        body: JSON.stringify(body),
      });
      if (res.conflicts && res.conflicts.length) {
        setMsg('Newer score on the server — reloaded.');
      } else if (res.errors && res.errors.length) {
        setMsg(res.errors[0].message);
      } else {
        setMsg('Saved');
      }
      const fresh = await api<GridPayload>(
        `/grades/grid?class_subject_assignment_id=${section.class_subject_assignment_id}&quarter=${quarter}`,
        token
      );
      setGrid(fresh);
      setDrafts({});
    } catch (err) {
      if (err instanceof ApiError && err.status === 0) {
        onOfflineSave(body);
        setMsg('Offline — queued');
        return;
      }
      setMsg((err as Error).message);
      for (const c of cells) dirty.current[cellKey(c.enrollment_id, c.grade_item_id)] = c;
    }
  }

  function commit(student: GridStudent, item: GradeItem, raw: string) {
    const k = cellKey(student.enrollment_id, item.id);
    const max = Number(item.max_score);
    const upper = raw.trim().toUpperCase();
    let status: Cell['entry_status'] = 'scored';
    let score: number | null = null;
    if (upper === 'A' || upper === 'ABS' || upper === 'ABSENT') {
      status = 'absent';
    } else if (upper === 'E' || upper === 'EXC' || upper === 'EXCUSED') {
      status = 'excused';
    } else {
      const v = validateScore(raw, max);
      if (!v.ok) {
        setErrors((e) => ({ ...e, [k]: v.error ?? 'Invalid' }));
        return;
      }
      score = v.score;
      setErrors((e) => {
        const n = { ...e };
        delete n[k];
        return n;
      });
    }
    setDrafts((d) => ({ ...d, [k]: status === 'absent' ? 'ABS' : status === 'excused' ? 'EXC' : raw }));
    const students = grid.students.map((s) => {
      if (s.enrollment_id !== student.enrollment_id) return s;
      const cells = { ...s.cells, [String(item.id)]: { ...(s.cells[String(item.id)] ?? { component_id: 0, max_score: max, recorded_at: null }), score, entry_status: status, max_score: max } };
      const ww = percentageScore(items, cells, 'written_work');
      const pt = percentageScore(items, cells, 'performance_task');
      const qa = percentageScore(items, cells, 'quarterly_assessment');
      return {
        ...s,
        cells,
        written_work_avg: ww != null ? String(ww) : null,
        performance_task_avg: pt != null ? String(pt) : null,
        quarterly_assessment_score: qa != null ? String(qa) : null,
        initial_grade: (() => {
          const ig = initialGrade(ww, pt, qa, grid.weights);
          return ig != null ? String(ig) : null;
        })(),
      };
    });
    setGrid({ ...grid, students });
    dirty.current[k] = {
      enrollment_id: student.enrollment_id,
      grade_item_id: item.id,
      score,
      entry_status: status,
      client_updated_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
    };
    scheduleFlush();
  }

  function move(row: number, col: number) {
    const r = Math.max(0, Math.min(grid.students.length - 1, row));
    const c = Math.max(0, Math.min(Math.max(0, colCount - 1), col));
    setFocus({ row: r, col: c });
  }

  function onKey(e: KeyboardEvent<HTMLInputElement>, row: number, col: number, student: GridStudent, item: GradeItem) {
    if (e.key === 'ArrowDown' || e.key === 'Enter') {
      e.preventDefault();
      commit(student, item, (e.target as HTMLInputElement).value);
      move(row + 1, col);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      commit(student, item, (e.target as HTMLInputElement).value);
      move(row - 1, col);
    } else if (e.key === 'ArrowRight' && (e.ctrlKey || (e.target as HTMLInputElement).selectionStart === (e.target as HTMLInputElement).value.length)) {
      e.preventDefault();
      commit(student, item, (e.target as HTMLInputElement).value);
      move(row, col + 1);
    } else if (e.key === 'ArrowLeft' && (e.ctrlKey || (e.target as HTMLInputElement).selectionStart === 0)) {
      e.preventDefault();
      commit(student, item, (e.target as HTMLInputElement).value);
      move(row, col - 1);
    } else if (e.key === 'Tab') {
      e.preventDefault();
      commit(student, item, (e.target as HTMLInputElement).value);
      move(row, col + (e.shiftKey ? -1 : 1));
    }
  }

  function onPaste(e: ClipboardEvent<HTMLDivElement>) {
    if (readOnly) return;
    const text = e.clipboardData.getData('text/plain');
    if (!text.includes('\t') && !text.includes('\n')) return;
    e.preventDefault();
    const table = parseTsv(text);
    table.forEach((line, ri) => {
      line.forEach((val, ci) => {
        const student = grid.students[focus.row + ri];
        const item = items[focus.col + ci];
        if (student && item) commit(student, item, val);
      });
    });
  }

  async function addItem(type: GradeItem['component_type']) {
    const label = window.prompt(`Column name`, type === 'written_work' ? 'Quiz' : type === 'performance_task' ? 'Task' : 'Exam');
    if (!label) return;
    const maxRaw = window.prompt('Highest possible score', '100');
    const max = Number(maxRaw);
    if (!maxRaw || !Number.isFinite(max) || max <= 0) return;
    await api('/grades/items', token, {
      method: 'POST',
      body: JSON.stringify({
        class_subject_assignment_id: section.class_subject_assignment_id,
        quarter,
        component_type: type,
        item_label: label,
        max_score: max,
      }),
    });
    const fresh = await api<GridPayload>(
      `/grades/grid?class_subject_assignment_id=${section.class_subject_assignment_id}&quarter=${quarter}`,
      token
    );
    setGrid(fresh);
  }

  async function submitFinals() {
    if (!window.confirm('Submit final grades for this subject? An official grading card is issued only after every subject of the student has been submitted.')) {
      return;
    }
    const res = await api<{ report_cards?: { issued: number; waiting: number } }>('/grades/submit-final', token, {
      method: 'POST',
      body: JSON.stringify({ class_subject_assignment_id: section.class_subject_assignment_id, confirmed: true }),
    });
    const cards = res.report_cards;
    if (cards) {
      setMsg(
        `Finals submitted. Official cards issued: ${cards.issued}. Still waiting on other subjects: ${cards.waiting}.`
      );
    } else {
      setMsg('Final grades submitted.');
    }
  }

  const groups = useMemo(() => {
    return TYPES.map((t) => ({ ...t, items: items.filter((i) => i.component_type === t.key) }));
  }, [items]);

  const focusedStudent = grid.students[focus.row];
  const focusedItem = items[focus.col];
  const formulaName = focusedItem && focusedStudent ? `${focusedStudent.full_name}` : '';
  const formulaValue = focusedItem && focusedStudent ? `${focusedItem.item_label}  ${display(focusedStudent, focusedItem)}` : '';

  return (
    <div className="sheet" onPaste={onPaste}>
      <div className="sheet-toolbar">
        <strong>
          {section.subject_title} · G{section.grade_level} {section.strand}-{section.section_code}
        </strong>
        <span className="sheet-weights">
          WW {grid.weights.written_work_pct}% · PT {grid.weights.performance_task_pct}% · QA {grid.weights.quarterly_assessment_pct}%
        </span>
        <span className="sheet-toolbar-spacer" />
        {!readOnly &&
          TYPES.map((t) => (
            <button key={t.key} type="button" className="ghost" onClick={() => addItem(t.key)}>
              {t.short}
            </button>
          ))}
        <button type="button" onClick={submitFinals}>
          Submit finals
        </button>
      </div>
      {readOnly && <p className="warn sheet-banner">This quarter is locked. Ask the Registrar to unlock it.</p>}
      <div className="formula-bar">
        <span className="formula-name" title={formulaName}>
          {formulaName || 'Cell'}
        </span>
        <span className="formula-fx">fx</span>
        <span className="formula-value">{formulaValue}</span>
        {msg && <span className="formula-msg">{msg}</span>}
      </div>
      <div className="sheet-scroll">
        <table className="sheet-grid">
          <thead>
            <tr>
              <th className="col-num sticky-head sticky-col col-a" rowSpan={2}>
                #
              </th>
              <th className="sticky-head sticky-col col-b" rowSpan={2}>
                Student No.
              </th>
              <th className="sticky-head sticky-col col-c" rowSpan={2}>
                Name
              </th>
              {groups.map((g) => (
                <th key={g.key} className={`group-${g.group}`} colSpan={Math.max(1, g.items.length)}>
                  {g.label}
                </th>
              ))}
              <th className="group-out" rowSpan={2}>
                WW%
              </th>
              <th className="group-out" rowSpan={2}>
                PT%
              </th>
              <th className="group-out" rowSpan={2}>
                QA%
              </th>
              <th className="group-out" rowSpan={2}>
                IG
              </th>
              <th className="group-out" rowSpan={2}>
                QG
              </th>
            </tr>
            <tr>
              {groups.map((g) =>
                g.items.length ? (
                  g.items.map((i) => (
                    <th key={i.id} className={`group-${g.group} item-head`} title={`Highest possible score ${i.max_score}`}>
                      {i.item_label}
                      <span className="hps">/{i.max_score}</span>
                    </th>
                  ))
                ) : (
                  <th key={g.key} className={`group-${g.group} muted`}>
                    —
                  </th>
                )
              )}
            </tr>
          </thead>
          <tbody>
            {grid.students.map((s, row) => {
              let col = -1;
              return (
                <tr key={s.enrollment_id}>
                  <td className="col-num sticky-col col-a">{row + 1}</td>
                  <td className="sticky-col col-b">{s.lrn}</td>
                  <td className="sticky-col col-c name-cell">{s.full_name}</td>
                  {groups.map((g) =>
                    g.items.length ? (
                      g.items.map((item) => {
                        col += 1;
                        const myCol = col;
                        const k = cellKey(s.enrollment_id, item.id);
                        const active = focus.row === row && focus.col === myCol;
                        return (
                          <td key={item.id} className={`${errors[k] ? 'bad' : ''} ${active ? 'active' : ''}`}>
                            <input
                              data-cell={`${row}-${myCol}`}
                              disabled={readOnly}
                              value={display(s, item)}
                              onChange={(e) => setDrafts((d) => ({ ...d, [k]: e.target.value }))}
                              onFocus={() => setFocus({ row, col: myCol })}
                              onBlur={(e) => commit(s, item, e.target.value)}
                              onKeyDown={(e) => onKey(e, row, myCol, s, item)}
                              aria-label={`${s.full_name} ${item.item_label}`}
                            />
                            {errors[k] && <div className="cell-err">{errors[k]}</div>}
                          </td>
                        );
                      })
                    ) : (
                      <td key={g.key} className="empty-col" />
                    )
                  )}
                  <td className="computed">{s.written_work_avg ?? ''}</td>
                  <td className="computed">{s.performance_task_avg ?? ''}</td>
                  <td className="computed">{s.quarterly_assessment_score ?? ''}</td>
                  <td className="computed">{s.initial_grade ?? ''}</td>
                  <td className="computed qg">{s.transmuted_grade ?? ''}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      <div className="sheet-tabs">
        <span className="sheet-tab">Q{quarter}</span>
        <span className="sheet-hint">Enter a score · A = absent · E = excused · paste from Excel · autosaves</span>
      </div>
    </div>
  );
}
