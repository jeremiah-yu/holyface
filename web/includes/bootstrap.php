<?php
declare(strict_types=1);

/**
 * Shared bootstrap for the promotional website.
 * All pages include this file — never query the database from the public site.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

$config = require dirname(__DIR__) . '/config.php';

function cfg(): array
{
    global $config;
    return $config;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function web_base(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = trim((string) (cfg()['web_base'] ?? ''));
    if ($configured !== '') {
        $base = rtrim($configured, '/');
        return $base;
    }
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $script = rtrim($script, '/');
    if ($script === '/' || $script === '\\' || $script === '.' || $script === '') {
        $base = '';
        return $base;
    }
    $base = $script;
    return $base;
}

function static_page_name(string $path): string
{
    if (getenv('AMGS_STATIC_EXPORT') !== '1') {
        return $path;
    }
    $file = ltrim($path, '/');
    $map = [
        'index.php' => 'index.html',
        'features.php' => 'features.html',
        'how-it-works.php' => 'how-it-works.html',
        'strands.php' => 'strands.html',
        'apps.php' => 'apps.html',
        'terms.php' => 'terms.html',
        'privacy.php' => 'privacy.html',
        'ai-policy.php' => 'ai-policy.html',
        'cookies.php' => 'cookies.html',
        'sitemap.php' => 'sitemap.xml',
        'robots.php' => 'robots.txt',
        'faq.php' => 'index.html',
        'contact.php' => 'index.html',
        'grading.php' => 'index.html',
        'announcements.php' => 'index.html',
        '404.php' => '404.html',
    ];
    return $map[$file] ?? $file;
}

function url(string $path = ''): string
{
    $path = static_page_name(ltrim($path, '/'));
    $b = web_base();
    if ($path === '') {
        return $b === '' ? '/' : $b . '/';
    }
    return ($b === '' ? '' : $b) . '/' . $path;
}

function abs_url(string $path = ''): string
{
    $site = rtrim((string) (cfg()['site_url'] ?? ''), '/');
    if ($site === '') {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $site = ($https ? 'https://' : 'http://') . $host . web_base();
    }
    $path = static_page_name(ltrim($path, '/'));
    return $path === '' ? $site . '/' : $site . '/' . $path;
}

/** Fetch public JSON from the AMGS API. Never used for student records on this site. */
function amgs_fetch(string $path): array
{
    $bases = array_values(array_unique(array_filter([
        rtrim((string) (cfg()['api_base_url'] ?? ''), '/'),
        'http://localhost:8089',
        'http://localhost/holyface/api/public',
    ])));
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 4,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);
    foreach ($bases as $apiBase) {
        $json = @file_get_contents($apiBase . $path, false, $ctx);
        if ($json === false) {
            continue;
        }
        $data = json_decode($json, true);
        if (is_array($data) && !empty($data['success'])) {
            return $data;
        }
    }
    return [];
}

/**
 * POST JSON to the AMGS API (contact form). Tries the same base URL fallbacks as GET.
 *
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function amgs_post(string $path, array $payload): array
{
    $bases = array_values(array_unique(array_filter([
        rtrim((string) (cfg()['api_base_url'] ?? ''), '/'),
        'http://localhost:8089',
        'http://localhost/holyface/api/public',
    ])));
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    foreach ($bases as $apiBase) {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $body,
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);
        $resp = @file_get_contents($apiBase . $path, false, $ctx);
        if ($resp === false) {
            continue;
        }
        $data = json_decode($resp, true);
        if (is_array($data)) {
            return $data;
        }
    }
    return [];
}

/**
 * Public announcements only. Drops any item that looks like it contains an LRN
 * so this promotional site never shows student identifiers.
 *
 * @return list<array<string, mixed>>
 */
function public_announcements(): array
{
    $items = amgs_fetch('/announcements?audience=public')['announcements'] ?? [];
    $safe = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $text = (string) ($item['title'] ?? '') . ' ' . (string) ($item['body'] ?? '');
        if (preg_match('/\b\d{12}\b/', $text)) {
            continue;
        }
        $safe[] = $item;
    }
    return $safe;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
    return is_string($token)
        && $token !== ''
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function legal_catalog(): array
{
    static $catalog = null;
    if ($catalog === null) {
        /** @var array<string, mixed> $catalog */
        $catalog = require dirname(__DIR__, 2) . '/shared/legal/policies.php';
    }
    return $catalog;
}

/**
 * Authenticated JSON call to the AMGS API.
 *
 * @param array<string, mixed>|null $payload
 * @return array<string, mixed>
 */
function amgs_auth(string $method, string $path, string $token, ?array $payload = null): array
{
    $bases = array_values(array_unique(array_filter([
        rtrim((string) (cfg()['api_base_url'] ?? ''), '/'),
        'http://localhost:8089',
        'http://localhost/holyface/api/public',
    ])));
    $headers = "Accept: application/json\r\nAuthorization: Bearer {$token}\r\n";
    $http = [
        'method' => $method,
        'header' => $headers,
        'timeout' => 10,
        'ignore_errors' => true,
    ];
    if ($payload !== null) {
        $http['header'] .= "Content-Type: application/json\r\n";
        $http['content'] = json_encode($payload, JSON_THROW_ON_ERROR);
    }
    $ctx = stream_context_create(['http' => $http]);
    foreach ($bases as $apiBase) {
        $resp = @file_get_contents($apiBase . $path, false, $ctx);
        if ($resp === false) {
            continue;
        }
        $data = json_decode($resp, true);
        if (is_array($data)) {
            return $data;
        }
    }
    return [];
}

/** @param list<string>|null $roles */
function require_portal_user(?array $roles = null): array
{
    if (empty($_SESSION['amgs_user']) || empty($_SESSION['amgs_token'])) {
        header('Location: ' . url('login.php'));
        exit;
    }
    $user = $_SESSION['amgs_user'];
    if ($roles !== null && !in_array((string) ($user['role'] ?? ''), $roles, true)) {
        header('Location: ' . url('login.php'));
        exit;
    }
    if (empty($_SESSION['amgs_consent'])) {
        header('Location: ' . url('accept.php'));
        exit;
    }
    return $user;
}

function render(string $view, array $vars = []): void
{
    $config = cfg();
    $school = $config['school'];
    extract($vars, EXTR_SKIP);
    require dirname(__DIR__) . '/templates/header.php';
    require dirname(__DIR__) . '/templates/' . $view . '.php';
    require dirname(__DIR__) . '/templates/footer.php';
}
