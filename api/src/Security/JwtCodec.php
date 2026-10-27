<?php
declare(strict_types=1);

namespace HolyFace\Api\Security;

/**
 * Minimal HS256 JWT encode/decode (no Composer required).
 */
final class JwtCodec
{
    public static function encode(array $payload, string $secret): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $segments = [
            self::b64(json_encode($header, JSON_THROW_ON_ERROR)),
            self::b64(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = self::b64($signature);
        return implode('.', $segments);
    }

    /** @return array<string, mixed> */
    public static function decode(string $jwt, string $secret): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Invalid token format.');
        }
        [$h64, $p64, $s64] = $parts;
        $signingInput = $h64 . '.' . $p64;
        $expected = self::b64(hash_hmac('sha256', $signingInput, $secret, true));
        if (!hash_equals($expected, $s64)) {
            throw new \InvalidArgumentException('Invalid token signature.');
        }
        $payload = json_decode(self::b64Decode($p64), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Invalid token payload.');
        }
        if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
            throw new \InvalidArgumentException('Token expired.');
        }
        return $payload;
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64Decode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid base64.');
        }
        return $decoded;
    }
}
