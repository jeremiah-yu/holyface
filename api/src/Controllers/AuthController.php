<?php
declare(strict_types=1);

namespace HolyFace\Api\Controllers;

use HolyFace\Api\Database\Connection;
use HolyFace\Api\Http\Request;
use HolyFace\Api\Http\Response;
use HolyFace\Api\Config\Env;
use HolyFace\Api\Security\JwtService;
use HolyFace\Api\Services\AuditService;
use HolyFace\Api\Services\ConsentService;
use HolyFace\Api\Services\LoginRateLimiter;
use PDO;

final class AuthController
{
    public function __construct(
        private JwtService $jwt,
        private LoginRateLimiter $limiter,
        private AuditService $audit,
        private ConsentService $consent,
    ) {}

    public function login(Request $request): void
    {
        $body = $request->body ?? [];
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($username === '' || $password === '') {
            Response::error('Username and password are required.', 422);
            return;
        }

        if ($this->limiter->isBlocked($request->ip, $username)) {
            Response::error('Too many login attempts. Please try again later.', 429);
            return;
        }

        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT u.*, r.code AS role FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             LEFT JOIN students s ON s.user_id = u.id
             WHERE u.username = ? OR u.email = ? OR s.lrn = ?
             LIMIT 1'
        );
        $stmt->execute([$username, $username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !(int) $user['is_active']) {
            $this->limiter->recordAttempt($request->ip, $username);
            Response::error('Invalid credentials.', 401);
            return;
        }

        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            Response::error('Account temporarily locked.', 423);
            return;
        }

        if (!password_verify($password, $user['password_hash'])) {
            $attempts = (int) $user['failed_login_attempts'] + 1;
            $max = (int) Env::get('LOGIN_MAX_ATTEMPTS', '5');
            $lockMinutes = (int) Env::get('LOGIN_LOCKOUT_MINUTES', '15');
            $lockedUntil = null;
            if ($attempts >= $max) {
                $lockedUntil = date('Y-m-d H:i:s', time() + $lockMinutes * 60);
                $attempts = 0;
            }
            $pdo->prepare(
                'UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?'
            )->execute([$attempts, $lockedUntil, $user['id']]);
            $this->limiter->recordAttempt($request->ip, $username);
            Response::error('Invalid credentials.', 401);
            return;
        }

        $this->limiter->clearForIp($request->ip);
        $pdo->prepare(
            'UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?'
        )->execute([$user['id']]);

        $access = $this->jwt->issueAccessToken((int) $user['id'], $user['role'], $user['username']);
        $refresh = bin2hex(random_bytes(32));
        $pdo->prepare(
            'INSERT INTO refresh_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))'
        )->execute([
            $user['id'],
            hash('sha256', $refresh),
            (int) Env::get('JWT_REFRESH_TTL', '604800'),
        ]);

        $this->audit->log((int) $user['id'], 'login_success', 'user', (string) $user['id'], null, $request->ip);

        $mustChange = (int) ($user['must_change_password'] ?? 0) === 1;
        $consentComplete = $this->consent->isComplete((int) $user['id'], (string) $user['role']);

        Response::json([
            'success' => true,
            'access_token' => $access,
            'refresh_token' => $refresh,
            'token_type' => 'Bearer',
            'expires_in' => (int) Env::get('JWT_ACCESS_TTL', '3600'),
            'must_change_password' => $mustChange,
            'consent_complete' => $consentComplete,
            'requires_guardian' => $this->consent->isMinor((int) $user['id']) && $user['role'] === 'student',
            'user' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
        ]);
    }

    public function me(Request $request, array $auth): void
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare('SELECT must_change_password FROM users WHERE id = ?');
        $stmt->execute([(int) $auth['user']['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        Response::json([
            'success' => true,
            'user' => $auth['user'],
            'role' => $auth['role'],
            'must_change_password' => (int) ($row['must_change_password'] ?? 0) === 1,
            'consent_complete' => $this->consent->isComplete((int) $auth['user']['id'], (string) $auth['role']),
            'requires_guardian' => $this->consent->isMinor((int) $auth['user']['id']) && $auth['role'] === 'student',
        ]);
    }

    public function changePassword(Request $request, array $auth): void
    {
        $body = $request->body ?? [];
        $current = (string) ($body['current_password'] ?? '');
        $new = (string) ($body['new_password'] ?? '');
        if ($current === '' || $new === '') {
            Response::error('Current and new password are required.', 422);
            return;
        }
        if (!$this->isStrong($new)) {
            Response::error('New password must be at least 10 characters with upper, lower, number, and symbol.', 422);
            return;
        }

        $pdo = Connection::get();
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([(int) $auth['user']['id']]);
        $hash = (string) ($stmt->fetchColumn() ?: '');
        if (!password_verify($current, $hash)) {
            Response::error('Current password is incorrect.', 401);
            return;
        }

        $pdo->prepare(
            'UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?'
        )->execute([
            password_hash($new, PASSWORD_ARGON2ID),
            (int) $auth['user']['id'],
        ]);
        $this->audit->log((int) $auth['user']['id'], 'password_changed', 'user', (string) $auth['user']['id'], null, $request->ip);
        Response::json(['success' => true]);
    }

    private function isStrong(string $password): bool
    {
        return strlen($password) >= 10
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/\d/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }

}
