<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
render('legal-cookies', [
    'pageTitle' => 'Cookie notice — Holy Face AMGS',
    'pageDescription' => 'Cookies used on the Holy Face AMGS promotional website.',
    'canonical' => abs_url('cookies.php'),
    'nav' => '',
]);
