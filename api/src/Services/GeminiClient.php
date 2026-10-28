<?php
declare(strict_types=1);

namespace HolyFace\Api\Services;

use HolyFace\Api\Config\Env;

/**
 * Calls Gemini with subject/topic/notes only. Never send student identity here.
 * Uses Flash-Lite only — thinking models (3.8 Flash) take 20s+ and block this PHP server.
 */
final class GeminiClient
{
    /** Fast non-thinking models, newest first. gemini-2.0-flash was shut down 2026-06-01. */
    private const MODELS = [
        'gemini-3.5-flash-lite',
        'gemini-3.1-flash-lite',
        'gemini-flash-latest',
        'gemini-3.5-flash',
    ];

    public function enabled(): bool
    {
        $key = Env::get('GEMINI_API_KEY', '');
        return is_string($key) && $key !== '';
    }

    public function generateText(string $system, string $user, int $maxTokens = 400, bool $fast = false): ?string
    {
        $raw = $this->request($system, $user, $maxTokens, false, $fast, null);
        return $raw !== null && $raw !== '' ? $raw : null;
    }

    public function extractImageText(string $mime, string $base64): ?string
    {
        $system = 'You transcribe class notes from a photo. Return plain study notes only. '
            . 'Ignore faces, names, student numbers, and contact details.';
        $raw = $this->request(
            $system,
            'Extract the lesson notes as plain text.',
            500,
            false,
            true,
            ['mime' => $mime, 'data' => $base64]
        );
        return $raw !== null && $raw !== '' ? trim($raw) : null;
    }

    /** @return array<string, mixed>|null */
    public function generateJson(string $system, string $user, int $maxTokens = 700): ?array
    {
        $raw = $this->request($system, $user . "\n\nReturn JSON only. No markdown.", $maxTokens, true, false, null);
        if ($raw === null) {
            return null;
        }
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    /** @param array{mime:string,data:string}|null $image */
    private function request(string $system, string $user, int $maxTokens, bool $json, bool $fast, ?array $image): ?string
    {
        $key = Env::get('GEMINI_API_KEY', '');
        if (!is_string($key) || $key === '') {
            return null;
        }
        $preferred = Env::get('GEMINI_MODEL', '');
        $models = self::MODELS;
        if (is_string($preferred) && $preferred !== '' && !in_array($preferred, $models, true)) {
            array_unshift($models, $preferred);
        } elseif (is_string($preferred) && $preferred !== '') {
            $models = array_values(array_unique(array_merge([$preferred], $models)));
        }

        $gen = [
            'temperature' => 0.2,
            'maxOutputTokens' => max(96, min($fast ? 280 : 800, $maxTokens)),
            'thinkingConfig' => ['thinkingBudget' => 0],
        ];
        if ($json) {
            $gen['responseMimeType'] = 'application/json';
        }

        $parts = [['text' => $user]];
        if ($image !== null) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $image['mime'],
                    'data' => $image['data'],
                ],
            ];
        }

        $payload = json_encode([
            'system_instruction' => [
                'parts' => [['text' => $system]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => $parts],
            ],
            'generationConfig' => $gen,
        ], JSON_THROW_ON_ERROR);

        $tries = $fast ? 1 : 2;
        $timeout = $fast ? 6 : 12;
        foreach (array_slice($models, 0, $tries) as $model) {
            $result = $this->post($model, $key, $payload, $timeout);
            if ($result['text'] !== null) {
                return $result['text'];
            }
            if (!in_array($result['code'], [0, 400, 404], true)) {
                return null;
            }
        }
        return null;
    }

    /** @return array{text:?string, code:int} */
    private function post(string $model, string $key, string $payload, int $timeout = 12): array
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($model) . ':generateContent';
        $headers = [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $key,
        ];
        [$body, $code] = $this->httpPost($url, $headers, $payload, $timeout);
        if ($body === null || $code < 200 || $code >= 300) {
            return ['text' => null, 'code' => $code];
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return ['text' => null, 'code' => $code];
        }
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        return ['text' => is_string($text) && $text !== '' ? $text : null, 'code' => $code];
    }

    /**
     * @param list<string> $headers
     * @return array{0:?string, 1:int}
     */
    private function httpPost(string $url, array $headers, string $payload, int $timeout = 12): array
    {
        $ca = dirname(__DIR__, 2) . '/certs/cacert.pem';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return [null, 0];
            }
            $opts = [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => max(4, $timeout),
            ];
            if (is_readable($ca)) {
                $opts[CURLOPT_CAINFO] = $ca;
            }
            curl_setopt_array($ch, $opts);
            $resp = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return [is_string($resp) ? $resp : null, $code];
        }

        $ssl = [];
        if (is_readable($ca)) {
            $ssl['cafile'] = $ca;
            $ssl['verify_peer'] = true;
            $ssl['verify_peer_name'] = true;
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $payload,
                'timeout' => max(4, $timeout),
                'ignore_errors' => true,
            ],
            'ssl' => $ssl,
        ]);
        $resp = @file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        return [is_string($resp) ? $resp : null, $code];
    }
}
