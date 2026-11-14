import { ClipboardEvent, Fragment, KeyboardEvent, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { api, ApiError } from '../api';
import { periodLabel } from '../calendar';
import { initialGrade, parseSheet, percentageScore, validateScore } from '../compute';
import { layoutForGrade, missingEclassItems, parseEclassWorkbook } from '../eclass';
import type { Cell, GradeItem, GridPayload, GridStudent, Section } from '../types';

type Focus = { row: number; col: number };

type ComponentKey = GradeItem['component_type'];

function groupMeta(gradeLevel: number, weights: GridPayload['weights']) {
  const layout = layoutForGrade(gradeLevel);
  const short: Record<ComponentKey, string> = {
    written_work: '+ WW',
    performance_task: '+ PT',
    quarterly_assessment: Number(gradeLevel) === 11 ? '+ Exam' : '+ QA',
  };
  const group: Record<ComponentKey, string> = {
    written_work: 'ww',
    performance_task: 'pt',
    quarterly_assessment: 'qa',
  };
  const weightOf: Record<ComponentKey, number> = {
    written_work: Number(weights.written_work_pct),
    performance_task: Number(weights.performance_task_pct),
    quarterly_assessment: Number(weights.quarterly_assessment_pct),
  };
  return layout.components.map((c) => ({
    key: c.key,
    group: group[c.key],
    short: short[c.key],
    label: c.label,
    weight: weightOf[c.key],
  }));
}

function scoredTotal(student: GridStudent, items: GradeItem[]): number | null {
  let total = 0;
  let any = false;
  for (const item of items) {
    const cell = student.cells[String(item.id)];
    if (!cell || cell.entry_status === 'absent' || cell.entry_status === 'excused') continue;
    if (cell.score === null || cell.score === undefined) continue;
    total += Number(cell.score);
    any = true;
  }
  return any ? Math.round(total * 100) / 100 : null;
}

function hpsTotal(items: GradeItem[]): number {
  return items.reduce((sum, item) => sum + Number(item.max_score || 0), 0);
}

function fmt(n: number | string | null | undefined, digits = 2): string {
  if (n === null || n === undefined || n === '') return '';
  const v = Number(n);
  if (!Number.isFinite(v)) return '';
  return Number.isInteger(v) ? String(v) : v.toFixed(digits).replace(/\.?0+$/, '');
}

function psOf(student: GridStudent, key: ComponentKey): number | null {
  const raw =
    key === 'written_work'
      ? student.written_work_avg
      : key === 'performance_task'
        ? student.performance_task_avg
        : student.quarterly_assessment_score;
  if (raw === null || raw === undefined || raw === '') return null;
  const n = Number(raw);
  return Number.isFinite(n) ? n : null;
}

function wsOf(ps: number | null, weightPct: number): number | null {
  if (ps === null) return null;
  return Math.round(ps * (weightPct / 100) * 100) / 100;
}

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
  const eclass = useMemo(() => layoutForGrade(section.grade_level), [section.grade_level]);
  const isG11 = eclass.kind === 'g11';
  const finalLabel = isG11 ? 'Transmuted Grade' : 'Quarterly Grade';
  const missingCols = useMemo(() => missingEclassItems(eclass, items), [eclass, items]);

  const groups = useMemo(() => {
    return groupMeta(section.grade_level, grid.weights).map((g) => ({
      ...g,
      items: items.filter((i) => i.component_type === g.key),
    }));
  }, [items, section.grade_level, grid.weights]);

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
      const cells = {
        ...s.cells,
        [String(item.id)]: {
          ...(s.cells[String(item.id)] ?? { component_id: 0, max_score: max, recorded_at: null }),
          score,
          entry_status: status,
          max_score: max,
        },
      };
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
    const table = parseSheet(text);
    table.forEach((line, ri) => {
      line.forEach((val, ci) => {
        const student = grid.students[focus.row + ri];
        const item = items[focus.col + ci];
        if (student && item) commit(student, item, val);
      });
    });
  }

  async function refreshGrid() {
    const fresh = await api<GridPayload>(
      `/grades/grid?class_subject_assignment_id=${section.class_subject_assignment_id}&quarter=${quarter}`,
      token
    );
    setGrid(fresh);
    return fresh;
  }

  async function addItem(type: ComponentKey) {
    const expected = eclass.components.find((c) => c.key === type);
    const count = items.filter((i) => i.component_type === type).length;
    const defaults = expected?.labels[count] ?? String(count + 1);
    const defaultMax = expected?.defaultMax[count] ?? (type === 'quarterly_assessment' ? 50 : 100);
    const label = window.prompt('Column name from the official ECLASS file', defaults);
    if (!label) return;
    const maxRaw = window.prompt('Highest possible score', String(defaultMax));
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
    await refreshGrid();
  }

  async function ensureEclassColumns() {
    const missing = missingEclassItems(eclass, items);
    if (!missing.length) {
      setMsg(`Columns already match ${eclass.title}.`);
      return;
    }
    for (const item of missing) {
      await api('/grades/items', token, {
        method: 'POST',
        body: JSON.stringify({
          class_subject_assignment_id: section.class_subject_assignment_id,
          quarter,
          component_type: item.component_type,
          item_label: item.item_label,
          max_score: item.max_score,
        }),
      });
    }
    await refreshGrid();
    setMsg(`Loaded official ECLASS columns from ${eclass.templateFile.split('/').pop()}.`);
  }

  function downloadOfficialTemplate() {
    const a = document.createElement('a');
    a.href = eclass.templateFile;
    a.download = eclass.templateFile.split('/').pop() || 'ECLASS.xlsx';
    a.click();
  }

  async function saveHps(item: GradeItem, raw: string) {
    const max = Number(raw);
    if (!Number.isFinite(max) || max <= 0) {
      setMsg('Highest possible score must be greater than 0.');
      return;
    }
    try {
      await api('/grades/items', token, {
        method: 'PUT',
        body: JSON.stringify({ id: item.id, max_score: max, item_label: item.item_label }),
      });
      const fresh = await api<GridPayload>(
        `/grades/grid?class_subject_assignment_id=${section.class_subject_assignment_id}&quarter=${quarter}`,
        token
      );
      setGrid(fresh);
      setMsg('Highest possible score saved.');
    } catch (err) {
      setMsg((err as Error).message);
    }
  }

  async function submitFinals() {
    if (!window.confirm('Submit final grades for this subject? The class adviser generates and releases official cards after every subject is in.')) {
      return;
    }
    const res = await api<{ advisory?: { ready: number; waiting: number } }>('/grades/submit-final', token, {
      method: 'POST',
      body: JSON.stringify({ class_subject_assignment_id: section.class_subject_assignment_id, confirmed: true }),
    });
    const ready = res.advisory;
    if (ready) {
      setMsg(`Finals submitted. Ready for the adviser: ${ready.ready}. Still waiting on other subjects: ${ready.waiting}.`);
    } else {
      setMsg('Final grades submitted.');
    }
  }

  async function importEclassFile(file: File) {
    const lower = file.name.toLowerCase();
    if (lower.endsWith('.xlsx') || lower.endsWith('.xlsm') || lower.endsWith('.xls')) {
      const buf = await file.arrayBuffer();
      let workingItems = items;
      const missing = missingEclassItems(eclass, workingItems);
      if (missing.length) {
        for (const item of missing) {
          await api('/grades/items', token, {
            method: 'POST',
            body: JSON.stringify({
              class_subject_assignment_id: section.class_subject_assignment_id,
              quarter,
              component_type: item.component_type,
              item_label: item.item_label,
              max_score: item.max_score,
            }),
          });
        }
        workingItems = (await refreshGrid()).items;
      }
      const parsed = parseEclassWorkbook(buf, eclass, quarter, grid.students);
      for (const h of parsed.hps) {
        const item = workingItems.find((i) => i.component_type === h.component_type && String(i.item_label).trim() === h.item_label);
        if (item && Number(item.max_score) !== h.max_score) {
          await api('/grades/items', token, {
            method: 'PUT',
            body: JSON.stringify({ id: item.id, max_score: h.max_score, item_label: item.item_label }),
          });
        }
      }
      const fresh = await refreshGrid();
      workingItems = fresh.items;
      let applied = 0;
      for (const row of parsed.scores) {
        if (row.score === null) continue;
        const student = fresh.students.find((s) => s.enrollment_id === row.enrollment_id);
        const item = workingItems.find((i) => i.component_type === row.component_type && String(i.item_label).trim() === row.item_label);
        if (!student || !item) continue;
        commit(student, item, String(row.score));
        applied += 1;
      }
      setMsg(`Imported ${file.name} (${parsed.sheet}): matched ${parsed.matched} learners, applied ${applied} scores from the official ECLASS file.`);
      return;
    }
    const text = await file.text();
    const table = parseSheet(text);
    table.forEach((line, ri) => {
      line.forEach((val, ci) => {
        const student = grid.students[ri];
        const item = items[ci];
        if (student && item) commit(student, item, val);
      });
    });
    setMsg(`Imported ${file.name}`);
  }

  const focusedStudent = grid.students[focus.row];
  const focusedItem = items[focus.col];
  const formulaName = focusedItem && focusedStudent ? `${focusedStudent.full_name}` : '';
  const formulaValue = focusedItem && focusedStudent ? `${focusedItem.item_label}  ${display(focusedStudent, focusedItem)}` : '';
  const periodTitle = grid.period?.label ?? periodLabel(section.grade_level, quarter);
  const gradeSection = `G${section.grade_level} ${section.strand}-${section.section_code}`;
  const title = isG11 ? 'Strengthened Senior High School Class Record' : 'Senior High School Class Record';
  const pursuant = isG11 ? '(Pursuant to Deped Order 15 series of 2026)' : '(Pursuant to Deped Order 8 series of 2015)';
  const scoreColSpan = groups.reduce((n, g) => n + Math.max(3, g.items.length + 3), 0) + 2;

  function renderScoreHeads() {
    return groups.map((g) =>
      g.items.length ? (
        <Fragment key={g.key}>
          {g.items.map((item, idx) => (
            <th key={item.id} className="e-num" title={item.item_label}>
              {/^\d+$/.test(String(item.item_label).trim()) || /^(SA|TE)/i.test(String(item.item_label))
                ? item.item_label
                : idx + 1}
            </th>
          ))}
          <th className="e-total">Total</th>
          <th className="e-ps">PS</th>
          <th className="e-ws">WS</th>
        </Fragment>
      ) : (
        <Fragment key={g.key}>
          <th className="e-num">—</th>
          <th className="e-total">Total</th>
          <th className="e-ps">PS</th>
          <th className="e-ws">WS</th>
        </Fragment>
      )
    );
  }

  function renderGroupHeads() {
    return groups.map((g) => (
      <th key={g.key} className={`e-group e-group-${g.group}`} colSpan={Math.max(3, g.items.length + 3)}>
        {g.label} ({fmt(g.weight, 0)}%)
      </th>
    ));
  }

  return (
    <div className="sheet eclass" onPaste={onPaste}>
      <div className="sheet-toolbar eclass-actions">
        <span className="sheet-weights">{msg || formulaValue || 'Encode scores like the official ECLASS spreadsheet'}</span>
        <span className="sheet-toolbar-spacer" />
        {!readOnly &&
          groups.map((t) => (
            <button key={t.key} type="button" className="ghost" onClick={() => addItem(t.key)}>
              {t.short}
            </button>
          ))}
        {!readOnly && missingCols.length > 0 && (
          <button type="button" className="ghost" onClick={() => void ensureEclassColumns()}>
            Use official ECLASS columns
          </button>
        )}
        <button type="button" className="ghost" onClick={downloadOfficialTemplate}>
          Download {isG11 ? 'G11' : 'G12'} ECLASS
        </button>
        <label className="ghost" style={{ display: 'inline-flex', alignItems: 'center', cursor: 'pointer' }}>
          Import ECLASS
          <input
            type="file"
            accept=".xlsx,.xlsm,.xls,.csv,.tsv,.txt"
            hidden
            onChange={(e) => {
              const file = e.target.files?.[0];
              if (file) void importEclassFile(file);
              e.target.value = '';
            }}
          />
        </label>
        <button type="button" onClick={submitFinals}>
          Submit finals
        </button>
      </div>
      {readOnly && <p className="warn sheet-banner">This period is locked. Ask the Registrar to unlock it.</p>}
      <p className="sheet-scroll-hint">This page is drawn to match the official {isG11 ? 'G11-ECLASS.xlsm' : 'G12-ECLASS.xlsx'} class record.</p>
      <div className="sheet-scroll eclass-scroll">
        <div className="eclass-paper">
          <div className="eclass-title">{title}</div>
          <div className="eclass-pursuant">{pursuant}</div>
          <div className="eclass-meta">
            <div className="eclass-meta-row">
              <span className="e-label">REGION</span>
              <span className="e-box">IV-A</span>
              <span className="e-label">DIVISION</span>
              <span className="e-box">RIZAL</span>
            </div>
            <div className="eclass-meta-row">
              <span className="e-label">SCHOOL NAME</span>
              <span className="e-box e-wide">HOLY FACE OF JESUS LYCEUM OF SAN JOSE INC.</span>
              <span className="e-label">SCHOOL YEAR</span>
              <span className="e-box">{section.school_year}</span>
            </div>
            <div className="eclass-meta-row eclass-meta-band">
              <span className="e-box e-period">{periodTitle}</span>
              <span className="e-label">GRADE &amp; SECTION:</span>
              <span className="e-box">{gradeSection}</span>
              <span className="e-label">TEACHER:</span>
              <span className="e-box">{section.teacher_name || '—'}</span>
              <span className="e-label">SUBJECT:</span>
              <span className="e-box">{section.subject_title}</span>
              <span className="e-label">{isG11 ? 'TRACK:' : 'SEMESTER / TRACK:'}</span>
              <span className="e-box">{section.strand}</span>
            </div>
          </div>

          <table className="sheet-grid eclass-grid">
            <thead>
              <tr>
                <th className="col-num sticky-head sticky-col col-a" rowSpan={2}>
                  #
                </th>
                <th className="sticky-head sticky-col col-name e-learners" rowSpan={2}>
                  LEARNERS&apos; NAMES
                </th>
                {renderGroupHeads()}
                <th className="e-final" rowSpan={2}>
                  Initial Grade
                </th>
                <th className="e-final" rowSpan={2}>
                  {finalLabel}
                </th>
              </tr>
              <tr>{renderScoreHeads()}</tr>
            </thead>
            <tbody>
              <tr className="hps-row">
                <td className="col-num sticky-col col-a" />
                <td className="sticky-col col-name name-cell e-hps-label">HIGHEST POSSIBLE SCORE</td>
                {groups.map((g) =>
                  g.items.length ? (
                    <Fragment key={`hps-${g.key}`}>
                      {g.items.map((item) => (
                        <td key={`hps-${item.id}`} className="hps-cell">
                          {readOnly ? (
                            fmt(item.max_score, 0)
                          ) : (
                            <input
                              defaultValue={String(item.max_score)}
                              disabled={readOnly}
                              onBlur={(e) => {
                                if (String(e.target.value) !== String(item.max_score)) {
                                  void saveHps(item, e.target.value);
                                }
                              }}
                              aria-label={`Highest possible score ${item.item_label}`}
                            />
                          )}
                        </td>
                      ))}
                      <td className="computed e-total">{fmt(hpsTotal(g.items), 0)}</td>
                      <td className="computed e-ps">100</td>
                      <td className="computed e-ws">{fmt(g.weight / 100, 2)}</td>
                    </Fragment>
                  ) : (
                    <Fragment key={`hps-${g.key}`}>
                      <td className="empty-col" />
                      <td className="computed e-total" />
                      <td className="computed e-ps">100</td>
                      <td className="computed e-ws">{fmt(g.weight / 100, 2)}</td>
                    </Fragment>
                  )
                )}
                <td className="computed" />
                <td className="computed" />
              </tr>
              <tr className="e-sex-row">
                <td className="sticky-col col-a" />
                <td className="sticky-col col-name" colSpan={1}>
                  MALE
                </td>
                <td colSpan={scoreColSpan} />
              </tr>
              {grid.students.map((s, row) => {
                let col = -1;
                return (
                  <tr key={s.enrollment_id}>
                    <td className="col-num sticky-col col-a">{row + 1}</td>
                    <td className="sticky-col col-name name-cell" title={s.lrn}>
                      {s.full_name}
                    </td>
                    {groups.map((g) => {
                      const total = scoredTotal(s, g.items);
                      const ps = psOf(s, g.key);
                      const ws = wsOf(ps, g.weight);
                      if (!g.items.length) {
                        return (
                          <Fragment key={`${g.key}-${s.enrollment_id}`}>
                            <td className="empty-col" />
                            <td className="computed e-total" />
                            <td className="computed e-ps" />
                            <td className="computed e-ws" />
                          </Fragment>
                        );
                      }
                      return (
                        <Fragment key={`${g.key}-${s.enrollment_id}`}>
                          {g.items.map((item) => {
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
                          })}
                          <td className="computed e-total">{fmt(total)}</td>
                          <td className="computed e-ps">{fmt(ps)}</td>
                          <td className="computed e-ws">{fmt(ws)}</td>
                        </Fragment>
                      );
                    })}
                    <td className="computed e-final">{fmt(s.initial_grade)}</td>
                    <td className="computed qg e-final">{fmt(s.transmuted_grade, 0)}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
          {formulaName && (
            <div className="eclass-status">
              Active cell: <strong>{formulaName}</strong> · {formulaValue}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
