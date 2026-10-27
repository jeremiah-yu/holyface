<?php
declare(strict_types=1);

namespace HolyFace\Api\Security;

use HolyFace\Api\Database\Connection;
use HolyFace\Api\Http\Request;
use HolyFace\Api\Http\Response;
use PDO;

final class AuthMiddleware
{
    public function __construct(private JwtService $jwt) {}

    /** @return array{user:array<string,mixed>, role:string}|null */
    public function authenticate(Request $request, ?array $allowedRoles = null): ?array
    {
        $token = $request->bearerToken();
        if (!$token) {
            Response::error('Authentication required.', 401);
            return null;
        }

        try {
            $claims = $this->jwt->decode($token);
        } catch (\Throwable) {
            Response::error('Invalid or expired token.', 401);
            return null;
        }

        if (($claims['type'] ?? '') !== 'access') {
            Response::error('Invalid token type.', 401);
            return null;
        }

        $role = (string) ($claims['role'] ?? '');
        if ($allowedRoles !== null && !in_array($role, $allowedRoles, true)) {
            Response::error('Forbidden.', 403);
            return null;
        }

        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.email, u.username, u.full_name, u.is_active, r.code AS role
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([(int) $claims['sub']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !(int) $user['is_active']) {
            Response::error('Account inactive.', 403);
            return null;
        }

        return ['user' => $user, 'role' => $role];
    }
}
