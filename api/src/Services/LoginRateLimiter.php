<?php
declare(strict_types=1);

namespace HolyFace\Api\Services;

use HolyFace\Api\Config\Env;
use HolyFace\Api\Database\Connection;
use PDO;

final class LoginRateLimiter
{
    public function isBlocked(string $ip, string $username): bool
    {
        $window = (int) Env::get('LOGIN_RATE_WINDOW_SECONDS', '300');
        $max = (int) Env::get('LOGIN_MAX_ATTEMPTS', '5');

        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS c FROM login_rate_limits
             WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([$ip, $window]);
        $count = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        return $count >= $max;
    }

    public function recordAttempt(string $ip, string $username): void
    {
        $pdo = Connection::get();
        $pdo->prepare(
            'INSERT INTO login_rate_limits (ip_address, username_attempt) VALUES (?, ?)'
        )->execute([$ip, $username]);
    }

    public function clearForIp(string $ip): void
    {
        $pdo = Connection::get();
        $pdo->prepare('DELETE FROM login_rate_limits WHERE ip_address = ?')->execute([$ip]);
    }
}
