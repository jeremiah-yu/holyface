<?php
declare(strict_types=1);

namespace HolyFace\Api\Controllers;

use HolyFace\Api\Database\Connection;
use HolyFace\Api\Http\Request;
use HolyFace\Api\Http\Response;
use HolyFace\Api\Security\JwtService;
use HolyFace\Api\Services\ConsentService;
use HolyFace\Api\Services\ReportCardService;
use PDO;

final class StudentController
{
    public function __construct(
        private JwtService $jwt,
        private ConsentService $consent,
        private ReportCardService $reportCards = new ReportCardService(),
    ) {}

    public function digitalId(Request $request, array $auth): void
    {
        if (!$this->studentOnly($auth)) {
            return;
        }
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT s.id, s.lrn, s.grade_level, s.section_code, s.photo_path, s.guardian_name, s.guardian_contact,
                    st.code AS strand_code, st.name AS strand_name, sy.label AS school_year, sy.ends_on,
                    u.full_name, u.username,
                    e.status AS enrollment_status
             FROM students s
             INNER JOIN users u ON u.id = s.user_id
             INNER JOIN strands st ON st.id = s.strand_id
             INNER JOIN school_years sy ON sy.id = s.school_year_id
             LEFT JOIN enrollments e ON e.student_id = s.id AND e.status = "enrolled"
             WHERE s.user_id = ?
             ORDER BY e.id DESC
             LIMIT 1'
        );
        $stmt->execute([(int) $auth['user']['id']]);
        $card = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$card) {
            Response::error('Student record not found.', 404);
            return;
        }

        $enrolled = ($card['enrollment_status'] ?? '') === 'enrolled';
        $validUntil = $card['ends_on'] ?: date('Y-m-d', strtotime('+1 year'));
        $qr = $this->jwt->issueIdPass((int) $card['id']);

        Response::json([
            'success' => true,
            'digital_id' => [
                'student_id' => (int) $card['id'],
                'full_name' => $card['full_name'],
                'lrn' => $card['lrn'],
                'photo_path' => $card['photo_path'],
                'strand_code' => $card['strand_code'],
                'strand_name' => $card['strand_name'],
                'grade_level' => (int) $card['grade_level'],
                'section_code' => $card['section_code'],
                'school_year' => $card['school_year'],
                'valid_until' => $validUntil,
                'enrollment_status' => $enrolled ? 'enrolled' : 'not_enrolled',
                'id_active' => $enrolled,
                'guardian_name' => $card['guardian_name'],
                'guardian_contact' => $card['guardian_contact'],
                'qr_token' => $qr,
                'qr_expires_in' => 900,
                'school' => [
                    'name' => 'HOLY FACE OF JESUS LYCEUM OF SAN JOSE, INC.',
                    'short_name' => 'Holy Face',
                    'address' => 'R & J Bldg., Blk 09, Lot 6 & 8, Mayon Ave., Amityville, San Jose, Rodriguez, Rizal, Philippines',
                    'logo' => 'https://www.holyface.school/assets/img/branding/hf-logo.png',
                ],
                'generated_at' => date('c'),
            ],
        ]);
    }

    public function verifyId(Request $request, array $auth): void
    {
        if (!in_array($auth['role'], ['teacher', 'admin'], true)) {
            Response::error('Staff only.', 403);
            return;
        }
        $token = (string) (($request->body['token'] ?? $request->query['token'] ?? ''));
        if ($token === '') {
            Response::error('token required.', 422);
            return;
        }
        try {
            $claims = $this->jwt->decode($token);
        } catch (\Throwable) {
            Response::error('Invalid or expired ID pass.', 401);
            return;
        }
        if (($claims['type'] ?? '') !== 'id_pass') {
            Response::error('Not an ID pass token.', 400);
            return;
        }
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT s.id, u.full_name, s.lrn, s.grade_level, s.section_code, st.code AS strand,
                    e.status AS enrollment_status
             FROM students s
             INNER JOIN users u ON u.id = s.user_id
             INNER JOIN strands st ON st.id = s.strand_id
             LEFT JOIN enrollments e ON e.student_id = s.id AND e.status = "enrolled"
             WHERE s.id = ? LIMIT 1'
        );
        $stmt->execute([(int) $claims['sub']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            Response::error('Unknown student.', 404);
            return;
        }
        Response::json([
            'success' => true,
            'valid' => true,
            'enrolled' => ($row['enrollment_status'] ?? '') === 'enrolled',
            'student' => $row,
        ]);
    }

    public function academics(Request $request, array $auth): void
    {
        if (!$this->studentOnly($auth)) {
            return;
        }
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT sub.id AS subject_id, sub.title AS subject, sub.subject_code,
                    g.quarter, g.transmuted_grade, g.remarks,
                    fg.q1, fg.q2, fg.q3, fg.q4, fg.final_rating, fg.action_taken
             FROM students s
             INNER JOIN enrollments e ON e.student_id = s.id
             INNER JOIN class_subject_assignments csa ON csa.class_section_id = e.class_section_id
             INNER JOIN subjects sub ON sub.id = csa.subject_id
             LEFT JOIN grades g ON g.enrollment_id = e.id AND g.class_subject_assignment_id = csa.id
             LEFT JOIN final_grades fg ON fg.enrollment_id = e.id AND fg.class_subject_assignment_id = csa.id
             WHERE s.user_id = ?
             ORDER BY sub.title, g.quarter'
        );
        $stmt->execute([(int) $auth['user']['id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $warn = 75.0;
        $critical = 72.0;
        try {
            $t = $pdo->query('SELECT warn_below, critical_below FROM early_warning_thresholds WHERE code = "quarter_grade" LIMIT 1');
            $th = $t?->fetch(PDO::FETCH_ASSOC);
            if ($th) {
                $warn = (float) $th['warn_below'];
                $critical = (float) $th['critical_below'];
            }
        } catch (\Throwable) {
            // table may not exist yet
        }

        $bySubject = [];
        $finals = [];
        $atRisk = [];
        foreach ($rows as $row) {
            $code = $row['subject_code'];
            if (!isset($bySubject[$code])) {
                $bySubject[$code] = [
                    'subject' => $row['subject'],
                    'subject_code' => $code,
                    'quarters' => [],
                    'final_rating' => $row['final_rating'] !== null ? (float) $row['final_rating'] : null,
                    'action_taken' => $row['action_taken'],
                ];
                if ($row['final_rating'] !== null) {
                    $finals[] = (float) $row['final_rating'];
                }
            }
            if ($row['quarter'] !== null) {
                $g = $row['transmuted_grade'] !== null ? (float) $row['transmuted_grade'] : null;
                $bySubject[$code]['quarters'][] = [
                    'quarter' => (int) $row['quarter'],
                    'grade' => $g,
                    'remarks' => $row['remarks'],
                ];
                if ($g !== null && $g < $warn) {
                    $level = $g < $critical ? 'critical' : 'warn';
                    $atRisk[] = [
                        'subject' => $row['subject'],
                        'grade' => $g,
                        'quarter' => (int) $row['quarter'],
                        'level' => $level,
                        'badge' => 'At risk in ' . $row['subject'] . ': ' . $g,
                    ];
                }
            }
        }

        $general = $finals ? round(array_sum($finals) / count($finals), 2) : null;
        $trend = [];
        foreach ($bySubject as $sub) {
            foreach ($sub['quarters'] as $q) {
                if ($q['grade'] === null) {
                    continue;
                }
                $trend[$q['quarter']][] = $q['grade'];
            }
        }
        ksort($trend);
        $trendSeries = [];
        foreach ($trend as $q => $vals) {
            $trendSeries[] = ['quarter' => (int) $q, 'average' => round(array_sum($vals) / count($vals), 2)];
        }

        $cardAvailable = false;
        try {
            $cardAvailable = $this->reportCards->cardForUser((int) $auth['user']['id']) !== null;
        } catch (\Throwable) {
            $cardAvailable = false;
        }

        Response::json([
            'success' => true,
            'subjects' => array_values($bySubject),
            'general_average' => $general,
            'at_risk' => $atRisk,
            'trend' => $trendSeries,
            'thresholds' => ['warn_below' => $warn, 'critical_below' => $critical],
            'official_card_available' => $cardAvailable,
        ]);
    }

    public function reportCard(Request $request, array $auth): void
    {
        if (!$this->studentOnly($auth)) {
            return;
        }
        $userId = (int) $auth['user']['id'];
        try {
            $card = $this->reportCards->cardForUser($userId);
            $progress = $this->reportCards->progressForUser($userId);
        } catch (\Throwable) {
            Response::json([
                'success' => true,
                'available' => false,
                'card' => null,
                'progress' => ['total' => 0, 'submitted' => 0, 'missing' => []],
                'message' => 'Official grading cards are not ready on this server yet.',
            ]);
            return;
        }

        Response::json([
            'success' => true,
            'available' => $card !== null,
            'card' => $card,
            'progress' => $progress,
            'message' => $card !== null
                ? 'Official grading card.'
                : 'The official grading card is issued only after every subject teacher has submitted final grades.',
        ]);
    }

    public function schedule(Request $request, array $auth): void
    {
        if (!$this->studentOnly($auth)) {
            return;
        }
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT cs.weekday, cs.start_time, cs.end_time, cs.room, sub.title AS subject, sub.subject_code
             FROM students s
             INNER JOIN enrollments e ON e.student_id = s.id AND e.status = "enrolled"
             INNER JOIN class_schedules cs ON cs.class_section_id = e.class_section_id
             INNER JOIN subjects sub ON sub.id = cs.subject_id
             WHERE s.user_id = ?
             ORDER BY cs.weekday, cs.start_time'
        );
        try {
            $stmt->execute([(int) $auth['user']['id']]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $rows = [];
        }
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday'];
        foreach ($rows as &$r) {
            $r['weekday_name'] = $days[(int) $r['weekday']] ?? '';
        }
        Response::json(['success' => true, 'schedule' => $rows]);
    }

    public function announcements(Request $request, array $auth): void
    {
        if (!$this->studentOnly($auth)) {
            return;
        }
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT id, title, body, published_at FROM announcements
             WHERE audience IN ("public","all","students") AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY published_at DESC LIMIT 30'
        );
        $stmt->execute();
        Response::json(['success' => true, 'announcements' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function subjects(Request $request, array $auth): void
    {
        if (!$this->studentOnly($auth)) {
            return;
        }
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT DISTINCT sub.id, sub.title, sub.subject_code
             FROM students s
             INNER JOIN enrollments e ON e.student_id = s.id
             INNER JOIN class_subject_assignments csa ON csa.class_section_id = e.class_section_id
             INNER JOIN subjects sub ON sub.id = csa.subject_id
             WHERE s.user_id = ?
             ORDER BY sub.title'
        );
        $stmt->execute([(int) $auth['user']['id']]);
        Response::json(['success' => true, 'subjects' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function profile(Request $request, array $auth): void
    {
        if (!$this->studentOnly($auth)) {
            return;
        }
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT u.full_name, u.email, u.username, s.lrn, s.grade_level, s.section_code,
                    st.code AS strand, sy.label AS school_year, s.guardian_name
             FROM users u
             INNER JOIN students s ON s.user_id = u.id
             INNER JOIN strands st ON st.id = s.strand_id
             INNER JOIN school_years sy ON sy.id = s.school_year_id
             WHERE u.id = ?'
        );
        $stmt->execute([(int) $auth['user']['id']]);
        Response::json(['success' => true, 'profile' => $stmt->fetch(PDO::FETCH_ASSOC)]);
    }

    public function consentStatus(Request $request, array $auth): void
    {
        if (!$this->studentOnly($auth)) {
            return;
        }
        Response::json(['success' => true] + $this->consent->status((int) $auth['user']['id'], (string) $auth['role']));
    }

    public function recordConsent(Request $request, array $auth): void
    {
        if (!$this->studentOnly($auth)) {
            return;
        }
        $body = $request->body ?? [];
        $type = (string) ($body['consent_type'] ?? 'data_privacy');
        $allowed = ['terms', 'privacy', 'ai_policy', 'guardian', 'data_privacy'];
        if (!in_array($type, $allowed, true)) {
            Response::error('Unknown consent type.', 422);
            return;
        }
        $granted = (bool) ($body['granted'] ?? false);
        $version = (string) ($body['version'] ?? '1.0');
        $details = null;
        if ($type === 'guardian') {
            $gName = trim(strip_tags((string) ($body['guardian_name'] ?? '')));
            $rel = trim(strip_tags((string) ($body['relationship'] ?? '')));
            if ($granted && (mb_strlen($gName) < 2 || $rel === '')) {
                Response::error('Parent/guardian name and relationship are required.', 422);
                return;
            }
            $details = json_encode([
                'guardian_name' => $gName,
                'relationship' => $rel,
                'confirmed' => (bool) ($body['confirmed'] ?? false),
            ], JSON_THROW_ON_ERROR);
        }

        $pdo = Connection::get();
        $pdo->prepare(
            'INSERT INTO consent_logs (user_id, consent_type, version, granted, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([(int) $auth['user']['id'], $type, $version, $granted ? 1 : 0, $details, $request->ip]);

        Response::json(['success' => true, 'recorded' => true]);
    }

    public function requestCorrection(Request $request, array $auth): void
    {
        if (!$this->studentOnly($auth)) {
            return;
        }
        $body = $request->body ?? [];
        $field = trim(strip_tags((string) ($body['field_name'] ?? '')));
        $requested = trim(strip_tags((string) ($body['requested_value'] ?? '')));
        $reason = trim(strip_tags((string) ($body['reason'] ?? '')));
        $allowed = ['full_name', 'guardian_name', 'guardian_contact', 'section', 'photo', 'other'];
        if (!in_array($field, $allowed, true) || mb_strlen($requested) < 2) {
            Response::error('Please describe the correction.', 422);
            return;
        }
        $pdo = Connection::get();
        $pdo->prepare(
            'INSERT INTO data_correction_requests (user_id, field_name, requested_value, reason)
             VALUES (?, ?, ?, ?)'
        )->execute([(int) $auth['user']['id'], $field, mb_substr($requested, 0, 255), $reason]);
        Response::json(['success' => true]);
    }

    /** Weak subjects (anonymous grades only) for study-plan priority. */
    public function weakSubjects(int $userId): array
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT sub.title AS subject, MIN(g.transmuted_grade) AS grade
             FROM students s
             INNER JOIN enrollments e ON e.student_id = s.id
             INNER JOIN class_subject_assignments csa ON csa.class_section_id = e.class_section_id
             INNER JOIN subjects sub ON sub.id = csa.subject_id
             INNER JOIN grades g ON g.enrollment_id = e.id AND g.class_subject_assignment_id = csa.id
             WHERE s.user_id = ? AND g.transmuted_grade IS NOT NULL
             GROUP BY sub.title
             ORDER BY grade ASC'
        );
        $stmt->execute([$userId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = ['subject' => $row['subject'], 'grade' => (float) $row['grade']];
        }
        return $out;
    }

    private function studentOnly(array $auth): bool
    {
        if ($auth['role'] !== 'student') {
            Response::error('Students only.', 403);
            return false;
        }
        return true;
    }
}
