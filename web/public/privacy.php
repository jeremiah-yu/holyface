<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
render('legal-document', [
    'docCode' => 'privacy',
    'pageTitle' => 'Privacy Policy (RA 10173) — Holy Face AMGS',
    'pageDescription' => 'Privacy Policy under the Philippine Data Privacy Act of 2012 (RA 10173) for the Holy Face Academic Suite.',
    'canonical' => abs_url('privacy.php'),
    'nav' => '',
]);
