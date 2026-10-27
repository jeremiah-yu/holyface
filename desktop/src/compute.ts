/**
 * Client-side mirror of GradeComputationService for optimistic grid totals.
 * Authoritative values always come from the PHP API after sync.
 *
 * Source: DepEd Order No. 8, s. 2015 — Percentage Score = (total score ÷ total HPS) × 100.
 */
import type { Cell, GradeItem, Weights } from './types';

export function percentageScore(items: GradeItem[], cells: Record<string, Cell>, type: GradeItem['component_type']): number | null {
  let totalScore = 0;
  let totalMax = 0;
  for (const item of items.filter((i) => i.component_type === type)) {
    const cell = cells[String(item.id)];
    if (!cell || cell.entry_status === 'absent' || cell.entry_status === 'excused') continue;
    if (cell.score === null || cell.score === undefined) continue;
    const max = Number(item.max_score);
    if (max <= 0) continue;
    totalScore += Number(cell.score);
    totalMax += max;
  }
  if (totalMax <= 0) return null;
  return Math.round((totalScore / totalMax) * 10000) / 100;
}

export function initialGrade(ww: number | null, pt: number | null, qa: number | null, weights: Weights): number | null {
  if (ww === null || pt === null || qa === null) return null;
  return Math.round((ww * weights.written_work_pct + pt * weights.performance_task_pct + qa * weights.quarterly_assessment_pct) / 100 * 100) / 100;
}

export function validateScore(value: string, max: number): { ok: boolean; score: number | null; error?: string } {
  const trimmed = value.trim();
  if (trimmed === '') return { ok: true, score: null };
  const n = Number(trimmed);
  if (!Number.isFinite(n)) return { ok: false, score: null, error: 'Not a number' };
  if (n < 0) return { ok: false, score: null, error: 'Cannot be negative' };
  if (n > max) return { ok: false, score: null, error: `Cannot exceed ${max}` };
  return { ok: true, score: n };
}

export function toCsv(rows: string[][]): string {
  const esc = (v: string) => (/[",\n]/.test(v) ? `"${v.replace(/"/g, '""')}"` : v);
  return '\uFEFF' + rows.map((r) => r.map((c) => esc(c ?? '')).join(',')).join('\r\n');
}

export function parseTsv(text: string): string[][] {
  return text
    .replace(/\r/g, '')
    .split('\n')
    .filter((line) => line.length > 0)
    .map((line) => line.split('\t'));
}
