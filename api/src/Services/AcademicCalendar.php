<?php
declare(strict_types=1);

namespace HolyFace\Api\Services;

/**
 * Holy Face SHS calendar from the Oct 2026 teacher interview:
 * Grade 11 (MATATAG) uses three terms; Grade 12 still uses two semesters / four quarters.
 */
final class AcademicCalendar
{
    /**
     * @return array{
     *   mode:string,
     *   grade_level:int,
     *   max_period:int,
     *   final_rule:string,
     *   periods:list<array{id:int,code:string,label:string,short:string,semester:?int}>
     * }
     */
    public static function forGrade(int $gradeLevel): array
    {
        if ($gradeLevel === 11) {
            return [
                'mode' => 'terms',
                'grade_level' => 11,
                'max_period' => 3,
                'final_rule' => 'average_terms',
                'periods' => [
                    ['id' => 1, 'code' => 'T1', 'label' => 'Term 1', 'short' => 'T1', 'semester' => null],
                    ['id' => 2, 'code' => 'T2', 'label' => 'Term 2', 'short' => 'T2', 'semester' => null],
                    ['id' => 3, 'code' => 'T3', 'label' => 'Term 3', 'short' => 'T3', 'semester' => null],
                ],
            ];
        }

        return [
            'mode' => 'semesters',
            'grade_level' => $gradeLevel > 0 ? $gradeLevel : 12,
            'max_period' => 4,
            'final_rule' => 'average_semesters',
            'periods' => [
                ['id' => 1, 'code' => 'Q1', 'label' => 'Quarter 1', 'short' => 'Q1', 'semester' => 1],
                ['id' => 2, 'code' => 'Q2', 'label' => 'Quarter 2', 'short' => 'Q2', 'semester' => 1],
                ['id' => 3, 'code' => 'Q3', 'label' => 'Quarter 3', 'short' => 'Q3', 'semester' => 2],
                ['id' => 4, 'code' => 'Q4', 'label' => 'Quarter 4', 'short' => 'Q4', 'semester' => 2],
            ],
        ];
    }

    public static function isValidPeriod(int $gradeLevel, int $period): bool
    {
        $cal = self::forGrade($gradeLevel);
        return $period >= 1 && $period <= $cal['max_period'];
    }

    /** @return array{id:int,code:string,label:string,short:string,semester:?int}|null */
    public static function period(int $gradeLevel, int $periodId): ?array
    {
        foreach (self::forGrade($gradeLevel)['periods'] as $period) {
            if ($period['id'] === $periodId) {
                return $period;
            }
        }
        return null;
    }

    public static function honorLabel(?float $average): ?string
    {
        if ($average === null) {
            return null;
        }
        if ($average >= 98) {
            return 'With Highest Honors';
        }
        if ($average >= 95) {
            return 'With High Honors';
        }
        if ($average >= 90) {
            return 'With Honors';
        }
        return null;
    }
}
