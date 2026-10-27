<?php
declare(strict_types=1);

/**
 * Front controller for `php -S localhost:8090 router.php` (document root = this folder).
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$file = __DIR__ . $uri;

if ($uri !== '/' && is_file($file)) {
    return false;
}

$map = [
    '/' => 'index.php',
    '/sitemap.xml' => 'sitemap.php',
    '/robots.txt' => 'robots.php',
];

if (isset($map[$uri])) {
    require __DIR__ . '/' . $map[$uri];
    return true;
}

$php = __DIR__ . $uri;
if (str_ends_with($uri, '.php') && is_file($php)) {
    require $php;
    return true;
}

http_response_code(404);
require __DIR__ . '/404.php';
return true;
