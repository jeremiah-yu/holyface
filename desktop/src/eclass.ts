import * as XLSX from 'xlsx';
import type { GradeItem, GridStudent } from './types';

export type EclassKind = 'g11' | 'g12';

export type EclassComponent = {
  key: 'written_work' | 'performance_task' | 'quarterly_assessment';
  label: string;
  labels: string[];
  defaultMax: number[];
};

export type EclassLayout = {
  kind: EclassKind;
  title: string;
  templateFile: string;
  sheetNames: string[];
  components: EclassComponent[];
  /** 0-based column index of first score column in the grade sheet (F = 5). */
  firstScoreCol: number;
  nameCol: number;
  maleStartRow: number;
  femaleHeaderRow: number;
  hpsRow: number;
};

/** Exact column map taken from D:\G12-ECLASS-26-27.xlsx sheet 1ST / 2ND. */
export const G12_LAYOUT: EclassLayout = {
  kind: 'g12',
  title: 'Senior High School Class Record (G12 ECLASS)',
  templateFile: 'templates/G12-ECLASS.xlsx',
  sheetNames: ['1ST', '2ND'],
  firstScoreCol: 5,
  nameCol: 1,
  maleStartRow: 12, // 0-based? use 1-based in parser: row 13
  femaleHeaderRow: 63,
  hpsRow: 11,
  components: [
    {
      key: 'written_work',
      label: 'WRITTEN WORK',
      labels: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'],
      defaultMax: [100, 100, 100, 100, 100, 100, 100, 100, 100, 100],
    },
    {
      key: 'performance_task',
      label: 'PERFORMANCE TASKS',
      labels: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'],
      defaultMax: [100, 100, 100, 100, 100, 100, 100, 100, 100, 100],
    },
    {
      key: 'quarterly_assessment',
      label: 'QUARTERLY ASSESSMENT',
      labels: ['1'],
      defaultMax: [50],
    },
  ],
};

/** Exact column map taken from D:\G11-ECLASS.xlsm sheet TERM 1 / 2 / 3. */
export const G11_LAYOUT: EclassLayout = {
  kind: 'g11',
  title: 'Strengthened Senior High School Class Record (G11 ECLASS)',
  templateFile: 'templates/G11-ECLASS.xlsm',
  sheetNames: ['TERM 1', 'TERM 2', 'TERM 3'],
  firstScoreCol: 5,
  nameCol: 1,
  maleStartRow: 13,
  femaleHeaderRow: 63,
  hpsRow: 11,
  components: [
    {
      key: 'written_work',
      label: 'WRITTEN/ ORAL WORKS',
      labels: ['1', '2', '3', '4', '5'],
      defaultMax: [20, 10, 10, 20, 10],
    },
    {
      key: 'performance_task',
      label: 'PRODUCT/ PERFORMANCE TASKS',
      labels: ['1', '2', '3'],
      defaultMax: [30, 30, 30],
    },
    {
      key: 'quarterly_assessment',
      label: 'SUMMATIVE TESTS & TERM EXAMINATION',
      labels: ['SA1', 'SA2', 'TE'],
      defaultMax: [30, 30, 60],
    },
  ],
};

export function layoutForGrade(gradeLevel: number): EclassLayout {
  return Number(gradeLevel) === 11 ? G11_LAYOUT : G12_LAYOUT;
}

export function sheetForPeriod(layout: EclassLayout, period: number): string {
  const idx = Math.max(1, Math.min(period, layout.sheetNames.length)) - 1;
  return layout.sheetNames[idx];
}

export function expectedItems(layout: EclassLayout): { component_type: GradeItem['component_type']; item_label: string; max_score: number; sort_order: number }[] {
  const out: { component_type: GradeItem['component_type']; item_label: string; max_score: number; sort_order: number }[] = [];
  let order = 1;
  for (const comp of layout.components) {
    comp.labels.forEach((label, i) => {
      out.push({
        component_type: comp.key,
        item_label: label,
        max_score: comp.defaultMax[i] ?? 100,
        sort_order: order++,
      });
    });
  }
  return out;
}

function cellText(ws: XLSX.WorkSheet, r: number, c: number): string {
  const ref = XLSX.utils.encode_cell({ r, c });
  const cell = ws[ref];
  if (!cell || cell.v === null || cell.v === undefined) return '';
  return String(cell.v).trim();
}

function cellNum(ws: XLSX.WorkSheet, r: number, c: number): number | null {
  const ref = XLSX.utils.encode_cell({ r, c });
  const cell = ws[ref];
  if (!cell || cell.v === null || cell.v === undefined || cell.v === '') return null;
  const n = Number(cell.v);
  return Number.isFinite(n) ? n : null;
}

function normName(name: string): string {
  return name.toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
}

/**
 * Reads raw scores from an official DepEd/Teach Pinas ECLASS workbook
 * (G12-ECLASS-26-27.xlsx or G11-ECLASS.xlsm).
 */
export function parseEclassWorkbook(
  data: ArrayBuffer,
  layout: EclassLayout,
  period: number,
  students: GridStudent[]
): { scores: { enrollment_id: number; item_label: string; component_type: GradeItem['component_type']; score: number | null; max_score: number | null }[]; hps: { component_type: GradeItem['component_type']; item_label: string; max_score: number }[]; matched: number; sheet: string } {
  const wb = XLSX.read(data, { type: 'array', cellDates: false });
  const sheetName =
    wb.SheetNames.find((n) => n.toUpperCase() === sheetForPeriod(layout, period).toUpperCase()) ||
    wb.SheetNames.find((n) => layout.sheetNames.map((s) => s.toUpperCase()).includes(n.toUpperCase())) ||
    wb.SheetNames.find((n) => /^(1ST|2ND|TERM)/i.test(n));
  if (!sheetName) {
    throw new Error('This workbook has no ECLASS grade sheet (1ST / 2ND / TERM 1). Use the official G11 or G12 ECLASS file.');
  }
  const ws = wb.Sheets[sheetName];
  const byName = new Map(students.map((s) => [normName(s.full_name), s]));

  const scoreCols: { component_type: GradeItem['component_type']; item_label: string; col: number; defaultMax: number }[] = [];
  let col = layout.firstScoreCol;
  for (const comp of layout.components) {
    comp.labels.forEach((label, i) => {
      scoreCols.push({
        component_type: comp.key,
        item_label: label,
        col,
        defaultMax: comp.defaultMax[i] ?? 100,
      });
      col += 1;
    });
    col += 3; // Total, PS, WS
  }

  const hps: { component_type: GradeItem['component_type']; item_label: string; max_score: number }[] = [];
  for (const sc of scoreCols) {
    const max = cellNum(ws, layout.hpsRow - 1, sc.col);
    if (max != null && max > 0) {
      hps.push({ component_type: sc.component_type, item_label: sc.item_label, max_score: max });
    }
  }

  const scores: { enrollment_id: number; item_label: string; component_type: GradeItem['component_type']; score: number | null; max_score: number | null }[] = [];
  const seen = new Set<number>();
  const rows: number[] = [];
  for (let r = 12; r < 200; r++) {
    const a = cellText(ws, r, 0).toUpperCase();
    if (a === 'FEMALE' || a.startsWith('FEMALE')) continue;
    if (a === 'MALE' || a.startsWith('MALE')) continue;
    const name = cellText(ws, r, layout.nameCol);
    if (!name || /^\d+$/.test(name)) continue;
    const student = byName.get(normName(name));
    if (!student || seen.has(student.enrollment_id)) continue;
    seen.add(student.enrollment_id);
    rows.push(r);
    for (const sc of scoreCols) {
      const raw = cellText(ws, r, sc.col).toUpperCase();
      let score: number | null = null;
      if (raw === 'A' || raw === 'ABS' || raw === 'ABSENT') {
        score = null;
      } else if (raw === 'E' || raw === 'EXC' || raw === 'EXCUSED') {
        score = null;
      } else {
        score = cellNum(ws, r, sc.col);
      }
      const max = cellNum(ws, layout.hpsRow - 1, sc.col);
      scores.push({
        enrollment_id: student.enrollment_id,
        item_label: sc.item_label,
        component_type: sc.component_type,
        score,
        max_score: max,
      });
    }
  }

  return { scores, hps, matched: seen.size, sheet: sheetName };
}

export function missingEclassItems(layout: EclassLayout, items: GradeItem[]): { component_type: GradeItem['component_type']; item_label: string; max_score: number; sort_order: number }[] {
  const have = new Set(items.map((i) => `${i.component_type}:${String(i.item_label).trim()}`));
  return expectedItems(layout).filter((e) => !have.has(`${e.component_type}:${e.item_label}`));
}
