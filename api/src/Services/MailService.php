<?php
declare(strict_types=1);

namespace HolyFace\Api\Services;

use HolyFace\Api\Config\Env;

/** Sends account notices through the Registrar's sending mailbox. */
final class MailService
{
    public function sendAccount(
        string $to,
        string $fullName,
        string $username,
        string $password,
        string $role,
        string $studentNumber = ''
    ): bool {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !$this->isReady()) {
            return false;
        }
        $name = $fullName !== '' ? $fullName : $username;
        $roleLabel = match ($role) {
            'student' => 'Student',
            'teacher' => 'Professor',
            'admin' => 'Registrar',
            default => 'User',
        };
        $where = match ($role) {
            'student' => 'Sign in on the Holy Face Student mobile app.',
            'teacher' => 'Sign in at https://holyface.infinityfreeapp.com/login.php',
            'admin' => 'Sign in on the Holy Face AMGS Registrar desktop app.',
            default => 'Sign in with the Holy Face AMGS app for your role.',
        };
        $lines = [
            "Hello {$name},",
            '',
            'The Registrar created your Holy Face AMGS account.',
            '',
            "Role: {$roleLabel}",
            "Username: {$username}",
        ];
        if ($role === 'student' && $studentNumber !== '') {
            $lines[] = "Student number: {$studentNumber}";
        }
        $lines[] = "Temporary password: {$password}";
        $lines[] = '';
        $lines[] = $where;
        $lines[] = 'Change this password the first time you sign in.';
        $lines[] = '';
        $lines[] = 'Holy Face of Jesus Lyceum of San Jose, Inc.';
        return $this->send($to, 'Your Holy Face AMGS account', implode("\r\n", $lines));
    }

    /** @return array{from: string, host: string, port: int, user: string, pass: string} */
    public function config(): array
    {
        $saved = [];
        $path = $this->configPath();
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $saved = $decoded;
            }
        }
        $from = trim((string) ($saved['from'] ?? Env::get('MAIL_FROM', 'registrar@holyface.school')));
        $host = trim((string) ($saved['host'] ?? Env::get('MAIL_SMTP_HOST', '')));
        $port = (int) ($saved['port'] ?? Env::get('MAIL_SMTP_PORT', '587'));
        $user = trim((string) ($saved['user'] ?? Env::get('MAIL_SMTP_USER', '')));
        $pass = (string) ($saved['pass'] ?? Env::get('MAIL_SMTP_PASS', ''));
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $from = 'registrar@holyface.school';
        }
        if ($port !== 465 && $port !== 587) {
            $port = 587;
        }
        return ['from' => $from, 'host' => $host, 'port' => $port, 'user' => $user, 'pass' => $pass];
    }

    /** @return array{from: string, host: string, port: int, user: string, password_set: bool, ready: bool} */
    public function publicConfig(): array
    {
        $config = $this->config();
        return [
            'from' => $config['from'],
            'host' => $config['host'] !== '' ? $config['host'] : 'smtp.gmail.com',
            'port' => $config['port'],
            'user' => $config['user'],
            'password_set' => $config['pass'] !== '',
            'ready' => $this->isReady(),
        ];
    }

    public function isReady(): bool
    {
        $config = $this->config();
        return $config['host'] !== ''
            && $config['user'] !== ''
            && $config['pass'] !== ''
            && filter_var($config['from'], FILTER_VALIDATE_EMAIL);
    }

    /** @param array<string, mixed> $input */
    public function save(array $input): void
    {
        $current = $this->config();
        $from = trim((string) ($input['from'] ?? $current['from']));
        $host = trim((string) ($input['host'] ?? $current['host']));
        $port = (int) ($input['port'] ?? $current['port']);
        $user = trim((string) ($input['user'] ?? $current['user']));
        $pass = array_key_exists('pass', $input) && trim((string) $input['pass']) !== ''
            ? (string) $input['pass']
            : $current['pass'];
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Enter a valid sending email.');
        }
        if ($host === '' || $user === '' || $pass === '') {
            throw new \InvalidArgumentException('Sending email, mailbox, and app password are required.');
        }
        if ($port !== 465 && $port !== 587) {
            throw new \InvalidArgumentException('Use SMTP port 587 or 465.');
        }
        $dir = dirname($this->configPath());
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException('The server could not store the sending email.');
        }
        $written = file_put_contents(
            $this->configPath(),
            json_encode(['from' => $from, 'host' => $host, 'port' => $port, 'user' => $user, 'pass' => $pass], JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
        if ($written === false) {
            throw new \RuntimeException('The server could not store the sending email.');
        }
    }

    public function sendTest(): bool
    {
        $config = $this->config();
        if (!$this->isReady()) {
            return false;
        }
        $body = "This mailbox is the Holy Face AMGS sending email.\r\n\r\n"
            . "New student, professor, and Registrar accounts will be emailed from here.\r\n";
        return $this->send($config['from'], 'Holy Face AMGS sending email test', $body);
    }

    public function send(string $to, string $subject, string $body): bool
    {
        if (!$this->isReady() || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $config = $this->config();
        return $this->sendSmtp($config, $to, $subject, $body);
    }

    /** @param array{from: string, host: string, port: int, user: string, pass: string} $config */
    private function sendSmtp(array $config, string $to, string $subject, string $body): bool
    {
        $port = $config['port'];
        $remote = ($port === 465 ? 'ssl://' : 'tcp://') . $config['host'] . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!$fp) {
            return false;
        }
        stream_set_timeout($fp, 20);
        $read = static function ($fp): string {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $write = static function ($fp, string $cmd) use ($read): string {
            fwrite($fp, $cmd . "\r\n");
            return $read($fp);
        };
        $greet = $read($fp);
        if (!str_starts_with($greet, '220')) {
            fclose($fp);
            return false;
        }
        $ehlo = $write($fp, 'EHLO holyface.infinityfreeapp.com');
        if ($port !== 465 && str_contains($ehlo, 'STARTTLS')) {
            $tls = $write($fp, 'STARTTLS');
            if (!str_starts_with($tls, '220') || !stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($fp);
                return false;
            }
            $write($fp, 'EHLO holyface.infinityfreeapp.com');
        }
        $write($fp, 'AUTH LOGIN');
        $write($fp, base64_encode($config['user']));
        $auth = $write($fp, base64_encode($config['pass']));
        if (!str_starts_with($auth, '235')) {
            fclose($fp);
            return false;
        }
        $fromOk = $write($fp, 'MAIL FROM:<' . $config['from'] . '>');
        $rcptOk = $write($fp, 'RCPT TO:<' . $to . '>');
        if (!str_starts_with($fromOk, '250') || !str_starts_with($rcptOk, '250')) {
            fclose($fp);
            return false;
        }
        $write($fp, 'DATA');
        $payload = 'From: Holy Face AMGS <' . $config['from'] . ">\r\n"
            . 'To: <' . $to . ">\r\n"
            . 'Subject: ' . $this->encodeSubject($subject) . "\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "\r\n"
            . str_replace("\n.", "\n..", $body) . "\r\n.";
        $sent = $write($fp, $payload);
        $write($fp, 'QUIT');
        fclose($fp);
        return str_starts_with($sent, '250');
    }

    private function encodeSubject(string $subject): string
    {
        return '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }

    private function configPath(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'mail.json';
    }
}
