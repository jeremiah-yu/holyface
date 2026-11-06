<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /login.php\n";
echo "Disallow: /student-portal.php\n";
echo "Disallow: /teacher-portal.php\n";
echo "Disallow: /admin-portal.php\n";
echo "Disallow: /teacher-app/\n";
echo 'Sitemap: ' . abs_url('sitemap.php') . "\n";
