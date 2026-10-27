<?php
declare(strict_types=1);
http_response_code(404);
require dirname(__DIR__) . '/includes/bootstrap.php';
render('404', [
    'pageTitle' => 'Page not found — Holy Face AMGS',
    'pageDescription' => 'The requested page could not be found.',
    'canonical' => abs_url('404.php'),
    'nav' => '',
]);
