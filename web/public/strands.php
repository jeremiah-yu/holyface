<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
render('strands', [
    'pageTitle' => 'SHS Strands — ABM, HUMSS, ICT, HE, STEM | Holy Face',
    'pageDescription' => 'Senior High School strands at Holy Face of Jesus Lyceum of San Jose, Inc.',
    'canonical' => abs_url('strands.php'),
    'nav' => 'strands',
]);
