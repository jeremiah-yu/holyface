<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
render('how-it-works', [
    'pageTitle' => 'How it works — Holy Face AMGS',
    'pageDescription' => 'Three steps: enroll, teachers encode, students check progress.',
    'canonical' => abs_url('how-it-works.php'),
    'nav' => 'how',
]);
