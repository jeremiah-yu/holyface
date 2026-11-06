<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

render('home', [
    'pageTitle' => 'Holy Face AMGS — Track progress. Learn better.',
    'pageDescription' => 'Academic Monitoring and Grading System of Holy Face of Jesus Lyceum of San Jose, Inc. Professor web workspace, student mobile app, and Registrar desktop.',
    'canonical' => abs_url('index.php'),
    'nav' => 'home',
]);
