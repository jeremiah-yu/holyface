<?php
declare(strict_types=1);

namespace HolyFace\Api\Controllers;

use HolyFace\Api\Database\Connection;
use HolyFace\Api\Http\Request;
use HolyFace\Api\Http\Response;
use HolyFace\Api\Services\AiStudyService;
use PDO;

final class AiController
{
    public function __construct(
        private AiStudyService $ai,
        private StudentController $students,
    ) {}

    public function generate(Request $request, array $auth): void
    {
        if ($auth['role'] !== 'student') {
            Response::error('Students only.', 403);
            return;
        }
        $body = $request->body ?? [];
        $userId = (int) $auth['user']['id'];
        $input = [
            'tool_type' => (string) ($body['tool_type'] ?? ''),
            'subject' => (string) ($body['subject'] ?? ''),
            'topic' => (string) ($body['topic'] ?? ''),
            'notes' => (string) ($body['notes'] ?? ''),
            'exam_date' => $body['exam_date'] ?? null,
            'hours_per_day' => (float) ($body['hours_per_day'] ?? 1.5),
            'question_count' => (int) ($body['question_count'] ?? 8),
            'difficulty' => (string) ($body['difficulty'] ?? 'medium'),
            'weak_subjects' => $this->students->weakSubjects($userId),
        ];

        try {
            $result = $this->ai->generate($userId, $input);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
            return;
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 429);
            return;
        }

        Response::json(['success' => true, 'result' => $result]);
    }

    public function extractNotes(Request $request, array $auth): void
    {
        if ($auth['role'] !== 'student') {
            Response::error('Students only.', 403);
            return;
        }
        $body = $request->body ?? [];
        $filename = basename((string) ($body['filename'] ?? 'notes.txt'));
        $base64 = (string) ($body['content_base64'] ?? '');
        if ($base64 === '') {
            Response::error('Choose a notes file first.', 422);
            return;
        }
        try {
            $text = $this->ai->extractUploadedText($filename, $base64);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
            return;
        }
        Response::json(['success' => true, 'text' => $text, 'filename' => $filename]);
    }

    public function chat(Request $request, array $auth): void
    {
        if ($auth['role'] !== 'student') {
            Response::error('Students only.', 403);
            return;
        }
        $body = $request->body ?? [];
        $input = [
            'tool_type' => 'chat',
            'subject' => (string) ($body['subject'] ?? 'Study'),
            'topic' => (string) ($body['topic'] ?? ''),
            'notes' => (string) ($body['notes'] ?? ''),
            'message' => (string) ($body['message'] ?? ''),
        ];
        try {
            $result = $this->ai->generate((int) $auth['user']['id'], $input);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
            return;
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 429);
            return;
        }
        Response::json(['success' => true, 'result' => $result]);
    }

    public function planTasks(Request $request, array $auth): void
    {
        if ($auth['role'] !== 'student') {
            Response::error('Students only.', 403);
            return;
        }
        $token = (string) ($request->query['session_token'] ?? '');
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT id, day_no, task_text, is_done FROM study_plan_tasks
             WHERE user_id = ? AND session_token = ? ORDER BY day_no, id'
        );
        $stmt->execute([(int) $auth['user']['id'], $token]);
        Response::json(['success' => true, 'tasks' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function toggleTask(Request $request, array $auth): void
    {
        if ($auth['role'] !== 'student') {
            Response::error('Students only.', 403);
            return;
        }
        $id = (int) ($request->body['task_id'] ?? 0);
        $pdo = Connection::get();
        $pdo->prepare(
            'UPDATE study_plan_tasks SET is_done = 1 - is_done WHERE id = ? AND user_id = ?'
        )->execute([$id, (int) $auth['user']['id']]);
        Response::json(['success' => true]);
    }

    public function reviewCard(Request $request, array $auth): void
    {
        if ($auth['role'] !== 'student') {
            Response::error('Students only.', 403);
            return;
        }
        $cardId = (int) ($request->body['card_id'] ?? 0);
        $gotIt = (string) ($request->body['result'] ?? '') === 'got_it';
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT f.id, f.box FROM flashcards f
             INNER JOIN flashcard_decks d ON d.id = f.deck_id
             WHERE f.id = ? AND d.user_id = ?'
        );
        $stmt->execute([$cardId, (int) $auth['user']['id']]);
        $card = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$card) {
            Response::error('Card not found.', 404);
            return;
        }
        $box = (int) $card['box'];
        $box = $gotIt ? min(5, $box + 1) : 1;
        $intervals = [1 => 1, 2 => 3, 3 => 7, 4 => 14, 5 => 30];
        $days = $intervals[$box];
        $pdo->prepare(
            'UPDATE flashcards SET box = ?, next_review = DATE_ADD(CURDATE(), INTERVAL ? DAY) WHERE id = ?'
        )->execute([$box, $days, $cardId]);
        Response::json(['success' => true, 'box' => $box, 'next_review_days' => $days]);
    }

    public function decks(Request $request, array $auth): void
    {
        if ($auth['role'] !== 'student') {
            Response::error('Students only.', 403);
            return;
        }
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT d.id, d.subject_label, d.topic, d.created_at, COUNT(f.id) AS card_count
             FROM flashcard_decks d
             LEFT JOIN flashcards f ON f.deck_id = d.id
             WHERE d.user_id = ?
             GROUP BY d.id
             ORDER BY d.created_at DESC'
        );
        $stmt->execute([(int) $auth['user']['id']]);
        Response::json(['success' => true, 'decks' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function deckCards(Request $request, array $auth): void
    {
        if ($auth['role'] !== 'student') {
            Response::error('Students only.', 403);
            return;
        }
        $deckId = (int) ($request->query['deck_id'] ?? 0);
        $pdo = Connection::get();
        $own = $pdo->prepare('SELECT id FROM flashcard_decks WHERE id = ? AND user_id = ?');
        $own->execute([$deckId, (int) $auth['user']['id']]);
        if (!$own->fetch()) {
            Response::error('Deck not found.', 404);
            return;
        }
        $stmt = $pdo->prepare('SELECT id, front_text, back_text, box, next_review FROM flashcards WHERE deck_id = ?');
        $stmt->execute([$deckId]);
        Response::json(['success' => true, 'cards' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function submitQuiz(Request $request, array $auth): void
    {
        if ($auth['role'] !== 'student') {
            Response::error('Students only.', 403);
            return;
        }
        $body = $request->body ?? [];
        $score = (int) ($body['score'] ?? 0);
        $total = (int) ($body['total'] ?? 0);
        $token = (string) ($body['session_token'] ?? '');
        $missed = $body['missed'] ?? [];
        $pdo = Connection::get();
        $pdo->prepare(
            'INSERT INTO quiz_attempts (user_id, session_token, subject_label, difficulty, score, total, missed_json)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            (int) $auth['user']['id'],
            $token,
            mb_substr((string) ($body['subject'] ?? ''), 0, 128),
            (string) ($body['difficulty'] ?? 'medium'),
            $score,
            $total,
            json_encode($missed, JSON_THROW_ON_ERROR),
        ]);
        Response::json(['success' => true]);
    }

    public function quizHistory(Request $request, array $auth): void
    {
        if ($auth['role'] !== 'student') {
            Response::error('Students only.', 403);
            return;
        }
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT id, subject_label, difficulty, score, total, missed_json, created_at
             FROM quiz_attempts WHERE user_id = ? ORDER BY created_at DESC LIMIT 20'
        );
        $stmt->execute([(int) $auth['user']['id']]);
        Response::json(['success' => true, 'attempts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function report(Request $request, array $auth): void
    {
        if ($auth['role'] !== 'student') {
            Response::error('Students only.', 403);
            return;
        }
        $body = $request->body ?? [];
        $comment = trim(strip_tags((string) ($body['comment'] ?? '')));
        if (mb_strlen($comment) < 3) {
            Response::error('Please describe the problem.', 422);
            return;
        }
        $pdo = Connection::get();
        $pdo->prepare(
            'INSERT INTO ai_reports (user_id, session_token, item_ref, comment) VALUES (?, ?, ?, ?)'
        )->execute([
            (int) $auth['user']['id'],
            (string) ($body['session_token'] ?? ''),
            (string) ($body['item_ref'] ?? ''),
            mb_substr($comment, 0, 2000),
        ]);
        Response::json(['success' => true]);
    }
}
