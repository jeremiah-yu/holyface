<?php
declare(strict_types=1);

namespace HolyFace\Api\Services;

use HolyFace\Api\Database\Connection;
use PDO;

/**
 * Configurable DepEd-style grade computation.
 *
 * Sources (cited per rule; Registrar may change stored weights/table without code changes):
 * - DepEd Order No. 8, s. 2015 — Policy Guidelines on Classroom Assessment for the K to 12
 *   Basic Education Program:
 *   • Component Percentage Score = (total score ÷ total highest possible score) × 100
 *     (SUM method, not the average of per-item percentages). DO 8, s. 2015, Section on
 *     computing the Percentage Score.
 *   • Weighted Score = Percentage Score × component weight. DO 8, s. 2015, Table 4
 *     (component weights by subject category).
 *   • Initial Grade = sum of the three weighted scores (WW + PT + QA).
 *   • Quarterly Grade = Initial Grade converted via the transmutation table
 *     (DO 8, s. 2015 Appendix 2, as commonly republished for K to 12). Stored in
 *     `transmutation_table` so the school can edit bands.
 *   • Lowest passing transmuted grade is 75. Stored in `grading_settings.passing_mark`.
 *   • For a semester subject, Final Grade = average of the two quarterly grades,
 *     rounded to the nearest whole number (DO 8, s. 2015, reporting of grades).
 * - General Average = average of final grades across subjects. Passed/Failed uses
 *   the stored passing mark (default 75).
 *
 * Default weight profiles live in `grading_weight_profiles` (NOT hardcoded here).
 * Work Immersion / Research / performance-heavy weights are UNCONFIRMED until the
 * Registrar edits them on the Settings screen — do not treat seed values as official.
 */
final class GradeComputationService
{
    public function __construct(private AuditService $audit) {}

    public function passingMark(): float
    {
        $pdo = Connection::get();
        $value = $pdo->query(
            "SELECT setting_value FROM grading_settings WHERE setting_key = 'passing_mark' LIMIT 1"
        );
        if ($value) {
            $raw = $value->fetchColumn();
            if ($raw !== false && is_numeric($raw)) {
                return (float) $raw;
            }
        }
        return 75.0; // DepEd Order No. 8, s. 2015 — lowest passing transmuted grade.
    }

    /**
     * Look up transmuted quarterly grade from the school's table.
     * Source: DepEd Order No. 8, s. 2015, Appendix 2 (transmutation table).
     */
    public function transmute(float $initialGrade): float
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT transmuted_grade FROM transmutation_table
             WHERE ? BETWEEN initial_min AND initial_max
             ORDER BY transmuted_grade DESC
             LIMIT 1'
        );
        $stmt->execute([round($initialGrade, 2)]);
        $found = $stmt->fetchColumn();
        if ($found !== false) {
            return (float) $found;
        }
        // Fallback only if the table is empty: floor at 60 (DepEd lowest transmuted grade).
        if ($initialGrade >= 100) {
            return 100.0;
        }
        if ($initialGrade <= 0) {
            return 60.0;
        }
        return 60.0;
    }

    /** @return array{written_work_pct:float,performance_task_pct:float,quarterly_assessment_pct:float,profile_code:?string,confirmed:bool} */
    public function weightsForCsa(int $csaId): array
    {
        $pdo = Connection::get();
        $wStmt = $pdo->prepare(
            'SELECT written_work_pct, performance_task_pct, quarterly_assessment_pct
             FROM grade_component_weights WHERE class_subject_assignment_id = ?'
        );
        $wStmt->execute([$csaId]);
        $row = $wStmt->fetch(PDO::FETCH_ASSOC);

        $pStmt = $pdo->prepare(
            'SELECT p.code, p.confirmed, p.written_work_pct, p.performance_task_pct, p.quarterly_assessment_pct
             FROM class_subject_assignments csa
             INNER JOIN subjects s ON s.id = csa.subject_id
             LEFT JOIN grading_weight_profiles p ON p.code = s.weight_profile_code
             WHERE csa.id = ? LIMIT 1'
        );
        $pStmt->execute([$csaId]);
        $profile = $pStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if ($row) {
            return [
                'written_work_pct' => (float) $row['written_work_pct'],
                'performance_task_pct' => (float) $row['performance_task_pct'],
                'quarterly_assessment_pct' => (float) $row['quarterly_assessment_pct'],
                'profile_code' => $profile['code'] ?? null,
                'confirmed' => (bool) ($profile['confirmed'] ?? 0),
            ];
        }

        return [
            'written_work_pct' => (float) ($profile['written_work_pct'] ?? 25),
            'performance_task_pct' => (float) ($profile['performance_task_pct'] ?? 45),
            'quarterly_assessment_pct' => (float) ($profile['quarterly_assessment_pct'] ?? 30),
            'profile_code' => $profile['code'] ?? null,
            'confirmed' => (bool) ($profile['confirmed'] ?? 0),
        ];
    }

    /**
     * Percentage Score = (total score ÷ total highest possible score) × 100.
     * Absent/excused entries are excluded from both numerator and denominator
     * (they are not scored zeros). Source: DO 8, s. 2015 Percentage Score.
     *
     * @param list<array{score:?string,max_score:string,entry_status?:string}> $rows
     */
    public function percentageScore(array $rows): ?float
    {
        $totalScore = 0.0;
        $totalMax = 0.0;
        foreach ($rows as $row) {
            $status = $row['entry_status'] ?? 'scored';
            if ($status === 'absent' || $status === 'excused') {
                continue;
            }
            if ($row['score'] === null || $row['score'] === '' || (float) $row['max_score'] <= 0) {
                continue;
            }
            $totalScore += (float) $row['score'];
            $totalMax += (float) $row['max_score'];
        }
        if ($totalMax <= 0) {
            return null;
        }
        return round(($totalScore / $totalMax) * 100, 2);
    }

    public function recomputeQuarter(int $enrollmentId, int $csaId, int $quarter, ?int $actorId = null): array
    {
        $pdo = Connection::get();
        $weights = $this->weightsForCsa($csaId);
        $passing = $this->passingMark();

        $rowsFor = function (string $type) use ($pdo, $enrollmentId, $csaId, $quarter): array {
            $stmt = $pdo->prepare(
                'SELECT score, max_score, entry_status FROM grade_components
                 WHERE enrollment_id = ? AND class_subject_assignment_id = ? AND quarter = ? AND component_type = ?'
            );
            $stmt->execute([$enrollmentId, $csaId, $quarter, $type]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        };

        // Rule 1 — Percentage Score per component (SUM method).
        $ww = $this->percentageScore($rowsFor('written_work'));
        $pt = $this->percentageScore($rowsFor('performance_task'));
        $qa = $this->percentageScore($rowsFor('quarterly_assessment'));

        // Rules 2–3 — Weighted Score = PS × weight; Initial Grade = sum of weighted scores.
        $initial = null;
        if ($ww !== null && $pt !== null && $qa !== null) {
            $initial = round(
                ($ww * $weights['written_work_pct'] / 100)
                + ($pt * $weights['performance_task_pct'] / 100)
                + ($qa * $weights['quarterly_assessment_pct'] / 100),
                2
            );
        }

        // Rule 4 — Quarterly Grade via transmutation table.
        $transmuted = $initial !== null ? $this->transmute($initial) : null;
        $remarks = $transmuted !== null
            ? ($transmuted >= $passing ? 'PASSED' : 'FAILED')
            : null;

        $sel = $pdo->prepare(
            'SELECT id, written_work_avg, performance_task_avg, quarterly_assessment_score, initial_grade, transmuted_grade
             FROM grades WHERE enrollment_id = ? AND class_subject_assignment_id = ? AND quarter = ?'
        );
        $sel->execute([$enrollmentId, $csaId, $quarter]);
        $existing = $sel->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $fields = [
                'written_work_avg' => $ww,
                'performance_task_avg' => $pt,
                'quarterly_assessment_score' => $qa,
                'initial_grade' => $initial,
                'transmuted_grade' => $transmuted,
            ];
            foreach ($fields as $field => $newVal) {
                $oldVal = $existing[$field];
                if ($actorId && (string) $oldVal !== (string) $newVal) {
                    $this->audit->logGradeChange(
                        $actorId,
                        $enrollmentId,
                        $csaId,
                        $quarter,
                        $field,
                        $oldVal !== null ? (string) $oldVal : null,
                        $newVal !== null ? (string) $newVal : null,
                        null,
                        null
                    );
                }
            }
            $upd = $pdo->prepare(
                'UPDATE grades SET written_work_avg = ?, performance_task_avg = ?, quarterly_assessment_score = ?,
                 initial_grade = ?, transmuted_grade = ?, remarks = ?, computed_at = NOW()
                 WHERE id = ?'
            );
            $upd->execute([$ww, $pt, $qa, $initial, $transmuted, $remarks, $existing['id']]);
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO grades (enrollment_id, class_subject_assignment_id, quarter, written_work_avg, performance_task_avg,
                 quarterly_assessment_score, initial_grade, transmuted_grade, remarks, computed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $ins->execute([$enrollmentId, $csaId, $quarter, $ww, $pt, $qa, $initial, $transmuted, $remarks]);
        }

        $final = $this->recomputeFinal($enrollmentId, $csaId);

        return [
            'written_work_avg' => $ww,
            'performance_task_avg' => $pt,
            'quarterly_assessment_score' => $qa,
            'initial_grade' => $initial,
            'transmuted_grade' => $transmuted,
            'remarks' => $remarks,
            'weights' => $weights,
            'final' => $final,
        ];
    }

    /** @return array<string, mixed> */
    public function recomputeFinal(int $enrollmentId, int $csaId): array
    {
        $pdo = Connection::get();
        $passing = $this->passingMark();
        $stmt = $pdo->prepare(
            'SELECT quarter, transmuted_grade FROM grades
             WHERE enrollment_id = ? AND class_subject_assignment_id = ?'
        );
        $stmt->execute([$enrollmentId, $csaId]);
        $byQ = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byQ[(int) $row['quarter']] = $row['transmuted_grade'] !== null ? (float) $row['transmuted_grade'] : null;
        }

        $q1 = $byQ[1] ?? null;
        $q2 = $byQ[2] ?? null;
        $q3 = $byQ[3] ?? null;
        $q4 = $byQ[4] ?? null;

        // Rule 5 — Final Grade for a semester subject = average of the two quarterly
        // grades, rounded to the nearest whole number (DO 8, s. 2015).
        $sem1 = $this->semesterFinal($q1, $q2);
        $sem2 = $this->semesterFinal($q3, $q4);

        $semesters = array_values(array_filter([$sem1, $sem2], fn ($v) => $v !== null));
        $final = $semesters ? (float) round(array_sum($semesters) / count($semesters)) : null;
        $action = $final !== null ? ($final >= $passing ? 'PASSED' : 'FAILED') : null;

        $pdo->prepare(
            'INSERT INTO final_grades (enrollment_id, class_subject_assignment_id, q1, q2, q3, q4, sem1, sem2, final_rating, action_taken, computed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE q1 = VALUES(q1), q2 = VALUES(q2), q3 = VALUES(q3), q4 = VALUES(q4),
             sem1 = VALUES(sem1), sem2 = VALUES(sem2),
             final_rating = VALUES(final_rating), action_taken = VALUES(action_taken), computed_at = NOW()'
        )->execute([$enrollmentId, $csaId, $q1, $q2, $q3, $q4, $sem1, $sem2, $final, $action]);

        return [
            'q1' => $q1,
            'q2' => $q2,
            'q3' => $q3,
            'q4' => $q4,
            'sem1' => $sem1,
            'sem2' => $sem2,
            'final_rating' => $final,
            'action_taken' => $action,
        ];
    }

    private function semesterFinal(?float $a, ?float $b): ?float
    {
        if ($a !== null && $b !== null) {
            return (float) round(($a + $b) / 2);
        }
        return $a ?? $b;
    }
}
