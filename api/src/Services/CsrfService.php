<?php
declare(strict_types=1);

namespace HolyFace\Api\Services;

use HolyFace\Api\Database\Connection;

/** CSRF tokens for web forms (session key = browser cookie id or anonymous session). */
final class CsrfService
{
    public function issue(string $sessionKey): string
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $pdo = Connection::get();
        $pdo->prepare(
            'INSERT INTO csrf_tokens (token_hash, session_key, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR))'
        )->execute([$hash, $sessionKey]);
        return $token;
    }

    public function validate(string $sessionKey, string $token): bool
    {
        $hash = hash('sha256', $token);
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT id FROM csrf_tokens WHERE token_hash = ? AND session_key = ? AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$hash, $sessionKey]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        $pdo->prepare('DELETE FROM csrf_tokens WHERE id = ?')->execute([$row['id']]);
        return true;
    }
}
