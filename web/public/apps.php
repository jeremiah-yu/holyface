<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
render('apps', [
    'pageTitle' => 'Get the apps — Holy Face AMGS',
    'pageDescription' => 'Download the Holy Face Student mobile app and the teacher desktop app. Scan the QR code or use the download button.',
    'canonical' => abs_url('apps.php'),
    'nav' => 'apps',
]);
