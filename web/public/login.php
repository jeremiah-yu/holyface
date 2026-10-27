<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$apiBase = rtrim((string) cfg()['api_base_url'], '/');
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid CSRF token. Please refresh and try again.';
    } else {
        $payload = json_encode([
            'username' => trim((string) ($_POST['username'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
        ]);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);
        $resp = @file_get_contents($apiBase . '/auth/login', false, $ctx);
        $data = $resp ? json_decode($resp, true) : null;
        if (!empty($data['access_token'])) {
            $_SESSION['amgs_token'] = $data['access_token'];
            $_SESSION['amgs_user'] = $data['user'];
            $_SESSION['amgs_consent'] = !empty($data['consent_complete']);
            $_SESSION['amgs_requires_guardian'] = !empty($data['requires_guardian']);
            if (empty($data['consent_complete'])) {
                header('Location: ' . url('accept.php'));
                exit;
            }
            $role = $data['user']['role'] ?? '';
            $target = match ($role) {
                'student' => 'student-portal.php',
                'teacher' => 'teacher-portal.php',
                default => 'admin-portal.php',
            };
            header('Location: ' . url($target));
            exit;
        }
        $error = is_array($data) ? (string) ($data['message'] ?? 'Login failed.') : 'Login failed.';
    }
}

$school = cfg()['school'];
$pageTitle = 'AMGS staff login — Holy Face';
$pageDescription = 'Internal AMGS login. Students and parents should use the official school portal.';
$canonical = abs_url('login.php');
$nav = '';
require dirname(__DIR__) . '/templates/header.php';
?>
<section class="section portal-login">
  <div class="container narrow">
    <h1>AMGS staff login</h1>
    <p class="lead">For issued AMGS accounts. Students and parents: use the <a href="<?= e(cfg()['portal_url']) ?>" rel="noopener">Student/Parent Portal</a>.</p>
    <?php if ($error): ?>
      <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="card form-card" autocomplete="on">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label for="username">Username o Email</label>
      <input id="username" name="username" required autocomplete="username">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" required autocomplete="current-password">
      <button type="submit" class="btn btn-gold">Log In</button>
    </form>
      <input id="password" name="password" type="password" required autocomplete="current-password">
      <button type="submit" class="btn btn-gold">Log In</button>
    </form>
    <p class="muted small">Teachers: gamitin ang <strong>Desktop App</strong>. Students: <strong>Mobile App</strong> para sa digital ID.</p>
  </div>
</section>
<?php require dirname(__DIR__) . '/templates/footer.php';
