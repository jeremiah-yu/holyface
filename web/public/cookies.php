<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
render('legal-cookies', [
    'pageTitle' => 'Cookie notice — Holy Face AMGS',
    'pageDescription' => 'Cookies used on the Holy Face AMGS promotional website.',
    'canonical' => abs_url('cookies.php'),
    'nav' => '',
]);
