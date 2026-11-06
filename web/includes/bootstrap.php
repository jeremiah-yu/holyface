<?php
declare(strict_types=1);

/**
 * Shared bootstrap for the promotional website.
 * All pages include this file — never query the database from the public site.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => $https,
    ]);
}

function web_pkg_root(): string
{
    return dirname(__DIR__);
}

$config = require web_pkg_root() . '/config.php';
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$isLocal = $host === '' || str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
if (!$isLocal && (str_contains((string) $config['api_base_url'], 'localhost') || $config['api_base_url'] === '')) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $config['api_base_url'] = ($https ? 'https://' : 'http://') . $host . '/api/public';
}

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
        'login.php' => 'teacher-app/index.html',
        'teacher-portal.php' => 'teacher-app/index.html',
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

function professor_workspace_url(): string
{
    if (getenv('AMGS_STATIC_EXPORT') === '1') {
        return url('teacher-app/index.html');
    }
    return url('teacher-portal.php');
}

function download_url(string $kind): string
{
    $custom = trim((string) (cfg()['downloads'][$kind] ?? ''));
    if ($custom !== '') {
        return $custom;
    }
    if ($kind === 'mobile' || $kind === 'desktop') {
        return abs_url('download.php?app=' . $kind);
    }
    return abs_url('apps.php');
}

function qr_image_url(string $data, int $size = 220): string
{
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
        . '&ecc=M&margin=10&data=' . rawurlencode($data);
}

/** @return list<string> */
function amgs_api_bases(): array
{
    $bases = [rtrim((string) (cfg()['api_base_url'] ?? ''), '/')];
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '' || str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) {
        $bases[] = 'http://localhost:8089';
        $bases[] = 'http://localhost/holyface/api/public';
    }
    return array_values(array_unique(array_filter($bases)));
}

function amgs_api_root(): string
{
    $candidates = [
        dirname(__DIR__, 2) . '/api',
        web_pkg_root() . '/api',
    ];
    foreach ($candidates as $dir) {
        if (is_readable($dir . '/src/Router.php')) {
            return $dir;
        }
    }
    return $candidates[0];
}

function amgs_boot_api(): bool
{
    static $booted = false;
    if ($booted) {
        return true;
    }
    $api = amgs_api_root();
    if (!is_readable($api . '/src/Router.php')) {
        return false;
    }
    spl_autoload_register(static function (string $class) use ($api): void {
        $prefix = 'HolyFace\\Api\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $path = $api . '/src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_readable($path)) {
            require $path;
        }
    });
    \HolyFace\Api\Config\Env::load($api . '/.env');
    $booted = true;
    return true;
}

/**
 * Call the REST API in-process (InfinityFree blocks outbound HTTP).
 *
 * @param array<string, mixed>|null $payload
 * @return array<string, mixed>
 */
function amgs_local_dispatch(string $method, string $path, ?array $payload = null, ?string $token = null): array
{
    if (!amgs_boot_api()) {
        return [];
    }
    $query = [];
    $parsed = parse_url($path);
    $route = (string) ($parsed['path'] ?? $path);
    if (!empty($parsed['query'])) {
        parse_str((string) $parsed['query'], $query);
    }
    $headers = ['Accept' => 'application/json'];
    if ($token !== null && $token !== '') {
        $headers['Authorization'] = 'Bearer ' . $token;
    }
    if ($payload !== null) {
        $headers['Content-Type'] = 'application/json';
    }
    $request = new \HolyFace\Api\Http\Request(
        strtoupper($method),
        $route,
        $query,
        $payload,
        $headers,
        (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
        'amgs-web'
    );
    ob_start();
    try {
        (new \HolyFace\Api\Router())->dispatch($request);
    } catch (\Throwable) {
        ob_end_clean();
        return [];
    }
    $raw = ob_get_clean();
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : [];
}

/** Fetch public JSON from the AMGS API. Never used for student records on this site. */
function amgs_fetch(string $path): array
{
    $local = amgs_local_dispatch('GET', $path);
    if ($local !== []) {
        return $local;
    }
    $bases = amgs_api_bases();
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
    $local = amgs_local_dispatch('POST', $path, $payload);
    if ($local !== []) {
        return $local;
    }
    $bases = amgs_api_bases();
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
        $candidates = [
            dirname(__DIR__, 2) . '/shared/legal/policies.php',
            web_pkg_root() . '/shared/legal/policies.php',
        ];
        $path = $candidates[0];
        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                $path = $candidate;
                break;
            }
        }
        /** @var array<string, mixed> $catalog */
        $catalog = require $path;
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
    $local = amgs_local_dispatch($method, $path, $payload, $token);
    if ($local !== []) {
        return $local;
    }
    $bases = amgs_api_bases();
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
    require web_pkg_root() . '/templates/header.php';
    require web_pkg_root() . '/templates/' . $view . '.php';
    require web_pkg_root() . '/templates/footer.php';
}
