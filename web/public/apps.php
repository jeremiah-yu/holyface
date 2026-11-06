<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
render('apps', [
    'pageTitle' => 'Get the apps — Holy Face AMGS',
    'pageDescription' => 'Student mobile app, teacher web workspace, and Registrar desktop app for Holy Face AMGS.',
    'canonical' => abs_url('apps.php'),
    'nav' => 'apps',
]);
