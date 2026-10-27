<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
header('Location: ' . url('index.php'), true, 301);
exit;
