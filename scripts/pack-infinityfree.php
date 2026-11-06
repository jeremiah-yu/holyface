<?php
declare(strict_types=1);

/**
 * Build an InfinityFree htdocs folder you can upload as-is.
 *
 *   php scripts/pack-infinityfree.php
 */
$root = dirname(__DIR__);
$out = $root . '/deploy/infinityfree';
$htdocs = $out . '/htdocs';
$sqlDir = $out . '/sql';

function wipe(string $dir): void
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

function copy_tree(string $from, string $to, array $skipNames = []): void
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
        $rel = str_replace('\\', '/', substr($file->getPathname(), $fromLen));
        foreach ($skipNames as $skip) {
            if ($rel === '/' . $skip || str_starts_with($rel, '/' . $skip . '/')) {
                continue 2;
            }
        }
        $target = $to . $rel;
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

function strip_sql(string $sql): string
{
    $sql = preg_replace('/CREATE DATABASE[\s\S]*?;\s*/i', '', $sql) ?? $sql;
    $sql = preg_replace('/^USE\s+\S+;?\s*$/mi', '', $sql) ?? $sql;
    return trim($sql) . "\n";
}

wipe($out);
mkdir($htdocs, 0775, true);
mkdir($sqlDir, 0775, true);

copy_tree($root . '/web/public', $htdocs, ['router.php']);
copy_tree($root . '/web/includes', $htdocs . '/includes');
copy_tree($root . '/web/templates', $htdocs . '/templates');
copy($root . '/web/config.php', $htdocs . '/config.php');
copy_tree($root . '/shared', $htdocs . '/shared');
copy_tree($root . '/api', $htdocs . '/api', ['.env', 'vendor', 'scripts']);
if (!is_dir($htdocs . '/downloads')) {
    mkdir($htdocs . '/downloads', 0775, true);
}
if (!is_dir($htdocs . '/api/storage/registrar')) {
    mkdir($htdocs . '/api/storage/registrar', 0775, true);
}
file_put_contents(
    $htdocs . '/downloads/index.php',
    "<?php\nhttp_response_code(403);\n"
);
$deny = "Require all denied\nDeny from all\n";
file_put_contents($htdocs . '/includes/.htaccess', $deny);
file_put_contents($htdocs . '/templates/.htaccess', $deny);
file_put_contents($htdocs . '/shared/.htaccess', $deny);

$gemini = '';
$geminiModel = 'gemini-3.5-flash-lite';
$localEnv = $root . '/api/.env';
if (is_readable($localEnv)) {
    foreach (file($localEnv, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if (str_starts_with($line, 'GEMINI_API_KEY=')) {
            $gemini = trim(substr($line, strlen('GEMINI_API_KEY=')), " \t\"'");
        }
        if (str_starts_with($line, 'GEMINI_MODEL=')) {
            $geminiModel = trim(substr($line, strlen('GEMINI_MODEL=')), " \t\"'");
        }
    }
}
$jwt = bin2hex(random_bytes(32));
$env = <<<ENV
APP_ENV=production
APP_DEBUG=false
APP_URL=https://holyface.infinityfreeapp.com/api/public

DB_HOST=sqlXXX.infinityfree.com
DB_PORT=3306
DB_NAME=PASTE_FROM_PANEL
DB_USER=PASTE_FROM_PANEL
DB_PASS=PASTE_FROM_PANEL

JWT_SECRET={$jwt}
JWT_ACCESS_TTL=3600
JWT_REFRESH_TTL=604800

LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_MINUTES=15
LOGIN_RATE_WINDOW_SECONDS=300

CORS_ALLOWED_ORIGINS=*

AI_RATE_PER_HOUR=20
GEMINI_API_KEY={$gemini}
GEMINI_MODEL={$geminiModel}
ENV;
file_put_contents($htdocs . '/api/.env', $env);

function from_first_insert(string $sql): string
{
    $sql = strip_sql($sql);
    $pos = stripos($sql, 'INSERT');
    return $pos === false ? '' : trim(substr($sql, $pos)) . "\n";
}

$schema = strip_sql((string) file_get_contents($root . '/database/schema.sql'));
$seed = strip_sql((string) file_get_contents($root . '/database/seed.sql'));
file_put_contents($sqlDir . '/01-schema.sql', $schema);
file_put_contents($sqlDir . '/02-seed.sql', $seed);

$migSql = "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
$migSql .= "-- 003 demo grades/schedules (schema already has the tables)\n";
$migSql .= from_first_insert((string) file_get_contents($root . '/database/migrations/003_student_app.sql')) . "\n";
$migSql .= "-- 004 weights, transmutation, sample items\n";
$migSql .= from_first_insert((string) file_get_contents($root . '/database/migrations/004_teacher_desktop.sql')) . "\n";
$migSql .= "-- 008 adviser card release columns\n";
$migSql .= <<<'SQL'
ALTER TABLE official_report_cards
  ADD COLUMN IF NOT EXISTS released_at DATETIME NULL AFTER issued_at,
  ADD COLUMN IF NOT EXISTS released_by INT UNSIGNED NULL AFTER released_at,
  ADD COLUMN IF NOT EXISTS generated_by INT UNSIGNED NULL AFTER released_by,
  ADD COLUMN IF NOT EXISTS honor_label VARCHAR(64) NULL AFTER remarks,
  ADD COLUMN IF NOT EXISTS section_rank SMALLINT UNSIGNED NULL AFTER honor_label;

SQL;
$migSql .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";
file_put_contents($sqlDir . '/03-migrations.sql', $migSql);

$readme = <<<'TXT'
Holy Face AMGS — InfinityFree upload pack
========================================

Site: https://holyface.infinityfreeapp.com

Easiest upload: File Manager → htdocs → Upload htdocs-upload.zip → Extract.
Then upload the three .sql files via phpMyAdmin (not into htdocs).

1. Control Panel → MySQL Databases
   Create a database. Copy Host, Database name, Username, Password.
   Open phpMyAdmin → click that database name on the left → Import, in order:
     sql/01-schema.sql
     sql/02-seed.sql
     sql/03-migrations.sql

2. Edit api/.env (after extract, this is htdocs/api/.env)
   Paste DB_HOST, DB_NAME, DB_USER, DB_PASS from the MySQL page.
   JWT_SECRET is already generated.

3. File Manager → htdocs
   Delete default.php / index2.php if present.
   Extract htdocs-upload.zip HERE (files like index.php and api/ sit in htdocs).
   Do not create htdocs/htdocs.

4. Control Panel → PHP version → 8.1, 8.2, or 8.3

5. Test
   https://holyface.infinityfreeapp.com/api/public/health
   https://holyface.infinityfreeapp.com/
   https://holyface.infinityfreeapp.com/login.php

Demo logins (change after it works): password HolyFace@2026
  mreyes     professor
  registrar  registrar (desktop later)
  s001       student (APK later)
TXT;
file_put_contents($out . '/README.txt', $readme);

echo "Packed: $out\n";
echo "htdocs: $htdocs\n";
echo "sql: $sqlDir\n";
