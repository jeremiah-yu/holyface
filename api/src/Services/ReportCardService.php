<?php
declare(strict_types=1);

namespace HolyFace\Api\Services;

use HolyFace\Api\Database\Connection;
use PDO;

/**
 * Issues an official grading card only when every subject of a student
 * in the enrolled section has a submitted final rating.
 */
final class ReportCardService
{
    /** @return array{issued:int, waiting:int} */
    public function issueReadyCardsForClass(int $csaId): array
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT e.id AS enrollment_id, e.student_id, e.class_section_id
             FROM enrollments e
             INNER JOIN class_subject_assignments csa ON csa.class_section_id = e.class_section_id
             WHERE csa.id = ? AND e.status = "enrolled"'
        );
        $stmt->execute([$csaId]);
        $issued = 0;
        $waiting = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($this->issueIfComplete((int) $row['enrollment_id'])) {
                $issued++;
            } else {
                $waiting++;
            }
        }
        return ['issued' => $issued, 'waiting' => $waiting];
    }

    public function issueIfComplete(int $enrollmentId): bool
    {
        $snapshot = $this->buildSnapshot($enrollmentId);
        if ($snapshot === null) {
            return false;
        }

        $pdo = Connection::get();
        $existing = $pdo->prepare('SELECT id, card_number FROM official_report_cards WHERE enrollment_id = ? LIMIT 1');
        $existing->execute([$enrollmentId]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);
        $cardNumber = $row['card_number'] ?? sprintf('HF-%s-%04d', date('Y'), $enrollmentId);
        $snapshot['card_number'] = $cardNumber;
        $snapshot['issued_at'] = date('c');
        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($row) {
            $pdo->prepare(
                'UPDATE official_report_cards
                 SET general_average = ?, remarks = ?, payload_json = ?, issued_at = NOW()
                 WHERE id = ?'
            )->execute([
                $snapshot['general_average'],
                $snapshot['remarks'],
                $json,
                $row['id'],
            ]);
        } else {
            $pdo->prepare(
                'INSERT INTO official_report_cards
                 (enrollment_id, school_year_id, card_number, general_average, remarks, payload_json, issued_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $enrollmentId,
                $snapshot['student']['school_year_id'],
                $cardNumber,
                $snapshot['general_average'],
                $snapshot['remarks'],
                $json,
            ]);
        }
        return true;
    }

    public function hasCardForUser(int $userId): bool
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM official_report_cards orc
             INNER JOIN enrollments e ON e.id = orc.enrollment_id
             INNER JOIN students s ON s.id = e.student_id
             WHERE s.user_id = ? AND e.status IN ("enrolled","completed")
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        return (bool) $stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function cardForUser(int $userId): ?array
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT orc.payload_json
             FROM official_report_cards orc
             INNER JOIN enrollments e ON e.id = orc.enrollment_id
             INNER JOIN students s ON s.id = e.student_id
             WHERE s.user_id = ? AND e.status IN ("enrolled","completed")
             ORDER BY orc.issued_at DESC
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $json = $stmt->fetchColumn();
        if (!$json) {
            return null;
        }
        $data = json_decode((string) $json, true);
        return is_array($data) ? $data : null;
    }

    /** @return array<string, mixed> */
    public function progressForUser(int $userId): array
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT e.id AS enrollment_id
             FROM enrollments e
             INNER JOIN students s ON s.id = e.student_id
             WHERE s.user_id = ? AND e.status = "enrolled"
             ORDER BY e.id DESC
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $enrollmentId = (int) $stmt->fetchColumn();
        if ($enrollmentId <= 0) {
            return ['total' => 0, 'submitted' => 0, 'missing' => []];
        }

        $subjects = $this->subjectStatus($enrollmentId);
        $missing = [];
        $submitted = 0;
        foreach ($subjects as $sub) {
            if ($sub['ready']) {
                $submitted++;
            } else {
                $missing[] = $sub['title'];
            }
        }
        return [
            'total' => count($subjects),
            'submitted' => $submitted,
            'missing' => $missing,
        ];
    }

    /** @return list<array{title:string, ready:bool}> */
    private function subjectStatus(int $enrollmentId): array
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT sub.title,
                    fg.final_rating, fg.is_submitted
             FROM enrollments e
             INNER JOIN class_subject_assignments csa ON csa.class_section_id = e.class_section_id
             INNER JOIN subjects sub ON sub.id = csa.subject_id
             LEFT JOIN final_grades fg ON fg.enrollment_id = e.id AND fg.class_subject_assignment_id = csa.id
             WHERE e.id = ?
             ORDER BY sub.title'
        );
        $stmt->execute([$enrollmentId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'title' => (string) $row['title'],
                'ready' => (int) ($row['is_submitted'] ?? 0) === 1 && $row['final_rating'] !== null,
            ];
        }
        return $out;
    }

    /** @return array<string, mixed>|null */
    private function buildSnapshot(int $enrollmentId): ?array
    {
        $pdo = Connection::get();
        $head = $pdo->prepare(
            'SELECT e.id AS enrollment_id, e.class_section_id,
                    s.id AS student_id, s.lrn, s.grade_level, s.section_code, s.school_year_id,
                    u.full_name, st.code AS strand_code, st.name AS strand_name, sy.label AS school_year
             FROM enrollments e
             INNER JOIN students s ON s.id = e.student_id
             INNER JOIN users u ON u.id = s.user_id
             INNER JOIN strands st ON st.id = s.strand_id
             INNER JOIN school_years sy ON sy.id = s.school_year_id
             WHERE e.id = ?
             LIMIT 1'
        );
        $head->execute([$enrollmentId]);
        $student = $head->fetch(PDO::FETCH_ASSOC);
        if (!$student) {
            return null;
        }

        $subs = $pdo->prepare(
            'SELECT sub.subject_code, sub.title,
                    fg.q1, fg.q2, fg.q3, fg.q4, fg.sem1, fg.sem2, fg.final_rating, fg.action_taken, fg.is_submitted
             FROM class_subject_assignments csa
             INNER JOIN subjects sub ON sub.id = csa.subject_id
             LEFT JOIN final_grades fg ON fg.class_subject_assignment_id = csa.id AND fg.enrollment_id = ?
             WHERE csa.class_section_id = ?
             ORDER BY sub.title'
        );
        $subs->execute([$enrollmentId, (int) $student['class_section_id']]);
        $subjects = [];
        $finals = [];
        foreach ($subs->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ((int) ($row['is_submitted'] ?? 0) !== 1 || $row['final_rating'] === null) {
                return null;
            }
            $final = (float) $row['final_rating'];
            $finals[] = $final;
            $subjects[] = [
                'subject_code' => $row['subject_code'],
                'title' => $row['title'],
                'q1' => $this->num($row['q1']),
                'q2' => $this->num($row['q2']),
                'sem1' => $this->num($row['sem1']),
                'q3' => $this->num($row['q3']),
                'q4' => $this->num($row['q4']),
                'sem2' => $this->num($row['sem2']),
                'final_rating' => $final,
                'action_taken' => $row['action_taken'],
            ];
        }
        if ($subjects === []) {
            return null;
        }

        $average = round(array_sum($finals) / count($finals), 2);
        $passing = $this->passingMark();
        return [
            'school' => [
                'name' => 'HOLY FACE OF JESUS LYCEUM OF SAN JOSE, INC.',
                'short_name' => 'Holy Face',
                'address' => 'R & J Bldg., Blk 09, Lot 6 & 8, Mayon Ave., Amityville, San Jose, Rodriguez, Rizal, Philippines',
                'logo' => 'https://www.holyface.school/assets/img/branding/hf-logo.png',
            ],
            'student' => [
                'full_name' => $student['full_name'],
                'student_number' => $student['lrn'],
                'strand_code' => $student['strand_code'],
                'strand_name' => $student['strand_name'],
                'grade_level' => (int) $student['grade_level'],
                'section_code' => $student['section_code'],
                'school_year' => $student['school_year'],
                'school_year_id' => (int) $student['school_year_id'],
            ],
            'subjects' => $subjects,
            'general_average' => $average,
            'remarks' => $average >= $passing ? 'PASSED' : 'FAILED',
            'passing_mark' => $passing,
        ];
    }

    private function num(mixed $v): ?float
    {
        return $v === null || $v === '' ? null : (float) $v;
    }

    private function passingMark(): float
    {
        try {
            $v = Connection::get()->query(
                "SELECT setting_value FROM grading_settings WHERE setting_key = 'passing_mark' LIMIT 1"
            )->fetchColumn();
            if ($v !== false && $v !== null && $v !== '') {
                return (float) $v;
            }
        } catch (\Throwable) {
            // settings table may be missing on older installs
        }
        return 75.0;
    }
}
