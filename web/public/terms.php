<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
render('legal-document', [
    'docCode' => 'terms',
    'pageTitle' => 'Terms of Use — Holy Face AMGS',
    'pageDescription' => 'Terms of Use for the Holy Face Academic Suite (AMGS) website, mobile app, and teacher desktop app.',
    'canonical' => abs_url('terms.php'),
    'nav' => '',
]);
