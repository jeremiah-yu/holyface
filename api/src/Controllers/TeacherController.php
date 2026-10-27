<?php
declare(strict_types=1);

namespace HolyFace\Api\Controllers;

use HolyFace\Api\Database\Connection;
use HolyFace\Api\Http\Request;
use HolyFace\Api\Http\Response;
use PDO;

final class TeacherController
{
    public function listStudents(Request $request, array $auth): void
    {
        if (!in_array($auth['role'], ['teacher', 'admin'], true)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $pdo = Connection::get();
        $sectionId = isset($request->query['class_section_id']) ? (int) $request->query['class_section_id'] : null;
        $csaId = isset($request->query['class_subject_assignment_id']) ? (int) $request->query['class_subject_assignment_id'] : 0;
        $quarter = (int) ($request->query['quarter'] ?? 0);
        $strand = trim((string) ($request->query['strand'] ?? ''));
        $gradeLevel = isset($request->query['grade_level']) ? (int) $request->query['grade_level'] : 0;
        $sectionCode = trim((string) ($request->query['section'] ?? ''));
        $schoolYearId = isset($request->query['school_year_id']) ? (int) $request->query['school_year_id'] : 0;
        $q = trim((string) ($request->query['q'] ?? ''));

        $sql = 'SELECT s.id AS student_id, u.id AS user_id, u.full_name, s.lrn, s.grade_level, s.section_code,
                       s.photo_path, st.code AS strand, e.id AS enrollment_id, cs.id AS class_section_id,
                       sy.label AS school_year, g.transmuted_grade AS current_grade, g.remarks
                FROM students s
                INNER JOIN users u ON u.id = s.user_id
                INNER JOIN strands st ON st.id = s.strand_id
                INNER JOIN enrollments e ON e.student_id = s.id AND e.status = "enrolled"
                INNER JOIN class_sections cs ON cs.id = e.class_section_id
                INNER JOIN school_years sy ON sy.id = cs.school_year_id';
        $params = [];

        if ($csaId > 0) {
            $sql .= ' INNER JOIN class_subject_assignments csa ON csa.class_section_id = cs.id AND csa.id = ?';
            $params[] = $csaId;
            if ($quarter > 0) {
                $sql .= ' LEFT JOIN grades g ON g.enrollment_id = e.id AND g.class_subject_assignment_id = csa.id AND g.quarter = ?';
                $params[] = $quarter;
            } else {
                $sql .= ' LEFT JOIN grades g ON 1 = 0';
            }
        } else {
            $sql .= ' LEFT JOIN grades g ON 1 = 0';
        }

        $where = [];
        if ($auth['role'] === 'teacher') {
            $sql .= ' INNER JOIN (
                        SELECT DISTINCT csa.class_section_id
                        FROM class_subject_assignments csa
                        INNER JOIN teachers t ON t.id = csa.teacher_id
                        WHERE t.user_id = ?
                      ) scoped ON scoped.class_section_id = cs.id';
            $params[] = (int) $auth['user']['id'];
        }
        if ($sectionId) {
            $where[] = 'cs.id = ?';
            $params[] = $sectionId;
        }

        if ($strand !== '') {
            $where[] = 'st.code = ?';
            $params[] = $strand;
        }
        if ($gradeLevel > 0) {
            $where[] = 's.grade_level = ?';
            $params[] = $gradeLevel;
        }
        if ($sectionCode !== '') {
            $where[] = 's.section_code = ?';
            $params[] = $sectionCode;
        }
        if ($schoolYearId > 0) {
            $where[] = 'cs.school_year_id = ?';
            $params[] = $schoolYearId;
        }
        if ($q !== '') {
            $where[] = '(u.full_name LIKE ? OR s.lrn LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY u.full_name';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        Response::json(['success' => true, 'students' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function mySections(Request $request, array $auth): void
    {
        if (!in_array($auth['role'], ['teacher', 'admin'], true)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $pdo = Connection::get();
        $sql = 'SELECT DISTINCT cs.id, cs.section_code, cs.grade_level, st.code AS strand, st.id AS strand_id,
                       sy.id AS school_year_id, sy.label AS school_year, sy.is_current,
                       sub.id AS subject_id, sub.title AS subject_title, sub.subject_code, sub.weight_profile_code,
                       csa.id AS class_subject_assignment_id, tu.full_name AS teacher_name,
                       gcw.written_work_pct, gcw.performance_task_pct, gcw.quarterly_assessment_pct
                FROM class_subject_assignments csa
                INNER JOIN class_sections cs ON cs.id = csa.class_section_id
                INNER JOIN school_years sy ON sy.id = cs.school_year_id
                INNER JOIN strands st ON st.id = cs.strand_id
                INNER JOIN subjects sub ON sub.id = csa.subject_id
                INNER JOIN teachers t ON t.id = csa.teacher_id
                INNER JOIN users tu ON tu.id = t.user_id
                LEFT JOIN grade_component_weights gcw ON gcw.class_subject_assignment_id = csa.id';
        $params = [];
        if ($auth['role'] === 'teacher') {
            $sql .= ' WHERE t.user_id = ?';
            $params[] = (int) $auth['user']['id'];
        }
        $sql .= ' ORDER BY csa.id, sy.label DESC, cs.grade_level, st.code, cs.section_code, sub.title';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        Response::json(['success' => true, 'sections' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function filters(Request $request, array $auth): void
    {
        if (!in_array($auth['role'], ['teacher', 'admin'], true)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $pdo = Connection::get();
        $years = $pdo->query('SELECT id, label, is_current FROM school_years ORDER BY label DESC')->fetchAll(PDO::FETCH_ASSOC);
        $strands = $pdo->query('SELECT id, code, name FROM strands ORDER BY code')->fetchAll(PDO::FETCH_ASSOC);
        $sections = $pdo->query(
            'SELECT DISTINCT section_code, grade_level FROM class_sections ORDER BY grade_level, section_code'
        )->fetchAll(PDO::FETCH_ASSOC);

        Response::json([
            'success' => true,
            'school_years' => $years,
            'strands' => $strands,
            'sections' => $sections,
        ]);
    }
}
