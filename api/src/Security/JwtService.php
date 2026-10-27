<?php
declare(strict_types=1);

namespace HolyFace\Api\Security;

use HolyFace\Api\Config\Env;

final class JwtService
{
    public function issueAccessToken(int $userId, string $role, string $username): string
    {
        $now = time();
        $ttl = (int) Env::get('JWT_ACCESS_TTL', '3600');
        $payload = [
            'iss' => Env::get('APP_URL', 'holyface-amgs'),
            'sub' => $userId,
            'role' => $role,
            'username' => $username,
            'iat' => $now,
            'exp' => $now + $ttl,
            'type' => 'access',
        ];
        return JwtCodec::encode($payload, Env::require('JWT_SECRET'));
    }

    /** Short-lived ID pass — student row id only, never the LRN. */
    public function issueIdPass(int $studentId): string
    {
        $now = time();
        $ttl = (int) Env::get('ID_PASS_TTL', '900');
        return JwtCodec::encode([
            'iss' => Env::get('APP_URL', 'holyface-amgs'),
            'sub' => $studentId,
            'type' => 'id_pass',
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => bin2hex(random_bytes(8)),
        ], Env::require('JWT_SECRET'));
    }

    /** @return array{sub:int,role:string,username:string,type:string} */
    public function decode(string $token): array
    {
        $decoded = JwtCodec::decode($token, Env::require('JWT_SECRET'));
        return $decoded;
    }
}
