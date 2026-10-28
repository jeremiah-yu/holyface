<?php
declare(strict_types=1);

/**
 * Render the public promotional site to static HTML for GitHub Pages.
 *
 * Usage:
 *   php web/scripts/export-static.php
 *   php web/scripts/export-static.php --render index.php
 */
$root = dirname(__DIR__);
$public = $root . '/public';
$dist = $root . '/dist';

if (($argv[1] ?? '') === '--render') {
    $src = $argv[2] ?? '';
    if ($src === '' || str_contains($src, '..') || !preg_match('/^[a-z0-9.-]+\.php$/', $src)) {
        fwrite(STDERR, "Invalid page\n");
        exit(1);
    }
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['HTTP_HOST'] = getenv('AMGS_PAGES_HOST') ?: 'jeremiah-yu.github.io';
    $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
    $_SERVER['SCRIPT_NAME'] = '/' . $src;
    $_SERVER['REQUEST_URI'] = '/' . $src;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    require $public . '/' . $src;
    exit(0);
}

$webBase = getenv('AMGS_WEB_BASE');
if ($webBase === false || $webBase === '') {
    $webBase = '/holyface';
    putenv('AMGS_WEB_BASE=' . $webBase);
}
$siteUrl = getenv('AMGS_SITE_URL');
if ($siteUrl === false || $siteUrl === '') {
    $siteUrl = 'https://jeremiah-yu.github.io/holyface';
    putenv('AMGS_SITE_URL=' . $siteUrl);
}
$host = getenv('AMGS_PAGES_HOST') ?: 'jeremiah-yu.github.io';
putenv('AMGS_STATIC_EXPORT=1');
putenv('AMGS_PAGES_HOST=' . $host);

function wipe_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
}

function copy_tree(string $from, string $to): void
{
    if (!is_dir($from)) {
        return;
    }
    if (!is_dir($to)) {
        mkdir($to, 0775, true);
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $fromLen = strlen($from);
    foreach ($it as $file) {
        $target = $to . substr($file->getPathname(), $fromLen);
        if ($file->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0775, true);
            }
        } else {
            $parent = dirname($target);
            if (!is_dir($parent)) {
                mkdir($parent, 0775, true);
            }
            copy($file->getPathname(), $target);
        }
    }
}

wipe_dir($dist);
mkdir($dist . '/assets', 0775, true);

$pages = [
    'index.php' => 'index.html',
    'features.php' => 'features.html',
    'how-it-works.php' => 'how-it-works.html',
    'strands.php' => 'strands.html',
    'apps.php' => 'apps.html',
    'terms.php' => 'terms.html',
    'privacy.php' => 'privacy.html',
    'ai-policy.php' => 'ai-policy.html',
    'cookies.php' => 'cookies.html',
    '404.php' => '404.html',
    'sitemap.php' => 'sitemap.xml',
    'robots.php' => 'robots.txt',
];

$self = __FILE__;
$php = PHP_BINARY;
foreach ($pages as $src => $dest) {
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($self) . ' --render ' . escapeshellarg($src);
    $output = [];
    $code = 0;
    exec($cmd, $output, $code);
    $stdout = implode("\n", $output);
    if ($code !== 0) {
        fwrite(STDERR, "Export failed for $src (exit $code)\n$stdout\n");
        exit(1);
    }
    file_put_contents($dist . '/' . $dest, $stdout);
    echo "Wrote $dest (" . strlen($stdout) . " bytes)\n";
}

copy_tree($public . '/assets', $dist . '/assets');
copy_tree($public . '/downloads', $dist . '/downloads');
file_put_contents($dist . '/.nojekyll', '');
echo "Static site written to $dist\n";
