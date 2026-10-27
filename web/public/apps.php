<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
render('apps', [
    'pageTitle' => 'Get the apps — Holy Face AMGS',
    'pageDescription' => 'Student mobile app and teacher desktop app. Coming soon.',
    'canonical' => abs_url('apps.php'),
    'nav' => 'apps',
]);
