<?php
declare(strict_types=1);

namespace HolyFace\Api\Services;

use HolyFace\Api\Database\Connection;
use PDO;

final class AuditService
{
    public function log(?int $actorId, string $action, ?string $entityType, ?string $entityId, ?array $metadata, ?string $ip): void
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, metadata, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $actorId,
            $action,
            $entityType,
            $entityId,
            $metadata ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
            $ip,
        ]);
    }

    public function logGradeChange(
        int $actorId,
        int $enrollmentId,
        int $csaId,
        int $quarter,
        string $field,
        ?string $old,
        ?string $new,
        ?string $ip,
        ?string $ua
    ): void {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO grade_audit_logs
             (actor_user_id, enrollment_id, class_subject_assignment_id, quarter, field_name, old_value, new_value, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$actorId, $enrollmentId, $csaId, $quarter, $field, $old, $new, $ip, $ua]);
    }
}
