<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

render('home', [
    'pageTitle' => 'Holy Face AMGS — Track progress. Learn better.',
    'pageDescription' => 'Academic Monitoring and Grading System of Holy Face of Jesus Lyceum of San Jose, Inc. For students and teachers.',
    'canonical' => abs_url('index.php'),
    'nav' => 'home',
]);
