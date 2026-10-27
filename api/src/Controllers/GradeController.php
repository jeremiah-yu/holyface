<?php
declare(strict_types=1);

namespace HolyFace\Api\Controllers;

use HolyFace\Api\Database\Connection;
use HolyFace\Api\Http\Request;
use HolyFace\Api\Http\Response;
use HolyFace\Api\Services\AuditService;
use HolyFace\Api\Services\GradeComputationService;
use HolyFace\Api\Services\ReportCardService;
use PDO;

final class GradeController
{
    public function __construct(
        private GradeComputationService $grades,
        private AuditService $audit,
        private ReportCardService $reportCards = new ReportCardService(),
    ) {}

    public function upsertComponent(Request $request, array $auth): void
    {
        if (!in_array($auth['role'], ['teacher', 'admin'], true)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $body = $request->body ?? [];
        $enrollmentId = (int) ($body['enrollment_id'] ?? 0);
        $csaId = (int) ($body['class_subject_assignment_id'] ?? 0);
        $quarter = (int) ($body['quarter'] ?? 0);
        $type = (string) ($body['component_type'] ?? '');
        $label = trim((string) ($body['item_label'] ?? ''));
        $max = (float) ($body['max_score'] ?? 0);
        $score = array_key_exists('score', $body) && $body['score'] !== null && $body['score'] !== ''
            ? (float) $body['score']
            : null;
        $status = (string) ($body['entry_status'] ?? 'scored');
        $componentId = isset($body['id']) ? (int) $body['id'] : null;
        $itemId = isset($body['grade_item_id']) ? (int) $body['grade_item_id'] : null;

        $allowedTypes = ['written_work', 'performance_task', 'quarterly_assessment'];
        $allowedStatus = ['scored', 'absent', 'excused'];
        if ($enrollmentId <= 0 || $csaId <= 0 || $quarter < 1 || $quarter > 4 || !in_array($type, $allowedTypes, true) || $label === '' || $max <= 0) {
            Response::error('Invalid grade component payload.', 422);
            return;
        }
        if (!in_array($status, $allowedStatus, true)) {
            Response::error('entry_status must be scored, absent, or excused.', 422);
            return;
        }
        if ($status === 'scored' && $score !== null && $score > $max) {
            Response::error('Score cannot exceed the highest possible score.', 422);
            return;
        }
        if ($status !== 'scored') {
            $score = null;
        }

        if ($auth['role'] === 'teacher' && !$this->teacherOwnsCsa((int) $auth['user']['id'], $csaId)) {
            Response::error('You are not assigned to this subject/section.', 403);
            return;
        }

        $pdo = Connection::get();
        if ($this->isLocked($pdo, $csaId, $quarter) && $auth['role'] !== 'admin') {
            Response::error('This grading period is locked. Ask the Registrar to unlock it.', 423);
            return;
        }

        if ($componentId) {
            $oldStmt = $pdo->prepare('SELECT score, entry_status FROM grade_components WHERE id = ? AND enrollment_id = ?');
            $oldStmt->execute([$componentId, $enrollmentId]);
            $old = $oldStmt->fetch(PDO::FETCH_ASSOC);
            $pdo->prepare(
                'UPDATE grade_components SET score = ?, entry_status = ?, recorded_by = ?, recorded_at = NOW()
                 WHERE id = ? AND enrollment_id = ?'
            )->execute([$score, $status, (int) $auth['user']['id'], $componentId, $enrollmentId]);

            if ($old && ((string) $old['score'] !== (string) $score || ($old['entry_status'] ?? '') !== $status)) {
                $this->audit->logGradeChange(
                    (int) $auth['user']['id'],
                    $enrollmentId,
                    $csaId,
                    $quarter,
                    'component_score:' . $componentId,
                    $old['score'] !== null ? (string) $old['score'] : ($old['entry_status'] ?? null),
                    $score !== null ? (string) $score : $status,
                    $request->ip,
                    $request->userAgent
                );
            }
        } else {
            $pdo->prepare(
                'INSERT INTO grade_components (grade_item_id, enrollment_id, class_subject_assignment_id, quarter, component_type, item_label, max_score, score, entry_status, recorded_by, recorded_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([$itemId ?: null, $enrollmentId, $csaId, $quarter, $type, $label, $max, $score, $status, (int) $auth['user']['id']]);
        }

        $computed = $this->grades->recomputeQuarter($enrollmentId, $csaId, $quarter, (int) $auth['user']['id']);
        Response::json(['success' => true, 'quarter_grade' => $computed]);
    }

    public function listComponents(Request $request, array $auth): void
    {
        if (!in_array($auth['role'], ['teacher', 'admin'], true)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $enrollmentId = (int) ($request->query['enrollment_id'] ?? 0);
        $csaId = (int) ($request->query['class_subject_assignment_id'] ?? 0);
        $quarter = (int) ($request->query['quarter'] ?? 1);

        if ($enrollmentId <= 0 || $csaId <= 0) {
            Response::error('enrollment_id and class_subject_assignment_id required.', 422);
            return;
        }

        if ($auth['role'] === 'teacher' && !$this->teacherOwnsCsa((int) $auth['user']['id'], $csaId)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT * FROM grade_components
             WHERE enrollment_id = ? AND class_subject_assignment_id = ? AND quarter = ?
             ORDER BY component_type, id'
        );
        $stmt->execute([$enrollmentId, $csaId, $quarter]);
        Response::json(['success' => true, 'components' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function grid(Request $request, array $auth): void
    {
        if (!in_array($auth['role'], ['teacher', 'admin'], true)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $csaId = (int) ($request->query['class_subject_assignment_id'] ?? 0);
        $quarter = (int) ($request->query['quarter'] ?? 1);
        if ($csaId <= 0 || $quarter < 1 || $quarter > 4) {
            Response::error('class_subject_assignment_id and quarter required.', 422);
            return;
        }
        if ($auth['role'] === 'teacher' && !$this->teacherOwnsCsa((int) $auth['user']['id'], $csaId)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $pdo = Connection::get();
        $classStmt = $pdo->prepare(
            'SELECT csa.id AS class_subject_assignment_id, cs.id AS class_section_id, cs.section_code, cs.grade_level,
                    st.code AS strand, sy.id AS school_year_id, sy.label AS school_year,
                    sub.id AS subject_id, sub.title AS subject_title, sub.subject_code, sub.weight_profile_code,
                    t.employee_no, u.full_name AS teacher_name
             FROM class_subject_assignments csa
             INNER JOIN class_sections cs ON cs.id = csa.class_section_id
             INNER JOIN school_years sy ON sy.id = cs.school_year_id
             INNER JOIN strands st ON st.id = cs.strand_id
             INNER JOIN subjects sub ON sub.id = csa.subject_id
             INNER JOIN teachers t ON t.id = csa.teacher_id
             INNER JOIN users u ON u.id = t.user_id
             WHERE csa.id = ? LIMIT 1'
        );
        $classStmt->execute([$csaId]);
        $class = $classStmt->fetch(PDO::FETCH_ASSOC);
        if (!$class) {
            Response::error('Class assignment not found.', 404);
            return;
        }

        $itemsStmt = $pdo->prepare(
            'SELECT id, component_type, item_label, max_score, sort_order
             FROM grade_items
             WHERE class_subject_assignment_id = ? AND quarter = ?
             ORDER BY FIELD(component_type, "written_work", "performance_task", "quarterly_assessment"), sort_order, id'
        );
        $itemsStmt->execute([$csaId, $quarter]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $studentsStmt = $pdo->prepare(
            'SELECT e.id AS enrollment_id, s.id AS student_id, u.full_name, s.lrn, s.photo_path,
                    fg.q1, fg.q2, fg.q3, fg.q4, fg.sem1, fg.sem2, fg.final_rating, fg.action_taken,
                    fg.is_submitted, g.written_work_avg, g.performance_task_avg, g.quarterly_assessment_score,
                    g.initial_grade, g.transmuted_grade, g.remarks, g.computed_at
             FROM enrollments e
             INNER JOIN students s ON s.id = e.student_id
             INNER JOIN users u ON u.id = s.user_id
             LEFT JOIN grades g ON g.enrollment_id = e.id AND g.class_subject_assignment_id = ? AND g.quarter = ?
             LEFT JOIN final_grades fg ON fg.enrollment_id = e.id AND fg.class_subject_assignment_id = ?
             WHERE e.class_section_id = ? AND e.status = "enrolled"
             ORDER BY u.full_name'
        );
        $studentsStmt->execute([$csaId, $quarter, $csaId, (int) $class['class_section_id']]);
        $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

        $cellStmt = $pdo->prepare(
            'SELECT gc.id, gc.enrollment_id, gc.grade_item_id, gc.score, gc.entry_status, gc.max_score, gc.recorded_at
             FROM grade_components gc
             WHERE gc.class_subject_assignment_id = ? AND gc.quarter = ?'
        );
        $cellStmt->execute([$csaId, $quarter]);
        $cellsByEnroll = [];
        foreach ($cellStmt->fetchAll(PDO::FETCH_ASSOC) as $cell) {
            $eid = (int) $cell['enrollment_id'];
            $iid = (int) ($cell['grade_item_id'] ?? 0);
            if ($iid <= 0) {
                continue;
            }
            $cellsByEnroll[$eid][$iid] = [
                'component_id' => (int) $cell['id'],
                'score' => $cell['score'] !== null ? (float) $cell['score'] : null,
                'entry_status' => $cell['entry_status'] ?? 'scored',
                'max_score' => (float) $cell['max_score'],
                'recorded_at' => $cell['recorded_at'],
            ];
        }

        foreach ($students as &$student) {
            $eid = (int) $student['enrollment_id'];
            $student['cells'] = $cellsByEnroll[$eid] ?? new \stdClass();
        }
        unset($student);

        Response::json([
            'success' => true,
            'class' => $class,
            'quarter' => $quarter,
            'locked' => $this->isLocked($pdo, $csaId, $quarter),
            'weights' => $this->grades->weightsForCsa($csaId),
            'passing_mark' => $this->grades->passingMark(),
            'items' => $items,
            'students' => $students,
        ]);
    }

    public function createItem(Request $request, array $auth): void
    {
        if (!in_array($auth['role'], ['teacher', 'admin'], true)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $body = $request->body ?? [];
        $csaId = (int) ($body['class_subject_assignment_id'] ?? 0);
        $quarter = (int) ($body['quarter'] ?? 0);
        $type = (string) ($body['component_type'] ?? '');
        $label = trim((string) ($body['item_label'] ?? ''));
        $max = (float) ($body['max_score'] ?? 0);
        $allowedTypes = ['written_work', 'performance_task', 'quarterly_assessment'];

        if ($csaId <= 0 || $quarter < 1 || $quarter > 4 || !in_array($type, $allowedTypes, true) || $label === '' || $max <= 0) {
            Response::error('Invalid grade item payload.', 422);
            return;
        }
        if ($auth['role'] === 'teacher' && !$this->teacherOwnsCsa((int) $auth['user']['id'], $csaId)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $pdo = Connection::get();
        if ($this->isLocked($pdo, $csaId, $quarter) && $auth['role'] !== 'admin') {
            Response::error('This grading period is locked. Ask the Registrar to unlock it.', 423);
            return;
        }

        $sortStmt = $pdo->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM grade_items
             WHERE class_subject_assignment_id = ? AND quarter = ? AND component_type = ?'
        );
        $sortStmt->execute([$csaId, $quarter, $type]);
        $sort = (int) $sortStmt->fetchColumn();

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO grade_items (class_subject_assignment_id, quarter, component_type, item_label, max_score, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$csaId, $quarter, $type, $label, $max, $sort]);
            $itemId = (int) $pdo->lastInsertId();

            $enStmt = $pdo->prepare(
                'SELECT e.id FROM enrollments e
                 INNER JOIN class_subject_assignments csa ON csa.class_section_id = e.class_section_id
                 WHERE csa.id = ? AND e.status = "enrolled"'
            );
            $enStmt->execute([$csaId]);
            $ins = $pdo->prepare(
                'INSERT INTO grade_components (grade_item_id, enrollment_id, class_subject_assignment_id, quarter, component_type, item_label, max_score, score, entry_status, recorded_by, recorded_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NULL, "scored", ?, NOW())'
            );
            $actor = (int) $auth['user']['id'];
            foreach ($enStmt->fetchAll(PDO::FETCH_COLUMN) as $enrollmentId) {
                $ins->execute([$itemId, (int) $enrollmentId, $csaId, $quarter, $type, $label, $max, $actor]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::error('Could not create grade item.', 500);
            return;
        }

        $this->audit->log((int) $auth['user']['id'], 'grade_item.create', 'grade_items', (string) $itemId, [
            'csa' => $csaId,
            'quarter' => $quarter,
            'label' => $label,
        ], $request->ip);

        Response::json(['success' => true, 'id' => $itemId]);
    }

    public function updateItem(Request $request, array $auth): void
    {
        if (!in_array($auth['role'], ['teacher', 'admin'], true)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $body = $request->body ?? [];
        $itemId = (int) ($body['id'] ?? 0);
        if ($itemId <= 0) {
            Response::error('id required.', 422);
            return;
        }

        $pdo = Connection::get();
        $item = $pdo->prepare('SELECT * FROM grade_items WHERE id = ?');
        $item->execute([$itemId]);
        $row = $item->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            Response::error('Item not found.', 404);
            return;
        }
        $csaId = (int) $row['class_subject_assignment_id'];
        $quarter = (int) $row['quarter'];
        if ($auth['role'] === 'teacher' && !$this->teacherOwnsCsa((int) $auth['user']['id'], $csaId)) {
            Response::error('Forbidden.', 403);
            return;
        }
        if ($this->isLocked($pdo, $csaId, $quarter) && $auth['role'] !== 'admin') {
            Response::error('This grading period is locked. Ask the Registrar to unlock it.', 423);
            return;
        }

        $label = trim((string) ($body['item_label'] ?? $row['item_label']));
        $max = (float) ($body['max_score'] ?? $row['max_score']);
        if ($label === '' || $max <= 0) {
            Response::error('Invalid item_label or max_score.', 422);
            return;
        }

        $over = $pdo->prepare(
            'SELECT COUNT(*) FROM grade_components WHERE grade_item_id = ? AND score IS NOT NULL AND score > ?'
        );
        $over->execute([$itemId, $max]);
        if ((int) $over->fetchColumn() > 0) {
            Response::error('Some student scores exceed the new highest possible score.', 422);
            return;
        }

        $pdo->prepare('UPDATE grade_items SET item_label = ?, max_score = ? WHERE id = ?')->execute([$label, $max, $itemId]);
        $pdo->prepare('UPDATE grade_components SET item_label = ?, max_score = ? WHERE grade_item_id = ?')->execute([$label, $max, $itemId]);

        $enStmt = $pdo->prepare('SELECT DISTINCT enrollment_id FROM grade_components WHERE grade_item_id = ?');
        $enStmt->execute([$itemId]);
        foreach ($enStmt->fetchAll(PDO::FETCH_COLUMN) as $eid) {
            $this->grades->recomputeQuarter((int) $eid, $csaId, $quarter, (int) $auth['user']['id']);
        }

        $this->audit->log((int) $auth['user']['id'], 'grade_item.update', 'grade_items', (string) $itemId, [
            'label' => $label,
            'max_score' => $max,
        ], $request->ip);

        Response::json(['success' => true]);
    }

    public function batchScores(Request $request, array $auth): void
    {
        if (!in_array($auth['role'], ['teacher', 'admin'], true)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $body = $request->body ?? [];
        $csaId = (int) ($body['class_subject_assignment_id'] ?? 0);
        $quarter = (int) ($body['quarter'] ?? 0);
        $cells = $body['cells'] ?? [];
        if ($csaId <= 0 || $quarter < 1 || $quarter > 4 || !is_array($cells)) {
            Response::error('class_subject_assignment_id, quarter, and cells required.', 422);
            return;
        }
        if ($auth['role'] === 'teacher' && !$this->teacherOwnsCsa((int) $auth['user']['id'], $csaId)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $pdo = Connection::get();
        if ($this->isLocked($pdo, $csaId, $quarter) && $auth['role'] !== 'admin') {
            Response::error('This grading period is locked. Ask the Registrar to unlock it.', 423);
            return;
        }

        $conflicts = [];
        $errors = [];
        $touched = [];
        $actor = (int) $auth['user']['id'];

        $itemStmt = $pdo->prepare('SELECT id, max_score, item_label, component_type FROM grade_items WHERE id = ? AND class_subject_assignment_id = ? AND quarter = ?');
        $cellStmt = $pdo->prepare('SELECT id, score, entry_status, recorded_at FROM grade_components WHERE enrollment_id = ? AND grade_item_id = ? LIMIT 1');
        $upd = $pdo->prepare(
            'UPDATE grade_components SET score = ?, entry_status = ?, recorded_by = ?, recorded_at = NOW() WHERE id = ?'
        );
        $ins = $pdo->prepare(
            'INSERT INTO grade_components (grade_item_id, enrollment_id, class_subject_assignment_id, quarter, component_type, item_label, max_score, score, entry_status, recorded_by, recorded_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        foreach ($cells as $cell) {
            $enrollmentId = (int) ($cell['enrollment_id'] ?? 0);
            $itemId = (int) ($cell['grade_item_id'] ?? 0);
            $status = (string) ($cell['entry_status'] ?? 'scored');
            $score = array_key_exists('score', $cell) && $cell['score'] !== null && $cell['score'] !== ''
                ? (float) $cell['score']
                : null;
            $clientUpdated = (string) ($cell['client_updated_at'] ?? '');

            if ($enrollmentId <= 0 || $itemId <= 0) {
                $errors[] = ['cell' => $cell, 'message' => 'enrollment_id and grade_item_id required'];
                continue;
            }
            if (!in_array($status, ['scored', 'absent', 'excused'], true)) {
                $errors[] = ['enrollment_id' => $enrollmentId, 'grade_item_id' => $itemId, 'message' => 'Invalid entry_status'];
                continue;
            }

            $itemStmt->execute([$itemId, $csaId, $quarter]);
            $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                $errors[] = ['enrollment_id' => $enrollmentId, 'grade_item_id' => $itemId, 'message' => 'Unknown grade item'];
                continue;
            }
            $max = (float) $item['max_score'];
            if ($status !== 'scored') {
                $score = null;
            } elseif ($score !== null && $score > $max) {
                $errors[] = ['enrollment_id' => $enrollmentId, 'grade_item_id' => $itemId, 'message' => 'Score cannot exceed the highest possible score'];
                continue;
            }

            $cellStmt->execute([$enrollmentId, $itemId]);
            $existing = $cellStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing && $clientUpdated !== '' && $existing['recorded_at'] && strtotime((string) $existing['recorded_at']) > strtotime($clientUpdated)) {
                $conflicts[] = [
                    'enrollment_id' => $enrollmentId,
                    'grade_item_id' => $itemId,
                    'server_score' => $existing['score'],
                    'server_status' => $existing['entry_status'],
                    'server_recorded_at' => $existing['recorded_at'],
                ];
                continue;
            }

            $oldScore = $existing['score'] ?? null;
            $oldStatus = $existing['entry_status'] ?? null;

            if ($existing) {
                $upd->execute([$score, $status, $actor, $existing['id']]);
            } else {
                $ins->execute([
                    $itemId,
                    $enrollmentId,
                    $csaId,
                    $quarter,
                    $item['component_type'],
                    $item['item_label'],
                    $max,
                    $score,
                    $status,
                    $actor,
                ]);
            }

            if ((string) $oldScore !== (string) $score || (string) $oldStatus !== $status) {
                $this->audit->logGradeChange(
                    $actor,
                    $enrollmentId,
                    $csaId,
                    $quarter,
                    'item:' . $itemId,
                    $oldScore !== null ? (string) $oldScore : ($oldStatus !== null ? (string) $oldStatus : null),
                    $score !== null ? (string) $score : $status,
                    $request->ip,
                    $request->userAgent
                );
            }
            $touched[$enrollmentId] = true;
        }

        foreach (array_keys($touched) as $eid) {
            $this->grades->recomputeQuarter((int) $eid, $csaId, $quarter, $actor);
        }

        $status = 200;
        if ($conflicts !== []) {
            $status = 409;
        } elseif ($errors !== []) {
            $status = 422;
        }
        Response::json([
            'success' => $errors === [] && $conflicts === [],
            'saved' => count($touched),
            'conflicts' => $conflicts,
            'errors' => $errors,
        ], $status);
    }

    public function submitFinal(Request $request, array $auth): void
    {
        if (!in_array($auth['role'], ['teacher', 'admin'], true)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $body = $request->body ?? [];
        $csaId = (int) ($body['class_subject_assignment_id'] ?? 0);
        $confirmed = !empty($body['confirmed']);
        if ($csaId <= 0) {
            Response::error('class_subject_assignment_id required.', 422);
            return;
        }
        if (!$confirmed) {
            Response::error('Confirmation required before submitting final grades.', 422);
            return;
        }
        if ($auth['role'] === 'teacher' && !$this->teacherOwnsCsa((int) $auth['user']['id'], $csaId)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $pdo = Connection::get();
        $enroll = $pdo->prepare(
            'SELECT e.id
             FROM enrollments e
             INNER JOIN class_subject_assignments csa ON csa.class_section_id = e.class_section_id
             WHERE csa.id = ? AND e.status = "enrolled"'
        );
        $enroll->execute([$csaId]);
        foreach ($enroll->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $this->grades->recomputeFinal((int) $row['id'], $csaId);
        }

        $pdo->prepare(
            'UPDATE final_grades SET is_submitted = 1, submitted_at = NOW(), submitted_by = ?
             WHERE class_subject_assignment_id = ?'
        )->execute([(int) $auth['user']['id'], $csaId]);

        $cards = ['issued' => 0, 'waiting' => 0];
        try {
            $cards = $this->reportCards->issueReadyCardsForClass($csaId);
        } catch (\Throwable) {
            // table may not exist until migration 006 is applied
        }

        $this->audit->log((int) $auth['user']['id'], 'grades.submit_final', 'final_grades', (string) $csaId, $cards, $request->ip);
        Response::json([
            'success' => true,
            'submitted' => true,
            'report_cards' => $cards,
        ]);
    }

    public function dashboard(Request $request, array $auth): void
    {
        if (!in_array($auth['role'], ['teacher', 'admin'], true)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $csaId = (int) ($request->query['class_subject_assignment_id'] ?? 0);
        $quarter = (int) ($request->query['quarter'] ?? 1);
        if ($csaId <= 0) {
            Response::error('class_subject_assignment_id required.', 422);
            return;
        }
        if ($auth['role'] === 'teacher' && !$this->teacherOwnsCsa((int) $auth['user']['id'], $csaId)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT g.transmuted_grade, g.remarks, u.full_name, s.lrn, e.id AS enrollment_id
             FROM enrollments e
             INNER JOIN students s ON s.id = e.student_id
             INNER JOIN users u ON u.id = s.user_id
             INNER JOIN class_subject_assignments csa ON csa.class_section_id = e.class_section_id
             LEFT JOIN grades g ON g.enrollment_id = e.id AND g.class_subject_assignment_id = csa.id AND g.quarter = ?
             WHERE csa.id = ? AND e.status = "enrolled"'
        );
        $stmt->execute([$quarter, $csaId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grades = [];
        foreach ($rows as $row) {
            if ($row['transmuted_grade'] !== null) {
                $grades[] = (float) $row['transmuted_grade'];
            }
        }
        $count = count($grades);
        $passing = $this->grades->passingMark();
        $passed = count(array_filter($grades, fn ($g) => $g >= $passing));
        $buckets = ['90-100' => 0, '85-89' => 0, '80-84' => 0, '75-79' => 0, 'below-75' => 0];
        foreach ($grades as $g) {
            if ($g >= 90) {
                $buckets['90-100']++;
            } elseif ($g >= 85) {
                $buckets['85-89']++;
            } elseif ($g >= 80) {
                $buckets['80-84']++;
            } elseif ($g >= 75) {
                $buckets['75-79']++;
            } else {
                $buckets['below-75']++;
            }
        }

        Response::json([
            'success' => true,
            'summary' => [
                'students' => count($rows),
                'with_grades' => $count,
                'average' => $count ? round(array_sum($grades) / $count, 2) : null,
                'highest' => $count ? max($grades) : null,
                'lowest' => $count ? min($grades) : null,
                'passing_rate' => $count ? round(($passed / $count) * 100, 1) : null,
                'passing_mark' => $passing,
            ],
            'distribution' => $buckets,
            'roster' => $rows,
        ]);
    }

    public function earlyWarning(Request $request, array $auth): void
    {
        if (!in_array($auth['role'], ['teacher', 'admin'], true)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $pdo = Connection::get();
        $threshold = (float) ($request->query['threshold'] ?? $this->setting($pdo, 'early_warning_threshold', '78'));
        $drop = (float) ($request->query['drop_points'] ?? $this->setting($pdo, 'drop_points', '8'));
        $csaId = isset($request->query['class_subject_assignment_id']) ? (int) $request->query['class_subject_assignment_id'] : 0;

        $sql = 'SELECT e.id AS enrollment_id, s.lrn, u.full_name, s.photo_path, s.grade_level, s.section_code,
                       st.code AS strand, sub.title AS subject_title, csa.id AS class_subject_assignment_id,
                       fg.q1, fg.q2, fg.q3, fg.q4, fg.final_rating
                FROM enrollments e
                INNER JOIN students s ON s.id = e.student_id
                INNER JOIN users u ON u.id = s.user_id
                INNER JOIN strands st ON st.id = s.strand_id
                INNER JOIN class_subject_assignments csa ON csa.class_section_id = e.class_section_id
                INNER JOIN subjects sub ON sub.id = csa.subject_id
                LEFT JOIN final_grades fg ON fg.enrollment_id = e.id AND fg.class_subject_assignment_id = csa.id
                WHERE e.status = "enrolled"';
        $params = [];
        if ($csaId > 0) {
            $sql .= ' AND csa.id = ?';
            $params[] = $csaId;
        } elseif ($auth['role'] === 'teacher') {
            $sql .= ' AND csa.teacher_id = (SELECT id FROM teachers WHERE user_id = ? LIMIT 1)';
            $params[] = (int) $auth['user']['id'];
        }
        $sql .= ' ORDER BY u.full_name';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $alerts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $reasons = [];
            $latest = null;
            foreach (['q4', 'q3', 'q2', 'q1'] as $q) {
                if ($row[$q] !== null) {
                    $latest = (float) $row[$q];
                    break;
                }
            }
            if ($latest !== null && $latest < $threshold) {
                $reasons[] = 'Below threshold (' . $threshold . ')';
            }
            $seq = [];
            foreach (['q1', 'q2', 'q3', 'q4'] as $q) {
                if ($row[$q] !== null) {
                    $seq[] = (float) $row[$q];
                }
            }
            for ($i = 1, $n = count($seq); $i < $n; $i++) {
                if ($seq[$i - 1] - $seq[$i] >= $drop) {
                    $reasons[] = 'Sharp drop of ' . round($seq[$i - 1] - $seq[$i], 1) . ' points';
                    break;
                }
            }
            if ($reasons) {
                $row['reasons'] = $reasons;
                $row['latest_grade'] = $latest;
                $alerts[] = $row;
            }
        }

        Response::json([
            'success' => true,
            'threshold' => $threshold,
            'drop_points' => $drop,
            'alerts' => $alerts,
        ]);
    }

    public function missingGrades(Request $request, array $auth): void
    {
        if (!in_array($auth['role'], ['teacher', 'admin'], true)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $csaId = (int) ($request->query['class_subject_assignment_id'] ?? 0);
        $quarter = (int) ($request->query['quarter'] ?? 1);
        if ($csaId <= 0) {
            Response::error('class_subject_assignment_id required.', 422);
            return;
        }
        if ($auth['role'] === 'teacher' && !$this->teacherOwnsCsa((int) $auth['user']['id'], $csaId)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT u.full_name, s.lrn, gi.item_label, gi.component_type, gc.score, gc.entry_status
             FROM enrollments e
             INNER JOIN students s ON s.id = e.student_id
             INNER JOIN users u ON u.id = s.user_id
             INNER JOIN class_subject_assignments csa ON csa.class_section_id = e.class_section_id
             INNER JOIN grade_items gi ON gi.class_subject_assignment_id = csa.id AND gi.quarter = ?
             LEFT JOIN grade_components gc ON gc.grade_item_id = gi.id AND gc.enrollment_id = e.id
             WHERE csa.id = ? AND e.status = "enrolled"
               AND (gc.id IS NULL OR (gc.score IS NULL AND IFNULL(gc.entry_status, "scored") = "scored"))
             ORDER BY u.full_name, gi.component_type, gi.sort_order'
        );
        $stmt->execute([$quarter, $csaId]);
        Response::json(['success' => true, 'missing' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private function teacherOwnsCsa(int $userId, int $csaId): bool
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM class_subject_assignments csa
             INNER JOIN teachers t ON t.id = csa.teacher_id
             WHERE csa.id = ? AND t.user_id = ? LIMIT 1'
        );
        $stmt->execute([$csaId, $userId]);
        return (bool) $stmt->fetchColumn();
    }

    private function isLocked(PDO $pdo, int $csaId, int $quarter): bool
    {
        $stmt = $pdo->prepare(
            'SELECT l.is_locked
             FROM grading_period_locks l
             INNER JOIN class_subject_assignments csa ON csa.id = ?
             INNER JOIN class_sections cs ON cs.id = csa.class_section_id AND cs.school_year_id = l.school_year_id
             WHERE l.quarter = ?
             LIMIT 1'
        );
        $stmt->execute([$csaId, $quarter]);
        return (int) $stmt->fetchColumn() === 1;
    }

    private function setting(PDO $pdo, string $key, string $default): string
    {
        $stmt = $pdo->prepare('SELECT setting_value FROM grading_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (string) $value : $default;
    }
}
