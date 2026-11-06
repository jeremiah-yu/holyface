<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

$app = strtolower(trim((string) ($_GET['app'] ?? '')));
$files = [
    'mobile' => [
        'path' => __DIR__ . '/downloads/holyface-student.apk',
        'name' => 'holyface-student.apk',
        'mime' => 'application/vnd.android.package-archive',
    ],
    'desktop' => [
        'path' => __DIR__ . '/downloads/holyface-registrar-setup.exe',
        'alt' => __DIR__ . '/downloads/holyface-registrar-setup.zip',
        'name' => 'holyface-registrar-setup.exe',
        'mime' => 'application/vnd.microsoft.portable-executable',
    ],
];

if (!isset($files[$app])) {
    header('Location: ' . url('apps.php'));
    exit;
}

$custom = trim((string) (cfg()['downloads'][$app] ?? ''));
if ($custom !== '') {
    header('Location: ' . $custom, true, 302);
    exit;
}

$info = $files[$app];
$chunks = amgs_download_chunks($info['path']);
if ($chunks === [] && !empty($info['alt'])) {
    $chunks = amgs_download_chunks($info['alt']);
    if ($chunks !== []) {
        $info['path'] = $info['alt'];
        $info['name'] = basename($info['alt']);
        $info['mime'] = 'application/zip';
    }
}
if ($chunks === []) {
    http_response_code(404);
    $pageTitle = 'Download unavailable — Holy Face AMGS';
    $pageDescription = 'The installer is not on this server yet.';
    $canonical = abs_url('download.php?app=' . $app);
    $nav = 'apps';
    require web_pkg_root() . '/templates/header.php';
    echo '<section class="section"><div class="container narrow">';
    echo '<h1>Download not ready</h1>';
    echo '<p>The ' . e($app === 'mobile' ? 'Android APK' : 'Windows installer') . ' is not on the server yet. Open <a href="' . e(url('apps.php')) . '">Get the apps</a>.</p>';
    echo '</div></section>';
    require web_pkg_root() . '/templates/footer.php';
    exit;
}

@set_time_limit(0);
$size = 0;
foreach ($chunks as $chunk) {
    $size += (int) filesize($chunk);
}

header('Content-Type: ' . $info['mime']);
header('Content-Disposition: attachment; filename="' . $info['name'] . '"');
header('Content-Length: ' . (string) $size);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
foreach ($chunks as $chunk) {
    readfile($chunk);
}
exit;

/** @return list<string> */
function amgs_download_chunks(string $path): array
{
    if (is_file($path)) {
        return [$path];
    }
    $chunks = [];
    for ($i = 1; $i <= 20; $i++) {
        $part = $path . '.part' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        if (!is_file($part)) {
            break;
        }
        $chunks[] = $part;
    }
    return $chunks;
}
