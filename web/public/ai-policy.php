<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
render('legal-document', [
    'docCode' => 'ai_policy',
    'pageTitle' => 'AI Use Policy — Holy Face AMGS',
    'pageDescription' => 'How Holy Face AMGS study tools work, what they must not be used for, and how to report problems.',
    'canonical' => abs_url('ai-policy.php'),
    'nav' => '',
]);
