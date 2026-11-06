<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
render('legal-document', [
    'docCode' => 'terms',
    'pageTitle' => 'Terms of Use — Holy Face AMGS',
    'pageDescription' => 'Terms of Use for the Holy Face Academic Suite (AMGS) website, mobile app, teacher workspace, and Registrar desktop app.',
    'canonical' => abs_url('terms.php'),
    'nav' => '',
]);
