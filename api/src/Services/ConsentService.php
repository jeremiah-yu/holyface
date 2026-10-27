<?php
declare(strict_types=1);

namespace HolyFace\Api\Services;

use HolyFace\Api\Database\Connection;
use PDO;

final class ConsentService
{
    /** @var array<string, mixed>|null */
    private static ?array $catalog = null;

    /** @return array<string, mixed> */
    public function catalog(): array
    {
        if (self::$catalog === null) {
            $path = dirname(__DIR__, 3) . '/shared/legal/policies.php';
            /** @var array<string, mixed> $data */
            $data = require $path;
            self::$catalog = $data;
        }
        return self::$catalog;
    }

    public function ensureSeeded(): void
    {
        $pdo = Connection::get();
        $count = (int) $pdo->query('SELECT COUNT(*) FROM policy_documents')->fetchColumn();
        if ($count > 0) {
            return;
        }
        $catalog = $this->catalog();
        $ins = $pdo->prepare(
            'INSERT INTO policy_documents (document_code, version, title, last_updated, body_json, is_current, published_at)
             VALUES (?, ?, ?, ?, ?, 1, NOW())'
        );
        foreach ($catalog['documents'] as $code => $doc) {
            $ins->execute([
                $code,
                $catalog['version'],
                $doc['title'],
                $catalog['last_updated'],
                json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function currentDocuments(): array
    {
        $this->ensureSeeded();
        $pdo = Connection::get();
        $rows = $pdo->query(
            'SELECT document_code, version, title, last_updated, body_json
             FROM policy_documents WHERE is_current = 1 ORDER BY FIELD(document_code, "terms", "privacy", "ai_policy")'
        )->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $body = json_decode((string) $row['body_json'], true);
            $out[] = [
                'code' => $row['document_code'],
                'version' => $row['version'],
                'title' => $row['title'],
                'last_updated' => $row['last_updated'],
                'sections' => $body['sections'] ?? [],
            ];
        }
        return $out;
    }

    /** @return array<string, string> code => version */
    public function currentVersions(): array
    {
        $map = [];
        foreach ($this->currentDocuments() as $doc) {
            $map[(string) $doc['code']] = (string) $doc['version'];
        }
        return $map;
    }

    public function isMinor(int $userId): bool
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT s.date_of_birth, r.code AS role
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             LEFT JOIN students s ON s.user_id = u.id
             WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['role'] !== 'student') {
            return false;
        }
        if (empty($row['date_of_birth'])) {
            return true;
        }
        $dob = new \DateTimeImmutable((string) $row['date_of_birth']);
        $age = $dob->diff(new \DateTimeImmutable('today'))->y;
        return $age < 18;
    }

    /** @return list<string> */
    public function requiredTypes(string $role, bool $minor): array
    {
        $need = ['terms', 'privacy', 'ai_policy'];
        if ($role === 'student' && $minor) {
            $need[] = 'guardian';
        }
        return $need;
    }

    public function isComplete(int $userId, string $role): bool
    {
        $versions = $this->currentVersions();
        $need = $this->requiredTypes($role, $this->isMinor($userId));
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT consent_type, version, granted
             FROM consent_logs WHERE user_id = ? ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([$userId]);
        $latest = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $type = (string) $row['consent_type'];
            if (!isset($latest[$type])) {
                $latest[$type] = $row;
            }
        }
        foreach ($need as $type) {
            if (empty($latest[$type]) || !(int) $latest[$type]['granted']) {
                return false;
            }
            if ($type === 'guardian') {
                continue;
            }
            $expected = $versions[$type] ?? '';
            if ($expected !== '' && (string) $latest[$type]['version'] !== $expected) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string, mixed> $body
     * @return array{complete: bool, recorded: list<string>}
     */
    public function accept(int $userId, string $role, array $body, string $ip, string $ua): array
    {
        $versions = $this->currentVersions();
        $minor = $this->isMinor($userId);
        $need = $this->requiredTypes($role, $minor);
        $guardianName = trim(strip_tags((string) ($body['guardian_name'] ?? '')));
        $guardianConfirmed = !empty($body['guardian_confirmed']);
        $device = mb_substr($ua, 0, 255);

        if ($minor && in_array('guardian', $need, true)) {
            if (mb_strlen($guardianName) < 2 || !$guardianConfirmed) {
                throw new \InvalidArgumentException('Parent or guardian name and confirmation are required for users under 18.');
            }
        }

        $pdo = Connection::get();
        $ins = $pdo->prepare(
            'INSERT INTO consent_logs
             (user_id, consent_type, version, granted, details, guardian_name, guardian_confirmed, ip_address, device_info)
             VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?)'
        );

        $recorded = [];
        foreach (['terms', 'privacy', 'ai_policy'] as $code) {
            $version = $versions[$code] ?? '';
            if ($version === '') {
                continue;
            }
            $details = json_encode([
                'document' => $code,
                'accepted_at' => date('c'),
                'ip_or_device' => $ip . ' | ' . $device,
                'scrolled' => !empty($body['scrolled']),
            ], JSON_THROW_ON_ERROR);
            $ins->execute([
                $userId,
                $code,
                $version,
                $details,
                $minor ? $guardianName : null,
                $minor && $guardianConfirmed ? 1 : 0,
                $ip,
                $device,
            ]);
            $recorded[] = $code;
        }

        if (in_array('guardian', $need, true)) {
            $gDetails = json_encode([
                'guardian_name' => $guardianName,
                'relationship' => trim(strip_tags((string) ($body['relationship'] ?? 'parent/guardian'))),
                'confirmed' => $guardianConfirmed,
                'accepted_at' => date('c'),
            ], JSON_THROW_ON_ERROR);
            $gVersion = $versions['privacy'] ?? ($versions['terms'] ?? '1.1');
            $ins->execute([$userId, 'guardian', $gVersion, $gDetails, $guardianName, 1, $ip, $device]);
            $recorded[] = 'guardian';
        }

        return ['complete' => $this->isComplete($userId, $role), 'recorded' => $recorded];
    }

    /**
     * @return array<string, mixed>
     */
    public function status(int $userId, string $role): array
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT consent_type, version, granted, guardian_name, guardian_confirmed, ip_address, device_info, created_at, details
             FROM consent_logs WHERE user_id = ? ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([$userId]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $minor = $this->isMinor($userId);
        return [
            'complete' => $this->isComplete($userId, $role),
            'requires_guardian' => $minor && $role === 'student',
            'current_versions' => $this->currentVersions(),
            'required' => $this->requiredTypes($role, $minor),
            'disclaimer' => $this->catalog()['disclaimer'],
            'history' => $logs,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array<string, mixed>>
     */
    public function publish(int $adminId, array $payload): array
    {
        $code = (string) ($payload['document_code'] ?? '');
        $version = trim((string) ($payload['version'] ?? ''));
        $title = trim((string) ($payload['title'] ?? ''));
        $lastUpdated = (string) ($payload['last_updated'] ?? date('Y-m-d'));
        $sections = $payload['sections'] ?? null;
        if (!in_array($code, ['terms', 'privacy', 'ai_policy'], true) || $version === '' || $title === '' || !is_array($sections)) {
            throw new \InvalidArgumentException('document_code, version, title, and sections are required.');
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE policy_documents SET is_current = 0 WHERE document_code = ?')->execute([$code]);
            $pdo->prepare(
                'INSERT INTO policy_documents (document_code, version, title, last_updated, body_json, is_current, published_by, published_at)
                 VALUES (?, ?, ?, ?, ?, 1, ?, NOW())
                 ON DUPLICATE KEY UPDATE title = VALUES(title), last_updated = VALUES(last_updated),
                 body_json = VALUES(body_json), is_current = 1, published_by = VALUES(published_by), published_at = NOW()'
            )->execute([
                $code,
                $version,
                $title,
                $lastUpdated,
                json_encode(['code' => $code, 'title' => $title, 'sections' => $sections], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $adminId,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $this->currentDocuments();
    }
}
