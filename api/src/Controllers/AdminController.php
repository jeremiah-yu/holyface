<?php
declare(strict_types=1);

namespace HolyFace\Api\Controllers;

use HolyFace\Api\Database\Connection;
use HolyFace\Api\Http\Request;
use HolyFace\Api\Http\Response;
use HolyFace\Api\Services\AuditService;
use PDO;
use PDOException;

final class AdminController
{
    private const MAX_BYTES = 15_728_640;
    private const CATEGORIES = ['circular', 'memo', 'form', 'policy', 'backup', 'student_record', 'other'];
    private const MIME = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv' => 'text/csv',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'zip' => 'application/zip',
    ];

    public function __construct(private AuditService $audit) {}

    public function overview(Request $request, array $auth): void
    {
        if (!$this->requireAdmin($auth)) {
            return;
        }
        $pdo = Connection::get();
        $countRole = static function (string $code) use ($pdo): int {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.code = ?'
            );
            $stmt->execute([$code]);
            return (int) $stmt->fetchColumn();
        };

        $files = (int) $pdo->query('SELECT COUNT(*) FROM registrar_files')->fetchColumn();
        $bytes = (int) $pdo->query('SELECT COALESCE(SUM(byte_size), 0) FROM registrar_files')->fetchColumn();
        $locked = (int) $pdo->query('SELECT COUNT(*) FROM grading_period_locks WHERE is_locked = 1')->fetchColumn();
        $open = (int) $pdo->query('SELECT COUNT(*) FROM grading_period_locks WHERE is_locked = 0')->fetchColumn();
        $recent = $pdo->query(
            'SELECT a.id, a.action, a.entity_type, a.entity_id, a.created_at, u.full_name AS actor
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.actor_user_id
             ORDER BY a.id DESC LIMIT 8'
        )->fetchAll(PDO::FETCH_ASSOC);

        Response::json([
            'success' => true,
            'counts' => [
                'students' => $countRole('student'),
                'teachers' => $countRole('teacher'),
                'admins' => $countRole('admin'),
                'active_users' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn(),
                'files' => $files,
                'file_bytes' => $bytes,
                'locked_periods' => $locked,
                'open_periods' => $open,
            ],
            'recent_audit' => $recent,
        ]);
    }

    public function listUsers(Request $request, array $auth): void
    {
        if (!$this->requireAdmin($auth)) {
            return;
        }
        $role = trim((string) ($request->query['role'] ?? ''));
        $q = trim((string) ($request->query['q'] ?? ''));
        $sql = 'SELECT u.id, u.username, u.email, u.full_name, u.is_active, u.last_login_at, u.must_change_password,
                       r.code AS role, t.employee_no, t.department, s.lrn AS student_number, s.grade_level, s.section_code,
                       st.code AS strand
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                LEFT JOIN teachers t ON t.user_id = u.id
                LEFT JOIN students s ON s.user_id = u.id
                LEFT JOIN strands st ON st.id = s.strand_id';
        $params = [];
        $where = [];
        if (in_array($role, ['admin', 'teacher', 'student'], true)) {
            $where[] = 'r.code = ?';
            $params[] = $role;
        }
        if ($q !== '') {
            $where[] = '(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR s.lrn LIKE ? OR t.employee_no LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY r.id, u.full_name';
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        Response::json(['success' => true, 'users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function createUser(Request $request, array $auth): void
    {
        if (!$this->requireAdmin($auth)) {
            return;
        }
        $body = $request->body ?? [];
        $role = trim((string) ($body['role'] ?? ''));
        $fullName = trim((string) ($body['full_name'] ?? ''));
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        if (!in_array($role, ['admin', 'teacher', 'student'], true) || $fullName === '' || $email === '' || $username === '') {
            Response::error('Role, full name, email, and username are required.', 422);
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Enter a valid email address.', 422);
            return;
        }
        if ($password === '') {
            $password = 'HolyFace@' . bin2hex(random_bytes(4));
        }
        if (!$this->isStrong($password)) {
            Response::error('Password must be at least 10 characters with upper, lower, number, and symbol.', 422);
            return;
        }

        $pdo = Connection::get();
        $roleId = $this->roleId($pdo, $role);
        if ($roleId === 0) {
            Response::error('Unknown role.', 422);
            return;
        }

        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                'INSERT INTO users (role_id, email, username, password_hash, full_name, must_change_password)
                 VALUES (?, ?, ?, ?, ?, 1)'
            )->execute([
                $roleId,
                $email,
                $username,
                password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT),
                $fullName,
            ]);
            $userId = (int) $pdo->lastInsertId();

            if ($role === 'teacher') {
                $emp = trim((string) ($body['employee_no'] ?? ''));
                if ($emp === '') {
                    $emp = 'T-' . date('Y') . '-' . str_pad((string) $userId, 4, '0', STR_PAD_LEFT);
                }
                $pdo->prepare('INSERT INTO teachers (user_id, employee_no, department) VALUES (?, ?, ?)')
                    ->execute([$userId, $emp, trim((string) ($body['department'] ?? '')) ?: null]);
            }

            if ($role === 'student') {
                $this->insertStudent($pdo, $userId, $body);
            }

            $pdo->commit();
        } catch (\InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::error($e->getMessage(), 422);
            return;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $msg = str_contains($e->getMessage(), 'Duplicate')
                ? 'Username, email, employee number, or student number is already in use.'
                : 'Could not create the account.';
            Response::error($msg, 409);
            return;
        }

        $this->audit->log((int) $auth['user']['id'], 'user_created', 'user', (string) $userId, [
            'role' => $role,
            'username' => $username,
        ], $request->ip);

        Response::json([
            'success' => true,
            'user_id' => $userId,
            'temporary_password' => $password,
            'must_change_password' => true,
        ], 201);
    }

    public function updateUser(Request $request, array $auth): void
    {
        if (!$this->requireAdmin($auth)) {
            return;
        }
        $body = $request->body ?? [];
        $id = (int) ($body['id'] ?? 0);
        if ($id < 1) {
            Response::error('User id is required.', 422);
            return;
        }
        if ($id === (int) $auth['user']['id'] && array_key_exists('is_active', $body) && !(int) $body['is_active']) {
            Response::error('You cannot deactivate your own account.', 422);
            return;
        }

        $pdo = Connection::get();
        $exists = $pdo->prepare('SELECT id FROM users WHERE id = ?');
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) {
            Response::error('User not found.', 404);
            return;
        }

        if (array_key_exists('is_active', $body)) {
            $active = ((int) $body['is_active']) === 1 ? 1 : 0;
            $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$active, $id]);
            $this->audit->log((int) $auth['user']['id'], $active ? 'user_activated' : 'user_deactivated', 'user', (string) $id, null, $request->ip);
        }

        $temporary = null;
        if (!empty($body['reset_password'])) {
            $temporary = is_string($body['reset_password']) && strlen($body['reset_password']) >= 10
                ? (string) $body['reset_password']
                : 'HolyFace@' . bin2hex(random_bytes(4));
            if (!$this->isStrong($temporary)) {
                Response::error('Reset password must be at least 10 characters with upper, lower, number, and symbol.', 422);
                return;
            }
            $pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 1, failed_login_attempts = 0, locked_until = NULL WHERE id = ?')
                ->execute([
                    password_hash($temporary, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT),
                    $id,
                ]);
            $this->audit->log((int) $auth['user']['id'], 'user_password_reset', 'user', (string) $id, null, $request->ip);
        }

        Response::json(['success' => true, 'temporary_password' => $temporary]);
    }

    public function listFiles(Request $request, array $auth): void
    {
        if (!$this->requireAdmin($auth)) {
            return;
        }
        $category = trim((string) ($request->query['category'] ?? ''));
        $q = trim((string) ($request->query['q'] ?? ''));
        $sql = 'SELECT f.id, f.title, f.original_name, f.mime, f.byte_size, f.category, f.notes, f.created_at,
                       u.full_name AS uploaded_by_name
                FROM registrar_files f
                INNER JOIN users u ON u.id = f.uploaded_by';
        $params = [];
        $where = [];
        if ($category !== '' && in_array($category, self::CATEGORIES, true)) {
            $where[] = 'f.category = ?';
            $params[] = $category;
        }
        if ($q !== '') {
            $where[] = '(f.title LIKE ? OR f.original_name LIKE ? OR f.notes LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY f.id DESC';
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        Response::json(['success' => true, 'categories' => self::CATEGORIES, 'files' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function uploadFile(Request $request, array $auth): void
    {
        if (!$this->requireAdmin($auth)) {
            return;
        }
        $body = $request->body ?? [];
        $filename = basename((string) ($body['filename'] ?? ''));
        $base64 = (string) ($body['content_base64'] ?? '');
        $title = trim((string) ($body['title'] ?? ''));
        $category = trim((string) ($body['category'] ?? 'other'));
        $notes = trim((string) ($body['notes'] ?? ''));
        if ($filename === '' || $base64 === '') {
            Response::error('Choose a file to keep in the registrar vault.', 422);
            return;
        }
        if (!in_array($category, self::CATEGORIES, true)) {
            $category = 'other';
        }
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!isset(self::MIME[$ext])) {
            Response::error('Allowed files: PDF, Word, Excel, CSV, TXT, JPG, PNG, WEBP, ZIP.', 422);
            return;
        }
        $bin = base64_decode($base64, true);
        if ($bin === false) {
            Response::error('The file could not be read.', 422);
            return;
        }
        if (strlen($bin) > self::MAX_BYTES) {
            Response::error('Each file must be 15 MB or smaller.', 422);
            return;
        }
        if ($title === '') {
            $title = pathinfo($filename, PATHINFO_FILENAME);
        }

        $stored = bin2hex(random_bytes(16));
        $dir = $this->storageDir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            Response::error('Could not create the registrar file vault.', 500);
            return;
        }
        if (file_put_contents($dir . DIRECTORY_SEPARATOR . $stored, $bin) === false) {
            Response::error('Could not store the file.', 500);
            return;
        }

        $pdo = Connection::get();
        $pdo->prepare(
            'INSERT INTO registrar_files (title, original_name, stored_name, mime, byte_size, category, notes, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $title,
            $filename,
            $stored,
            self::MIME[$ext],
            strlen($bin),
            $category,
            $notes !== '' ? $notes : null,
            (int) $auth['user']['id'],
        ]);
        $id = (int) $pdo->lastInsertId();
        $this->audit->log((int) $auth['user']['id'], 'file_uploaded', 'registrar_file', (string) $id, [
            'filename' => $filename,
            'category' => $category,
            'bytes' => strlen($bin),
        ], $request->ip);

        Response::json(['success' => true, 'id' => $id], 201);
    }

    public function downloadFile(Request $request, array $auth): void
    {
        if (!$this->requireAdmin($auth)) {
            return;
        }
        $id = (int) ($request->query['id'] ?? 0);
        $row = $this->fileRow($id);
        if ($row === null) {
            Response::error('File not found.', 404);
            return;
        }
        $path = $this->storageDir() . DIRECTORY_SEPARATOR . $row['stored_name'];
        if (!is_file($path)) {
            Response::error('The file is missing from storage.', 404);
            return;
        }
        $this->audit->log((int) $auth['user']['id'], 'file_downloaded', 'registrar_file', (string) $id, [
            'filename' => $row['original_name'],
        ], $request->ip);
        Response::json([
            'success' => true,
            'id' => $id,
            'filename' => $row['original_name'],
            'mime' => $row['mime'],
            'content_base64' => base64_encode((string) file_get_contents($path)),
        ]);
    }

    public function deleteFile(Request $request, array $auth): void
    {
        if (!$this->requireAdmin($auth)) {
            return;
        }
        $id = (int) (($request->body['id'] ?? $request->query['id'] ?? 0));
        $row = $this->fileRow($id);
        if ($row === null) {
            Response::error('File not found.', 404);
            return;
        }
        $path = $this->storageDir() . DIRECTORY_SEPARATOR . $row['stored_name'];
        if (is_file($path)) {
            @unlink($path);
        }
        Connection::get()->prepare('DELETE FROM registrar_files WHERE id = ?')->execute([$id]);
        $this->audit->log((int) $auth['user']['id'], 'file_deleted', 'registrar_file', (string) $id, [
            'filename' => $row['original_name'],
        ], $request->ip);
        Response::json(['success' => true]);
    }

    public function audit(Request $request, array $auth): void
    {
        if (!$this->requireAdmin($auth)) {
            return;
        }
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $limit = min(100, max(10, (int) ($request->query['limit'] ?? 40)));
        $offset = ($page - 1) * $limit;
        $pdo = Connection::get();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
        $stmt = $pdo->prepare(
            'SELECT a.id, a.action, a.entity_type, a.entity_id, a.metadata, a.ip_address, a.created_at,
                    u.full_name AS actor, u.username
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.actor_user_id
             ORDER BY a.id DESC
             LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $meta = $row['metadata'] ?? null;
            $row['metadata'] = is_string($meta) ? json_decode($meta, true) : $meta;
        }
        unset($row);
        Response::json(['success' => true, 'total' => $total, 'page' => $page, 'limit' => $limit, 'entries' => $rows]);
    }

    public function gradeAudit(Request $request, array $auth): void
    {
        if (!$this->requireAdmin($auth)) {
            return;
        }
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $limit = min(100, max(10, (int) ($request->query['limit'] ?? 40)));
        $offset = ($page - 1) * $limit;
        $pdo = Connection::get();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM grade_audit_logs')->fetchColumn();
        $stmt = $pdo->prepare(
            'SELECT g.id, g.quarter, g.field_name, g.old_value, g.new_value, g.created_at, g.ip_address,
                    u.full_name AS actor, su.full_name AS student_name, s.lrn AS student_number, sub.title AS subject
             FROM grade_audit_logs g
             INNER JOIN users u ON u.id = g.actor_user_id
             INNER JOIN enrollments e ON e.id = g.enrollment_id
             INNER JOIN students s ON s.id = e.student_id
             INNER JOIN users su ON su.id = s.user_id
             INNER JOIN class_subject_assignments csa ON csa.id = g.class_subject_assignment_id
             INNER JOIN subjects sub ON sub.id = csa.subject_id
             ORDER BY g.id DESC
             LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute();
        Response::json([
            'success' => true,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'entries' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    public function backup(Request $request, array $auth): void
    {
        if (!$this->requireAdmin($auth)) {
            return;
        }
        $pdo = Connection::get();
        $settings = $pdo->query('SELECT setting_key, setting_value, source_note FROM grading_settings')->fetchAll(PDO::FETCH_ASSOC);
        $profiles = $pdo->query(
            'SELECT code, name, written_work_pct, performance_task_pct, quarterly_assessment_pct, confirmed, source_note
             FROM grading_weight_profiles ORDER BY id'
        )->fetchAll(PDO::FETCH_ASSOC);
        $locks = $pdo->query(
            'SELECT sy.label AS school_year, l.quarter, l.is_locked, l.locked_at
             FROM grading_period_locks l INNER JOIN school_years sy ON sy.id = l.school_year_id
             ORDER BY l.school_year_id, l.quarter'
        )->fetchAll(PDO::FETCH_ASSOC);
        $users = $pdo->query(
            'SELECT u.username, u.email, u.full_name, u.is_active, r.code AS role, t.employee_no, s.lrn AS student_number
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             LEFT JOIN teachers t ON t.user_id = u.id
             LEFT JOIN students s ON s.user_id = u.id
             ORDER BY u.id'
        )->fetchAll(PDO::FETCH_ASSOC);
        $files = $pdo->query(
            'SELECT id, title, original_name, mime, byte_size, category, notes, created_at FROM registrar_files ORDER BY id'
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->audit->log((int) $auth['user']['id'], 'system_backup_exported', 'system', null, [
            'users' => count($users),
            'files' => count($files),
        ], $request->ip);

        Response::json([
            'success' => true,
            'generated_at' => gmdate('c'),
            'school' => 'HOLY FACE OF JESUS LYCEUM OF SAN JOSE, INC.',
            'note' => 'Password hashes and file binaries are not included. Download vault files separately.',
            'grading_settings' => $settings,
            'weight_profiles' => $profiles,
            'locks' => $locks,
            'users' => $users,
            'files' => $files,
        ]);
    }

    private function requireAdmin(array $auth): bool
    {
        if (($auth['role'] ?? '') !== 'admin') {
            Response::error('Registrar / Admin only.', 403);
            return false;
        }
        return true;
    }

    private function isStrong(string $password): bool
    {
        return strlen($password) >= 10
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/\d/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }

    private function roleId(PDO $pdo, string $code): int
    {
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE code = ?');
        $stmt->execute([$code]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /** @param array<string, mixed> $body */
    private function insertStudent(PDO $pdo, int $userId, array $body): void
    {
        $lrn = preg_replace('/\D+/', '', (string) ($body['student_number'] ?? $body['lrn'] ?? '')) ?? '';
        if (strlen($lrn) < 8 || strlen($lrn) > 20) {
            throw new \InvalidArgumentException('Student number must be 8 to 20 digits.');
        }
        $strandId = (int) ($body['strand_id'] ?? 0);
        $gradeLevel = (int) ($body['grade_level'] ?? 11);
        $section = trim((string) ($body['section_code'] ?? ''));
        $syId = (int) ($body['school_year_id'] ?? 0);
        if ($syId < 1) {
            $syId = (int) $pdo->query('SELECT id FROM school_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1')->fetchColumn();
        }
        if ($strandId < 1 || $section === '' || $syId < 1) {
            throw new \InvalidArgumentException('Student number, strand, grade level, and section are required.');
        }
        $pdo->prepare(
            'INSERT INTO students (user_id, lrn, strand_id, grade_level, section_code, school_year_id, guardian_name, guardian_contact)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $userId,
            $lrn,
            $strandId,
            $gradeLevel,
            $section,
            $syId,
            trim((string) ($body['guardian_name'] ?? '')) ?: null,
            trim((string) ($body['guardian_contact'] ?? '')) ?: null,
        ]);
        $studentId = (int) $pdo->lastInsertId();
        $find = $pdo->prepare(
            'SELECT id FROM class_sections WHERE school_year_id = ? AND strand_id = ? AND grade_level = ? AND section_code = ? LIMIT 1'
        );
        $find->execute([$syId, $strandId, $gradeLevel, $section]);
        $sectionId = (int) ($find->fetchColumn() ?: 0);
        if ($sectionId > 0) {
            $pdo->prepare('INSERT INTO enrollments (student_id, class_section_id, status, enrolled_at) VALUES (?, ?, "enrolled", NOW())')
                ->execute([$studentId, $sectionId]);
        }
    }

    /** @return array<string, mixed>|null */
    private function fileRow(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = Connection::get()->prepare('SELECT * FROM registrar_files WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function storageDir(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'registrar';
    }
}
