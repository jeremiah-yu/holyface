<?php
declare(strict_types=1);

namespace HolyFace\Api\Controllers;

use HolyFace\Api\Database\Connection;
use HolyFace\Api\Http\Request;
use HolyFace\Api\Http\Response;
use HolyFace\Api\Services\AuditService;
use PDO;

final class SettingsController
{
    public function __construct(private AuditService $audit) {}

    public function get(Request $request, array $auth): void
    {
        if (!in_array($auth['role'], ['teacher', 'admin'], true)) {
            Response::error('Forbidden.', 403);
            return;
        }

        $pdo = Connection::get();
        $settings = [];
        foreach ($pdo->query('SELECT setting_key, setting_value, source_note FROM grading_settings') as $row) {
            $settings[$row['setting_key']] = [
                'value' => $row['setting_value'],
                'source_note' => $row['source_note'],
            ];
        }

        $profiles = $pdo->query(
            'SELECT id, code, name, written_work_pct, performance_task_pct, quarterly_assessment_pct, confirmed, source_note
             FROM grading_weight_profiles ORDER BY id'
        )->fetchAll(PDO::FETCH_ASSOC);

        $locks = $pdo->query(
            'SELECT l.id, l.school_year_id, sy.label AS school_year, l.quarter, l.is_locked, l.locked_at, u.full_name AS locked_by_name
             FROM grading_period_locks l
             INNER JOIN school_years sy ON sy.id = l.school_year_id
             LEFT JOIN users u ON u.id = l.locked_by
             ORDER BY l.school_year_id, l.quarter'
        )->fetchAll(PDO::FETCH_ASSOC);

        $table = $pdo->query(
            'SELECT id, initial_min, initial_max, transmuted_grade FROM transmutation_table ORDER BY transmuted_grade DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        Response::json([
            'success' => true,
            'settings' => $settings,
            'weight_profiles' => $profiles,
            'locks' => $locks,
            'transmutation_table' => $table,
            'can_edit' => $auth['role'] === 'admin',
        ]);
    }

    public function saveSettings(Request $request, array $auth): void
    {
        if ($auth['role'] !== 'admin') {
            Response::error('Registrar / Admin only.', 403);
            return;
        }

        $body = $request->body ?? [];
        $pdo = Connection::get();
        $allowed = ['passing_mark', 'early_warning_threshold', 'drop_points', 'inactivity_minutes'];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $body)) {
                continue;
            }
            $value = trim((string) $body[$key]);
            if ($value === '' || !is_numeric($value)) {
                Response::error("Invalid value for {$key}.", 422);
                return;
            }
            $pdo->prepare(
                'INSERT INTO grading_settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            )->execute([$key, $value]);
        }

        $this->audit->log((int) $auth['user']['id'], 'settings.update', 'grading_settings', null, $body, $request->ip);
        $this->get($request, $auth);
    }

    public function saveWeightProfiles(Request $request, array $auth): void
    {
        if ($auth['role'] !== 'admin') {
            Response::error('Registrar / Admin only.', 403);
            return;
        }

        $profiles = $request->body['profiles'] ?? [];
        if (!is_array($profiles) || $profiles === []) {
            Response::error('profiles array required.', 422);
            return;
        }

        $pdo = Connection::get();
        $upd = $pdo->prepare(
            'UPDATE grading_weight_profiles
             SET name = ?, written_work_pct = ?, performance_task_pct = ?, quarterly_assessment_pct = ?, confirmed = ?, source_note = ?
             WHERE id = ?'
        );

        foreach ($profiles as $p) {
            $ww = (float) ($p['written_work_pct'] ?? 0);
            $pt = (float) ($p['performance_task_pct'] ?? 0);
            $qa = (float) ($p['quarterly_assessment_pct'] ?? 0);
            if (abs($ww + $pt + $qa - 100) > 0.01) {
                Response::error('Weights for each profile must sum to 100%.', 422);
                return;
            }
            $upd->execute([
                (string) ($p['name'] ?? ''),
                $ww,
                $pt,
                $qa,
                !empty($p['confirmed']) ? 1 : 0,
                (string) ($p['source_note'] ?? ''),
                (int) ($p['id'] ?? 0),
            ]);
        }

        // Push confirmed profiles onto class assignments that use that profile.
        $pdo->exec(
            'UPDATE grade_component_weights gcw
             INNER JOIN class_subject_assignments csa ON csa.id = gcw.class_subject_assignment_id
             INNER JOIN subjects s ON s.id = csa.subject_id
             INNER JOIN grading_weight_profiles p ON p.code = s.weight_profile_code
             SET gcw.written_work_pct = p.written_work_pct,
                 gcw.performance_task_pct = p.performance_task_pct,
                 gcw.quarterly_assessment_pct = p.quarterly_assessment_pct
             WHERE p.confirmed = 1'
        );

        $this->audit->log((int) $auth['user']['id'], 'settings.weights', 'grading_weight_profiles', null, ['count' => count($profiles)], $request->ip);
        $this->get($request, $auth);
    }

    public function setLock(Request $request, array $auth): void
    {
        if ($auth['role'] !== 'admin') {
            Response::error('Registrar / Admin only.', 403);
            return;
        }

        $body = $request->body ?? [];
        $sy = (int) ($body['school_year_id'] ?? 0);
        $quarter = (int) ($body['quarter'] ?? 0);
        $locked = !empty($body['is_locked']) ? 1 : 0;
        if ($sy <= 0 || $quarter < 1 || $quarter > 4) {
            Response::error('school_year_id and quarter (1-4) required.', 422);
            return;
        }

        $pdo = Connection::get();
        $pdo->prepare(
            'INSERT INTO grading_period_locks (school_year_id, quarter, is_locked, locked_by, locked_at)
             VALUES (?, ?, ?, ?, IF(? = 1, NOW(), NULL))
             ON DUPLICATE KEY UPDATE is_locked = VALUES(is_locked), locked_by = VALUES(locked_by),
             locked_at = VALUES(locked_at)'
        )->execute([$sy, $quarter, $locked, (int) $auth['user']['id'], $locked]);

        $this->audit->log(
            (int) $auth['user']['id'],
            $locked ? 'period.lock' : 'period.unlock',
            'grading_period_locks',
            (string) $quarter,
            ['school_year_id' => $sy],
            $request->ip
        );

        $this->get($request, $auth);
    }

    public function saveTransmutation(Request $request, array $auth): void
    {
        if ($auth['role'] !== 'admin') {
            Response::error('Registrar / Admin only.', 403);
            return;
        }

        $rows = $request->body['rows'] ?? [];
        if (!is_array($rows) || $rows === []) {
            Response::error('rows array required.', 422);
            return;
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $pdo->exec('DELETE FROM transmutation_table');
            $ins = $pdo->prepare(
                'INSERT INTO transmutation_table (initial_min, initial_max, transmuted_grade) VALUES (?, ?, ?)'
            );
            foreach ($rows as $row) {
                $ins->execute([
                    (float) $row['initial_min'],
                    (float) $row['initial_max'],
                    (int) $row['transmuted_grade'],
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::error('Could not save transmutation table.', 500);
            return;
        }

        $this->audit->log((int) $auth['user']['id'], 'settings.transmutation', 'transmutation_table', null, ['count' => count($rows)], $request->ip);
        $this->get($request, $auth);
    }
}
