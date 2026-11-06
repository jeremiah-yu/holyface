<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
render('how-it-works', [
    'pageTitle' => 'How it works — Holy Face AMGS',
    'pageDescription' => 'Three steps: enroll, professors encode, students check progress on mobile.',
    'canonical' => abs_url('how-it-works.php'),
    'nav' => 'how',
]);
