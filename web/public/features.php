<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
render('features', [
    'pageTitle' => 'Features — Holy Face AMGS',
    'pageDescription' => 'Real-time grades and privacy-safe study tools.',
    'canonical' => abs_url('features.php'),
    'nav' => 'features',
]);
