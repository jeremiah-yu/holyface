<?php
declare(strict_types=1);

namespace HolyFace\Api\Controllers;

use HolyFace\Api\Database\Connection;
use HolyFace\Api\Http\Request;
use HolyFace\Api\Http\Response;
use HolyFace\Api\Services\AcademicCalendar;
use HolyFace\Api\Services\AuditService;
use HolyFace\Api\Services\ReportCardService;
use PDO;

final class AdviserController
{
    public function __construct(
        private ReportCardService $cards,
        private AuditService $audit,
    ) {}

    public function myAdvisory(Request $request, array $auth): void
    {
        if (!in_array($auth['role'], ['teacher', 'admin'], true)) {
            Response::error('Forbidden.', 403);
            return;
        }
        $pdo = Connection::get();
        $sql = 'SELECT cs.id, cs.section_code, cs.grade_level, st.code AS strand, sy.label AS school_year,
                       sy.id AS school_year_id, adv.full_name AS adviser_name,
                       (SELECT COUNT(*) FROM enrollments e WHERE e.class_section_id = cs.id AND e.status = "enrolled") AS student_count
                FROM class_sections cs
                INNER JOIN strands st ON st.id = cs.strand_id
                INNER JOIN school_years sy ON sy.id = cs.school_year_id
                LEFT JOIN teachers t ON t.id = cs.adviser_teacher_id
                LEFT JOIN users adv ON adv.id = t.user_id';
        $params = [];
        if ($auth['role'] === 'teacher') {
            $sql .= ' WHERE t.user_id = ?';
            $params[] = (int) $auth['user']['id'];
        }
        $sql .= ' ORDER BY cs.grade_level, st.code, cs.section_code';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $sections = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['calendar'] = AcademicCalendar::forGrade((int) $row['grade_level']);
            $sections[] = $row;
        }
        Response::json(['success' => true, 'sections' => $sections]);
    }

    public function desk(Request $request, array $auth): void
    {
        $section = $this->requireAdvisory($request, $auth);
        if ($section === null) {
            return;
        }
        $pdo = Connection::get();
        $subjects = $pdo->prepare(
            'SELECT csa.id AS class_subject_assignment_id, sub.title, sub.subject_code, tu.full_name AS teacher_name,
                    SUM(CASE WHEN fg.is_submitted = 1 THEN 1 ELSE 0 END) AS submitted_count,
                    COUNT(e.id) AS student_count
             FROM class_subject_assignments csa
             INNER JOIN subjects sub ON sub.id = csa.subject_id
             INNER JOIN teachers t ON t.id = csa.teacher_id
             INNER JOIN users tu ON tu.id = t.user_id
             LEFT JOIN enrollments e ON e.class_section_id = csa.class_section_id AND e.status = "enrolled"
             LEFT JOIN final_grades fg ON fg.class_subject_assignment_id = csa.id AND fg.enrollment_id = e.id
             WHERE csa.class_section_id = ?
             GROUP BY csa.id, sub.title, sub.subject_code, tu.full_name
             ORDER BY sub.title'
        );
        $subjects->execute([(int) $section['id']]);

        $cards = $pdo->prepare(
            'SELECT COUNT(*) AS generated,
                    SUM(CASE WHEN orc.released_at IS NOT NULL THEN 1 ELSE 0 END) AS released
             FROM official_report_cards orc
             INNER JOIN enrollments e ON e.id = orc.enrollment_id
             WHERE e.class_section_id = ? AND e.status = "enrolled"'
        );
        $cards->execute([(int) $section['id']]);
        $cardStats = $cards->fetch(PDO::FETCH_ASSOC) ?: ['generated' => 0, 'released' => 0];

        Response::json([
            'success' => true,
            'section' => $section,
            'calendar' => AcademicCalendar::forGrade((int) $section['grade_level']),
            'subjects' => $subjects->fetchAll(PDO::FETCH_ASSOC),
            'cards' => [
                'generated' => (int) ($cardStats['generated'] ?? 0),
                'released' => (int) ($cardStats['released'] ?? 0),
                'students' => (int) $section['student_count'],
            ],
        ]);
    }

    public function sog(Request $request, array $auth): void
    {
        $section = $this->requireAdvisory($request, $auth);
        if ($section === null) {
            return;
        }
        Response::json([
            'success' => true,
            'section' => $section,
            'calendar' => AcademicCalendar::forGrade((int) $section['grade_level']),
            'rows' => $this->cards->sogForSection((int) $section['id']),
        ]);
    }

    public function generate(Request $request, array $auth): void
    {
        $section = $this->requireAdvisory($request, $auth, true);
        if ($section === null) {
            return;
        }
        $result = $this->cards->generateForSection((int) $section['id'], (int) $auth['user']['id']);
        $this->audit->log(
            (int) $auth['user']['id'],
            'cards.generate',
            'official_report_cards',
            (string) $section['id'],
            $result,
            $request->ip
        );
        Response::json(['success' => true] + $result);
    }

    public function release(Request $request, array $auth): void
    {
        $section = $this->requireAdvisory($request, $auth, true);
        if ($section === null) {
            return;
        }
        $result = $this->cards->releaseForSection((int) $section['id'], (int) $auth['user']['id']);
        $this->audit->log(
            (int) $auth['user']['id'],
            'cards.release',
            'official_report_cards',
            (string) $section['id'],
            $result,
            $request->ip
        );
        Response::json([
            'success' => true,
            'message' => 'Cards released. Students can open them on the mobile app.',
        ] + $result);
    }

    public function cards(Request $request, array $auth): void
    {
        $section = $this->requireAdvisory($request, $auth);
        if ($section === null) {
            return;
        }
        Response::json([
            'success' => true,
            'section' => $section,
            'cards' => $this->cards->cardsForSection((int) $section['id']),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function requireAdvisory(Request $request, array $auth, bool $write = false): ?array
    {
        if (!in_array($auth['role'], ['teacher', 'admin'], true)) {
            Response::error('Forbidden.', 403);
            return null;
        }
        $sectionId = (int) ($request->query['class_section_id'] ?? $request->body['class_section_id'] ?? 0);
        if ($sectionId <= 0) {
            Response::error('class_section_id required.', 422);
            return null;
        }
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT cs.id, cs.section_code, cs.grade_level, st.code AS strand, sy.label AS school_year,
                    sy.id AS school_year_id, t.user_id AS adviser_user_id, adv.full_name AS adviser_name,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.class_section_id = cs.id AND e.status = "enrolled") AS student_count
             FROM class_sections cs
             INNER JOIN strands st ON st.id = cs.strand_id
             INNER JOIN school_years sy ON sy.id = cs.school_year_id
             LEFT JOIN teachers t ON t.id = cs.adviser_teacher_id
             LEFT JOIN users adv ON adv.id = t.user_id
             WHERE cs.id = ?
             LIMIT 1'
        );
        $stmt->execute([$sectionId]);
        $section = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$section) {
            Response::error('Section not found.', 404);
            return null;
        }
        if ($auth['role'] === 'teacher' && (int) ($section['adviser_user_id'] ?? 0) !== (int) $auth['user']['id']) {
            Response::error('Only the class adviser can open this advisory.', 403);
            return null;
        }
        unset($section['adviser_user_id']);
        return $section;
    }
}
