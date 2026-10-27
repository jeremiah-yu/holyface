<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
render('features', [
    'pageTitle' => 'Features — Holy Face AMGS',
    'pageDescription' => 'Real-time grades and privacy-safe study tools.',
    'canonical' => abs_url('features.php'),
    'nav' => 'features',
]);
