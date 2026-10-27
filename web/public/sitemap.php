<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$pages = [
    'index.php' => 'weekly',
    'features.php' => 'monthly',
    'how-it-works.php' => 'monthly',
    'strands.php' => 'monthly',
    'apps.php' => 'monthly',
    'terms.php' => 'yearly',
    'privacy.php' => 'yearly',
    'ai-policy.php' => 'yearly',
    'cookies.php' => 'yearly',
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($pages as $file => $freq) {
    echo '  <url>';
    echo '<loc>' . htmlspecialchars(abs_url($file), ENT_XML1) . '</loc>';
    echo '<changefreq>' . $freq . '</changefreq>';
    echo '</url>' . "\n";
}
echo '</urlset>';
