<?php
declare(strict_types=1);

use HolyFace\Api\Config\Env;
use HolyFace\Api\Http\Request;
use HolyFace\Api\Http\Response;
use HolyFace\Api\Router;

// PSR-4 autoload without Composer (XAMPP-friendly)
spl_autoload_register(static function (string $class): void {
    $prefix = 'HolyFace\\Api\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($path)) {
        require $path;
    }
});

// Optional: firebase/php-jwt if Composer vendor is present
if (is_readable(dirname(__DIR__) . '/vendor/autoload.php')) {
    require dirname(__DIR__) . '/vendor/autoload.php';
}

Env::load(dirname(__DIR__) . '/.env');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

$origins = (string) Env::get('CORS_ALLOWED_ORIGINS', '*');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && amgs_cors_allowed($origin, $origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
}

function amgs_cors_allowed(string $origin, string $configured): bool
{
    if ($configured === '*' || $configured === '') {
        return true;
    }
    $allowed = array_values(array_filter(array_map('trim', explode(',', $configured))));
    $variants = [$origin];
    if (str_contains($origin, '://localhost')) {
        $variants[] = str_replace('://localhost', '://127.0.0.1', $origin);
    }
    if (str_contains($origin, '://127.0.0.1')) {
        $variants[] = str_replace('://127.0.0.1', '://localhost', $origin);
    }
    foreach ($variants as $candidate) {
        if (in_array($candidate, $allowed, true)) {
            return true;
        }
    }
    $host = (string) parse_url($origin, PHP_URL_HOST);
    if (in_array($host, ['localhost', '127.0.0.1'], true)) {
        return true;
    }
    if ($origin === 'null' && Env::get('APP_ENV', 'local') === 'local') {
        return true;
    }
    $env = Env::get('APP_ENV', 'local');
    if ($env === 'local' && preg_match('/^(192\.168\.|10\.|172\.(1[6-9]|2\d|3[0-1])\.)/', $host)) {
        return true;
    }
    return false;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $request = Request::fromGlobals();
    (new Router())->dispatch($request);
} catch (Throwable $e) {
    $debug = Env::get('APP_DEBUG', 'false') === 'true';
    Response::error($debug ? $e->getMessage() : 'Server error.', 500);
}
