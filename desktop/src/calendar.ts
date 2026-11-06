export type Period = {
  id: number;
  code: string;
  label: string;
  short: string;
  semester?: number | null;
};

export type Calendar = {
  mode: 'terms' | 'semesters' | string;
  grade_level: number;
  max_period: number;
  final_rule: string;
  periods: Period[];
};

export function calendarForGrade(gradeLevel: number): Calendar {
  if (gradeLevel === 11) {
    return {
      mode: 'terms',
      grade_level: 11,
      max_period: 3,
      final_rule: 'average_terms',
      periods: [
        { id: 1, code: 'T1', label: 'Term 1', short: 'T1' },
        { id: 2, code: 'T2', label: 'Term 2', short: 'T2' },
        { id: 3, code: 'T3', label: 'Term 3', short: 'T3' },
      ],
    };
  }
  return {
    mode: 'semesters',
    grade_level: gradeLevel || 12,
    max_period: 4,
    final_rule: 'average_semesters',
    periods: [
      { id: 1, code: 'Q1', label: 'Quarter 1', short: 'Q1', semester: 1 },
      { id: 2, code: 'Q2', label: 'Quarter 2', short: 'Q2', semester: 1 },
      { id: 3, code: 'Q3', label: 'Quarter 3', short: 'Q3', semester: 2 },
      { id: 4, code: 'Q4', label: 'Quarter 4', short: 'Q4', semester: 2 },
    ],
  };
}

export function periodLabel(gradeLevel: number, periodId: number): string {
  return calendarForGrade(gradeLevel).periods.find((p) => p.id === periodId)?.label ?? `Period ${periodId}`;
}

export function lockLabel(periodId: number): string {
  if (periodId === 4) return 'Period 4 · G12 Q4 only';
  return `Period ${periodId} · G11 T${periodId} / G12 Q${periodId}`;
}
