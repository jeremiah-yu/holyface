<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

$index = __DIR__ . '/teacher-app/index.html';
$apiBase = rtrim((string) cfg()['api_base_url'], '/');
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$isLocal = $host === '' || str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
if ($isLocal) {
    $apiCandidates = array_values(array_unique(array_filter([
        $apiBase,
        'http://127.0.0.1:8089',
        'http://localhost:8089',
    ])));
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 1, 'ignore_errors' => true]]);
    foreach ($apiCandidates as $base) {
        $health = @file_get_contents(rtrim((string) $base, '/') . '/health', false, $ctx);
        if (is_string($health) && str_contains($health, '"success"')) {
            $apiBase = rtrim((string) $base, '/');
            break;
        }
    }
}

$appHref = url('teacher-app/index.html');
if (is_file($index)) {
    header('Content-Type: text/html; charset=utf-8');
    $sessionUser = $_SESSION['amgs_user'] ?? null;
    $boot = ['apiBase' => $apiBase];
    if (!empty($_SESSION['amgs_token']) && is_array($sessionUser)) {
        $boot['token'] = (string) $_SESSION['amgs_token'];
        $boot['user'] = [
            'id' => (int) ($sessionUser['id'] ?? 0),
            'full_name' => (string) ($sessionUser['full_name'] ?? ''),
            'role' => (string) ($sessionUser['role'] ?? ''),
            'username' => (string) ($sessionUser['username'] ?? ''),
            'email' => (string) ($sessionUser['email'] ?? ''),
        ];
    }
    $boot = json_encode($boot, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Opening professor workspace</title>';
    echo '<script>sessionStorage.setItem("amgs_secure_dev", JSON.stringify(' . $boot . '));';
    echo 'location.replace(' . json_encode($appHref) . ');</script></head>';
    echo '<body><p>Opening the professor workspace…</p></body></html>';
    exit;
}

header('Location: http://127.0.0.1:5173/');
exit;
