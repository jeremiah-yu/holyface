<?php
declare(strict_types=1);

namespace HolyFace\Api\Services;

/**
 * Redacts identity data and blocks content that is not age-appropriate for Grade 11–12.
 */
final class ContentFilter
{
    /** Strip LRN-like numbers, emails, and PH mobile numbers from student-supplied notes. */
    public function redactIdentity(string $text): string
    {
        $text = preg_replace('/\b\d{12}\b/', '[redacted]', $text) ?? $text;
        $text = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted]', $text) ?? $text;
        $text = preg_replace('/\b09\d{9}\b/', '[redacted]', $text) ?? $text;
        return $text;
    }

    public function isInappropriate(string $text): bool
    {
        $lower = mb_strtolower($text);
        $blocked = [
            'porn', 'nsfw', 'xxx', 'explicit sex', 'suicide method', 'how to kill',
            'make a bomb', 'drug recipe', 'self harm instructions',
        ];
        foreach ($blocked as $term) {
            if (str_contains($lower, $term)) {
                return true;
            }
        }
        return false;
    }
}
