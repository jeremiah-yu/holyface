<?php
declare(strict_types=1);

namespace HolyFace\Api\Controllers;

use HolyFace\Api\Database\Connection;
use HolyFace\Api\Http\Request;
use HolyFace\Api\Http\Response;
use HolyFace\Api\Services\CsrfService;
use HolyFace\Api\Services\LoginRateLimiter;
use PDO;

final class PublicController
{
    public function __construct(
        private CsrfService $csrf,
    ) {}

    public function health(): void
    {
        Response::json([
            'success' => true,
            'service' => 'Holy Face AMGS API',
            'version' => '1.0.0',
            'time' => date('c'),
        ]);
    }

    public function strands(): void
    {
        $pdo = Connection::get();
        $rows = $pdo->query('SELECT code, name, description FROM strands ORDER BY code')->fetchAll(PDO::FETCH_ASSOC);
        Response::json(['success' => true, 'strands' => $rows]);
    }

    public function announcements(Request $request): void
    {
        $pdo = Connection::get();
        $audience = $request->query['audience'] ?? 'public';
        $stmt = $pdo->prepare(
            'SELECT id, title, body, audience, published_at FROM announcements
             WHERE audience IN ("public", "all", ?) AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY published_at DESC LIMIT 20'
        );
        $stmt->execute([$audience]);
        Response::json(['success' => true, 'announcements' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function csrfToken(Request $request): void
    {
        $sessionKey = $request->query['session_key'] ?? bin2hex(random_bytes(8));
        $token = $this->csrf->issue((string) $sessionKey);
        Response::json(['success' => true, 'csrf_token' => $token, 'session_key' => $sessionKey]);
    }

    public function studyMaterials(Request $request): void
    {
        $pdo = Connection::get();
        $subjectId = isset($request->query['subject_id']) ? (int) $request->query['subject_id'] : null;
        if ($subjectId) {
            $stmt = $pdo->prepare(
                'SELECT id, subject_id, title, tags, created_at FROM study_materials WHERE subject_id = ? ORDER BY created_at DESC'
            );
            $stmt->execute([$subjectId]);
        } else {
            $stmt = $pdo->query(
                'SELECT id, subject_id, title, tags, created_at FROM study_materials ORDER BY created_at DESC LIMIT 50'
            );
        }
        Response::json(['success' => true, 'materials' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function contact(Request $request): void
    {
        $body = $request->body ?? [];

        // Honeypot: bots fill hidden "website" — pretend success, store nothing.
        if (trim((string) ($body['website'] ?? '')) !== '') {
            Response::json(['success' => true]);
            return;
        }

        $limiter = new LoginRateLimiter();
        if ($limiter->isBlocked($request->ip, 'contact')) {
            Response::error('Too many messages. Please try again later.', 429);
            return;
        }

        $name = trim(strip_tags((string) ($body['name'] ?? '')));
        $email = trim((string) ($body['email'] ?? ''));
        $role = strtolower(trim((string) ($body['role'] ?? '')));
        $subject = trim(strip_tags((string) ($body['subject'] ?? '')));
        $message = trim(strip_tags((string) ($body['message'] ?? '')));

        $allowedRoles = ['student', 'parent', 'teacher', 'other'];
        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors['name'] = 'Please enter your name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if (!in_array($role, $allowedRoles, true)) {
            $errors['role'] = 'Please choose who you are.';
        }
        if (mb_strlen($subject) < 3 || mb_strlen($subject) > 160) {
            $errors['subject'] = 'Please enter a short subject.';
        }
        if (mb_strlen($message) < 10 || mb_strlen($message) > 2000) {
            $errors['message'] = 'Message must be 10–2000 characters.';
        }

        if ($errors) {
            Response::error('Please correct the highlighted fields.', 422, $errors);
            return;
        }

        $limiter->recordAttempt($request->ip, 'contact');

        try {
            $pdo = Connection::get();
            $stmt = $pdo->prepare(
                'INSERT INTO contact_inquiries (name, email, role, subject, message, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $name,
                $email,
                $role,
                $subject,
                $message,
                $request->ip,
                mb_substr($request->userAgent, 0, 255),
            ]);
        } catch (\Throwable) {
            Response::error('Unable to send your message right now.', 503);
            return;
        }

        Response::json(['success' => true, 'message' => 'Salamat. We received your message.']);
    }
}
