<?php
declare(strict_types=1);

namespace HolyFace\Api\Services;

use HolyFace\Api\Config\Env;
use HolyFace\Api\Database\Connection;
use PDO;

/**
 * Study helpers. External models (if configured) receive only subject, topic, and notes —
 * never name, LRN, photo, grades-with-identity, or contact details.
 */
final class AiStudyService
{
    public function __construct(private ContentFilter $filter) {}

    /**
     * @param array{
     *   tool_type:string,
     *   subject?:string,
     *   topic?:string,
     *   notes?:string,
     *   exam_date?:?string,
     *   hours_per_day?:float,
     *   question_count?:int,
     *   difficulty?:string,
     *   weak_subjects?:list<array{subject:string,grade:float}>
     * } $input
     * @return array<string, mixed>
     */
    public function generate(int $userId, array $input): array
    {
        $tool = $input['tool_type'] ?? '';
        $allowed = ['study_plan', 'flashcards', 'quiz'];
        if (!in_array($tool, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid tool type.');
        }

        $pdo = Connection::get();
        $limit = (int) Env::get('AI_RATE_PER_HOUR', '20');
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) AS c FROM ai_study_sessions
             WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
        $countStmt->execute([$userId]);
        if ((int) ($countStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0) >= $limit) {
            throw new \RuntimeException('Too many AI requests this hour. Maghintay / please wait.');
        }

        $subject = $this->filter->redactIdentity(trim((string) ($input['subject'] ?? 'General')));
        $topic = $this->filter->redactIdentity(trim((string) ($input['topic'] ?? $subject)));
        $notes = $this->filter->redactIdentity(trim((string) ($input['notes'] ?? '')));

        if ($this->filter->isInappropriate($subject . ' ' . $topic . ' ' . $notes)) {
            throw new \InvalidArgumentException('That topic is not allowed. Keep requests school-appropriate.');
        }

        if (mb_strlen($notes) > 8000) {
            $notes = mb_substr($notes, 0, 8000);
        }

        $source = trim($topic . "\n" . $notes);
        if ($source === '') {
            throw new \InvalidArgumentException('Provide a topic or notes.');
        }

        $payload = $this->localGenerate($tool, $subject, $topic, $notes, $input);
        $payload['disclaimer'] = 'AI-generated. Check with your teacher.';
        $payload['academic_honesty'] = 'These tools help you study. Do not submit AI output as graded work.';

        $token = bin2hex(random_bytes(16));
        $ins = $pdo->prepare(
            'INSERT INTO ai_study_sessions (session_token, user_id, study_material_id, tool_type, request_summary, response_payload)
             VALUES (?, ?, NULL, ?, ?, ?)'
        );
        $ins->execute([
            $token,
            $userId,
            $tool,
            'tool=' . $tool . '; subject_len=' . mb_strlen($subject) . '; topic_len=' . mb_strlen($topic),
            json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        if ($tool === 'study_plan') {
            $this->persistPlanTasks($pdo, $token, $userId, $payload);
        }
        if ($tool === 'flashcards') {
            $payload['deck_id'] = $this->persistDeck($pdo, $userId, $subject, $topic, $payload['cards'] ?? []);
        }

        return ['session_token' => $token, 'tool_type' => $tool, 'data' => $payload];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function localGenerate(string $tool, string $subject, string $topic, string $notes, array $input): array
    {
        $text = $notes !== '' ? $notes : $topic;
        return match ($tool) {
            'study_plan' => $this->buildStudyPlan($subject, $topic, $text, $input),
            'flashcards' => $this->buildFlashcards($topic, $text),
            default => $this->buildQuiz($topic, $text, $input),
        };
    }

    /** @param array<string, mixed> $input */
    private function buildStudyPlan(string $subject, string $topic, string $content, array $input): array
    {
        $hours = max(0.5, min(8.0, (float) ($input['hours_per_day'] ?? 1.5)));
        $examDate = (string) ($input['exam_date'] ?? '');
        $days = 5;
        if ($examDate !== '' && strtotime($examDate)) {
            $diff = (int) ((strtotime($examDate) - strtotime('today')) / 86400);
            $days = max(2, min(14, $diff));
        }

        /** @var list<array{subject:string,grade:float}> $weak */
        $weak = $input['weak_subjects'] ?? [];
        usort($weak, static fn ($a, $b) => $a['grade'] <=> $b['grade']);
        $priority = array_slice($weak, 0, 3);

        $chunks = array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?])\s+/', $content) ?: [])));
        $schedule = [];
        for ($d = 1; $d <= $days; $d++) {
            $focus = $chunks[$d - 1] ?? $topic;
            $prio = $priority[($d - 1) % max(count($priority), 1)]['subject'] ?? $subject;
            $mins = (int) round($hours * 60);
            $schedule[] = [
                'day' => $d,
                'focus' => mb_substr($prio . ': ' . $focus, 0, 140),
                'minutes' => $mins,
                'tasks' => [
                    'Read / Basahin key ideas (' . min(25, $mins) . ' min)',
                    'Worked example / Halimbawa (' . min(20, $mins) . ' min)',
                    'Self-check quiz (10 min)',
                    'Review weakest topic: ' . $prio,
                ],
            ];
        }

        return [
            'title' => 'Study plan: ' . $subject,
            'topic' => $topic,
            'exam_date' => $examDate ?: null,
            'hours_per_day' => $hours,
            'duration_days' => $days,
            'priority_subjects' => $priority,
            'schedule' => $schedule,
        ];
    }

    /** @return array{cards: list<array{front:string,back:string}>, count:int} */
    private function buildFlashcards(string $topic, string $content): array
    {
        $chunks = preg_split('/(?<=[.!?])\s+/', $content) ?: [];
        $cards = [];
        foreach (array_slice($chunks, 0, 12) as $chunk) {
            $chunk = trim($chunk);
            if (mb_strlen($chunk) < 8) {
                continue;
            }
            $words = preg_split('/\s+/', $chunk) ?: [];
            $term = implode(' ', array_slice($words, 0, min(5, count($words))));
            $cards[] = ['front' => $term . '?', 'back' => $chunk];
        }
        if (!$cards) {
            $cards[] = ['front' => $topic . ' — define', 'back' => $content !== '' ? mb_substr($content, 0, 200) : 'Review your notes with your teacher.'];
        }
        return ['cards' => $cards, 'count' => count($cards)];
    }

    /** @param array<string, mixed> $input */
    private function buildQuiz(string $topic, string $content, array $input): array
    {
        $n = (int) ($input['question_count'] ?? 8);
        $n = max(5, min(20, $n));
        $difficulty = in_array($input['difficulty'] ?? 'medium', ['easy', 'medium', 'hard'], true)
            ? (string) $input['difficulty']
            : 'medium';

        $sentences = array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?])\s+/', $content) ?: [])));
        if (!$sentences) {
            $sentences = [$topic . ' is an important Senior High topic.'];
        }

        $questions = [];
        for ($i = 0; $i < $n; $i++) {
            $s = $sentences[$i % count($sentences)];
            if ($i % 2 === 0) {
                $questions[] = [
                    'id' => $i + 1,
                    'type' => 'true_false',
                    'difficulty' => $difficulty,
                    'question' => 'True or False: ' . mb_substr($s, 0, 140),
                    'options' => ['True', 'False'],
                    'answer_index' => 0,
                    'explanation' => 'Based on the notes you provided. Confirm with your teacher and textbook.',
                ];
            } else {
                $questions[] = [
                    'id' => $i + 1,
                    'type' => 'multiple_choice',
                    'difficulty' => $difficulty,
                    'question' => 'Which statement best matches your notes on ' . $topic . '?',
                    'options' => [
                        mb_substr($s, 0, 80),
                        'This idea is unrelated to ' . $topic . '.',
                        'Skip this subject entirely.',
                        'Memorize without understanding.',
                    ],
                    'answer_index' => 0,
                    'explanation' => 'The first option restates your notes. Always verify with class materials.',
                ];
            }
        }

        return ['questions' => $questions, 'difficulty' => $difficulty, 'count' => count($questions)];
    }

    /** @param array<string, mixed> $payload */
    private function persistPlanTasks(PDO $pdo, string $token, int $userId, array $payload): void
    {
        $ins = $pdo->prepare(
            'INSERT INTO study_plan_tasks (session_token, user_id, day_no, task_text, is_done) VALUES (?, ?, ?, ?, 0)'
        );
        foreach ($payload['schedule'] ?? [] as $day) {
            $dayNo = (int) ($day['day'] ?? 1);
            foreach ($day['tasks'] ?? [] as $task) {
                $ins->execute([$token, $userId, $dayNo, mb_substr((string) $task, 0, 255)]);
            }
        }
    }

    /** @param list<array{front:string,back:string}> $cards */
    private function persistDeck(PDO $pdo, int $userId, string $subject, string $topic, array $cards): int
    {
        $pdo->prepare(
            'INSERT INTO flashcard_decks (user_id, subject_label, topic) VALUES (?, ?, ?)'
        )->execute([$userId, mb_substr($subject, 0, 128), mb_substr($topic, 0, 191)]);
        $deckId = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare(
            'INSERT INTO flashcards (deck_id, front_text, back_text, box, next_review) VALUES (?, ?, ?, 1, CURDATE())'
        );
        foreach ($cards as $card) {
            $ins->execute([$deckId, $card['front'], $card['back']]);
        }
        return $deckId;
    }
}
