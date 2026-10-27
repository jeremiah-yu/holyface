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

$origins = Env::get('CORS_ALLOWED_ORIGINS', '*');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origins === '*' || ($origin && in_array($origin, explode(',', $origins), true))) {
    header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
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
