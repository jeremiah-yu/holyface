<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/Config/Env.php';
HolyFace\Api\Config\Env::load(dirname(__DIR__) . '/.env');
$key = HolyFace\Api\Config\Env::get('GEMINI_API_KEY', '');
if (!is_string($key) || $key === '') {
    echo "no-key\n";
    exit(1);
}
$ca = dirname(__DIR__) . '/certs/cacert.pem';
$cases = [
    ['gemini-3.1-flash-lite', true],
    ['gemini-3.1-flash-lite', false],
    ['gemini-3.5-flash-lite', false],
    ['gemini-3.5-flash-lite', true],
];
foreach ($cases as [$model, $think]) {
    $gen = ['temperature' => 0, 'maxOutputTokens' => 16];
    if ($think) {
        $gen['thinkingConfig'] = ['thinkingBudget' => 0];
    }
    $payload = json_encode([
        'contents' => [['role' => 'user', 'parts' => [['text' => 'Reply with only the number 4.']]]],
        'generationConfig' => $gen,
    ], JSON_THROW_ON_ERROR);
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
    $ch = curl_init($url);
    $opts = [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
    ];
    if (is_readable($ca)) {
        $opts[CURLOPT_CAINFO] = $ca;
    }
    curl_setopt_array($ch, $opts);
    $t0 = microtime(true);
    $resp = curl_exec($ch);
    $ms = (int) ((microtime(true) - $t0) * 1000);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ok = '';
    if (is_string($resp)) {
        $json = json_decode($resp, true);
        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $msg = $json['error']['message'] ?? '';
        $ok = is_string($text) && $text !== '' ? 'ok ' . trim($text)
            : (is_string($msg) ? 'err=' . substr($msg, 0, 70) : '');
    }
    echo $model . ' think=' . ($think ? '0' : 'off') . " http={$code} {$ms}ms {$ok}\n";
}
