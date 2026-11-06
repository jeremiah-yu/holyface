<?php
declare(strict_types=1);

/**
 * Loads bootstrap whether this folder is web/public (XAMPP) or htdocs (InfinityFree).
 */
$pkg = dirname(__DIR__);
if (!is_readable($pkg . '/includes/bootstrap.php')) {
    $pkg = __DIR__;
}
require $pkg . '/includes/bootstrap.php';
