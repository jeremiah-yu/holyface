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
    public function __construct(
        private ContentFilter $filter,
        private GeminiClient $gemini = new GeminiClient(),
    ) {}

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
        $allowed = ['study_plan', 'flashcards', 'quiz', 'chat'];
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

        $message = (string) ($input['message'] ?? '');
        if ($this->filter->isInappropriate($subject . ' ' . $topic . ' ' . $notes . ' ' . $message)) {
            throw new \InvalidArgumentException('That topic is not allowed. Keep requests school-appropriate.');
        }

        if ($tool === 'chat' && mb_strlen($notes) > 1600) {
            $notes = mb_substr($notes, 0, 1600);
        } elseif (mb_strlen($notes) > 8000) {
            $notes = mb_substr($notes, 0, 8000);
        }

        $source = trim($topic . "\n" . $notes);
        if (in_array($tool, ['flashcards', 'quiz'], true) && $notes === '') {
            throw new \InvalidArgumentException('Upload class notes first so Gemini can generate from them.');
        }
        if ($tool !== 'chat' && $source === '') {
            throw new \InvalidArgumentException('Provide a topic or notes.');
        }

        $payload = $this->geminiGenerate($tool, $subject, $topic, $notes, $input)
            ?? $this->localGenerate($tool, $subject, $topic, $notes, $input);
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
     * @return array<string, mixed>|null
     */
    private function geminiGenerate(string $tool, string $subject, string $topic, string $notes, array $input): ?array
    {
        if (!$this->gemini->enabled()) {
            return null;
        }
        $system = 'You are a Senior High School study tutor for Holy Face of Jesus Lyceum. '
            . 'You receive only a subject, topic, and lesson notes. '
            . 'Never ask for or use a student name, student number, photo, email, or phone number. '
            . 'If notes look like they contain identity, ignore those parts. '
            . 'Do not write graded answers for the student to submit. Help them study. '
            . 'Keep Filipino and English mixed if the notes are mixed. Be accurate and school-appropriate.';
        $context = "Subject: {$subject}\nTopic: {$topic}\nNotes:\n" . ($notes !== '' ? $notes : '(no extra notes)');

        if ($tool === 'chat') {
            $message = $this->filter->redactIdentity(trim((string) ($input['message'] ?? '')));
            $text = $this->gemini->generateText(
                $system,
                $context . "\n\nStudent question (no identity should be here):\n" . $message
                    . "\n\nAnswer in 4 short sentences or fewer.",
                220,
                true
            );
            return $text !== null ? ['reply' => $text, 'used_notes' => $notes !== '', 'provider' => 'gemini'] : null;
        }

        $ask = match ($tool) {
            'flashcards' => $context . "\n\nCreate 6 flashcards from the notes. JSON: {\"cards\":[{\"front\":\"\",\"back\":\"\"}]}",
            'study_plan' => $context . "\n\nCreate a 5-day study plan. JSON: {\"title\":\"\",\"duration_days\":5,\"hours_per_day\":"
                . (float) ($input['hours_per_day'] ?? 1.5)
                . ",\"schedule\":[{\"day\":1,\"focus\":\"\",\"minutes\":60,\"tasks\":[\"\"]}]}",
            default => $context . "\n\nCreate "
                . max(5, min(8, (int) ($input['question_count'] ?? 6)))
                . " quiz questions from the notes (mix multiple_choice and true_false). JSON: "
                . "{\"questions\":[{\"id\":1,\"type\":\"multiple_choice\",\"question\":\"\",\"options\":[\"A\",\"B\",\"C\",\"D\"],\"answer_index\":0,\"explanation\":\"\"}]}",
        };
        $json = $this->gemini->generateJson($system, $ask, 900);
        if ($json === null) {
            return null;
        }
        if ($tool === 'flashcards') {
            $cards = [];
            foreach ($json['cards'] ?? [] as $card) {
                if (!is_array($card)) {
                    continue;
                }
                $front = trim((string) ($card['front'] ?? ''));
                $back = trim((string) ($card['back'] ?? ''));
                if ($front !== '' && $back !== '') {
                    $cards[] = ['front' => $front, 'back' => $back];
                }
            }
            return $cards ? ['cards' => $cards, 'count' => count($cards), 'provider' => 'gemini'] : null;
        }
        if ($tool === 'study_plan') {
            $schedule = $json['schedule'] ?? null;
            if (!is_array($schedule) || $schedule === []) {
                return null;
            }
            $json['provider'] = 'gemini';
            $json['duration_days'] = (int) ($json['duration_days'] ?? count($schedule));
            return $json;
        }
        $questions = [];
        foreach ($json['questions'] ?? [] as $i => $q) {
            if (!is_array($q)) {
                continue;
            }
            $options = $q['options'] ?? ['True', 'False'];
            if (!is_array($options) || $options === []) {
                continue;
            }
            $questions[] = [
                'id' => (int) ($q['id'] ?? $i + 1),
                'type' => (string) ($q['type'] ?? 'multiple_choice'),
                'difficulty' => (string) ($input['difficulty'] ?? 'medium'),
                'question' => (string) ($q['question'] ?? ''),
                'options' => array_values(array_map('strval', $options)),
                'answer_index' => (int) ($q['answer_index'] ?? 0),
                'explanation' => (string) ($q['explanation'] ?? 'Check with your teacher.'),
            ];
        }
        return $questions ? ['questions' => $questions, 'count' => count($questions), 'provider' => 'gemini'] : null;
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
            'chat' => $this->buildChatReply($subject, $topic, $notes, $input),
            default => $this->buildQuiz($topic, $text, $input),
        };
    }

    /**
     * Turn an uploaded notes file into plain text. Never keep the original file.
     */
    public function extractUploadedText(string $filename, string $base64): string
    {
        $bin = base64_decode($base64, true);
        if ($bin === false || $bin === '') {
            throw new \InvalidArgumentException('Could not read that file.');
        }
        if (strlen($bin) > 15_728_640) {
            throw new \InvalidArgumentException('File is too large. Maximum is 15 MB.');
        }
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $docs = ['pdf', 'doc', 'docx'];
        $images = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, array_merge($docs, $images), true)) {
            throw new \InvalidArgumentException('Upload a PDF, Word document (.doc/.docx), or an image (JPG, PNG, WEBP).');
        }
        $this->assertAllowedBinary($bin, $ext);
        $text = match ($ext) {
            'docx', 'doc' => $this->textFromDocx($bin),
            'pdf' => $this->textFromPdf($bin),
            default => $this->textFromImage($bin, $ext),
        };
        $text = $this->filter->redactIdentity(trim($text));
        if ($this->filter->isInappropriate($text)) {
            throw new \InvalidArgumentException('That file is not allowed. Keep uploads school-appropriate.');
        }
        $this->assertStudyNotes($text, in_array($ext, $images, true));
        if (mb_strlen($text) > 8000) {
            $text = mb_substr($text, 0, 8000);
        }
        return $text;
    }

    private function assertAllowedBinary(string $bin, string $ext): void
    {
        $head = substr($bin, 0, 8);
        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true);
        if ($isImage) {
            $ok = str_starts_with($head, "\x89PNG")
                || str_starts_with($head, "\xFF\xD8\xFF")
                || str_starts_with($head, 'RIFF');
            if (!$ok) {
                throw new \InvalidArgumentException('That photo could not be read. Use JPG, PNG, or WEBP.');
            }
            return;
        }
        if (str_starts_with($head, 'MZ')) {
            throw new \InvalidArgumentException('That file is not study notes, so it was not read.');
        }
        if (str_starts_with($head, 'PK') && !in_array($ext, ['docx', 'doc'], true)) {
            throw new \InvalidArgumentException('That archive is not read. Upload a PDF, Word file, or photo.');
        }
        if ($ext === 'pdf' && !str_starts_with($head, '%PDF')) {
            throw new \InvalidArgumentException('That PDF could not be read as notes.');
        }
        if ($ext === 'doc' && !str_starts_with($head, 'PK')) {
            throw new \InvalidArgumentException('Old .doc files are not supported. Save as .docx or PDF.');
        }
    }

    private function assertStudyNotes(string $text, bool $fromImage = false): void
    {
        if ($text === '') {
            throw new \InvalidArgumentException('No readable text was found in that file.');
        }
        $letters = preg_match_all('/\p{L}/u', $text);
        $minLetters = $fromImage ? 20 : 60;
        if ($letters < $minLetters) {
            throw new \InvalidArgumentException('That file does not look like class notes, so it was not read.');
        }
        $lower = mb_strtolower($text);
        $chatty = ['lol', 'lmao', 'omg', 'wtf', 'bruh', 'hey whats up', 'see you later', 'movie night'];
        foreach ($chatty as $bit) {
            if (str_contains($lower, $bit)) {
                throw new \InvalidArgumentException('This file is not study notes, so it was not read. Upload class notes only.');
            }
        }
        $studyHits = 0;
        foreach (['lesson', 'chapter', 'define', 'definition', 'example', 'formula', 'equation', 'photosynthesis', 'cell', 'atom', 'review', 'notes', 'meaning', 'theorem', 'process', 'function', 'organism', 'history', 'literature', 'quarter', 'strand', 'solve', 'because'] as $word) {
            if (str_contains($lower, $word)) {
                $studyHits++;
            }
        }
        $sentences = preg_split('/[.!?\n]+/', $text) ?: [];
        $sentences = array_values(array_filter($sentences, static fn (string $s) => mb_strlen(trim($s)) > 20));
        if ($studyHits === 0 && count($sentences) < 3) {
            throw new \InvalidArgumentException('This file is not study notes, so it was not read. Upload class notes only.');
        }
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

    /** @param array<string, mixed> $input */
    private function buildChatReply(string $subject, string $topic, string $notes, array $input): array
    {
        $message = $this->filter->redactIdentity(trim((string) ($input['message'] ?? '')));
        if (mb_strlen($message) < 2) {
            throw new \InvalidArgumentException('Type a study question first.');
        }
        if (mb_strlen($message) > 800) {
            $message = mb_substr($message, 0, 800);
        }

        $words = array_values(array_filter(
            preg_split('/\W+/u', mb_strtolower($message)) ?: [],
            static fn (string $w) => mb_strlen($w) > 3
        ));
        $sentences = array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?])\s+/', $notes) ?: [])));
        $hits = [];
        foreach ($sentences as $sentence) {
            $low = mb_strtolower($sentence);
            foreach ($words as $word) {
                if (str_contains($low, $word)) {
                    $hits[] = $sentence;
                    break;
                }
            }
            if (count($hits) >= 3) {
                break;
            }
        }

        if ($hits) {
            $reply = 'From your notes on ' . ($topic !== '' ? $topic : $subject) . ":\n\n";
            foreach ($hits as $hit) {
                $reply .= '• ' . mb_substr($hit, 0, 220) . "\n";
            }
            $reply .= "\nUse this to review, then check your textbook or teacher. I do not replace graded work.";
        } elseif ($notes !== '') {
            $reply = 'I read your notes, but I did not find a clear match for that question. '
                . 'Try asking about a word or idea that appears in the file — for example a definition, formula, or key term. '
                . 'Subject: ' . $subject . '.';
        } else {
            $reply = 'Upload a notes file on the Study tab first, then ask me about that topic. '
                . 'I can also help you plan how to review ' . ($subject !== '' ? $subject : 'your subject')
                . ': read the lesson, write 5 key terms, then take a sample quiz.';
        }

        return [
            'reply' => $reply,
            'used_notes' => $notes !== '',
        ];
    }

    private function asUtf8(string $bin): string
    {
        $text = mb_convert_encoding($bin, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        return trim(preg_replace("/[ \t]+\n/", "\n", $text) ?? $text);
    }

    private function textFromDocx(string $bin): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \InvalidArgumentException('DOCX is not supported on this server. Upload a .txt file.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'amgs');
        if ($tmp === false) {
            throw new \InvalidArgumentException('Could not open that file.');
        }
        file_put_contents($tmp, $bin);
        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            unlink($tmp);
            throw new \InvalidArgumentException('That Word file could not be opened.');
        }
        $xml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();
        unlink($tmp);
        $xml = str_replace(['</w:p>', '</w:tr>'], "\n", $xml);
        return $this->asUtf8(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    private function textFromImage(string $bin, string $ext): string
    {
        if (!$this->gemini->enabled()) {
            throw new \InvalidArgumentException('Photo notes need the study AI to be on. Upload a PDF or Word file instead.');
        }
        $mime = match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
        [$mime, $b64] = $this->compactImage($bin, $mime);
        $text = $this->gemini->extractImageText($mime, $b64);
        if ($text === null || trim($text) === '') {
            throw new \InvalidArgumentException('Could not read that photo. Try a clearer shot, or upload a PDF / Word file.');
        }
        return $this->asUtf8($text);
    }

    /** @return array{0:string,1:string} */
    private function compactImage(string $bin, string $mime): array
    {
        if (!function_exists('imagecreatefromstring')) {
            return [$mime, base64_encode($bin)];
        }
        $img = @imagecreatefromstring($bin);
        if ($img === false) {
            return [$mime, base64_encode($bin)];
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $max = 1280;
        if ($w > $max || $h > $max) {
            $scale = $max / max($w, $h);
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $resized = imagecreatetruecolor($nw, $nh);
            if ($resized !== false) {
                imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($img);
                $img = $resized;
            }
        }
        ob_start();
        imagejpeg($img, null, 72);
        imagedestroy($img);
        $out = ob_get_clean();
        return ['image/jpeg', base64_encode(is_string($out) && $out !== '' ? $out : $bin)];
    }

    private function textFromPdf(string $bin): string
    {
        $out = [];
        if (preg_match_all('/\((?:\\\\.|[^\\\\)]){4,}\)/', $bin, $m)) {
            foreach ($m[0] as $chunk) {
                $inner = substr($chunk, 1, -1);
                $inner = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)'], ["\n", '', ' ', '(', ')'], $inner);
                if (preg_match('/[A-Za-z]{3,}/', $inner)) {
                    $out[] = $inner;
                }
            }
        }
        return $this->asUtf8(implode(' ', $out));
    }
}
