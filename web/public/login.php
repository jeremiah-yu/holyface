<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid CSRF token. Please refresh and try again.';
    } else {
        $data = amgs_post('/auth/login', [
            'username' => trim((string) ($_POST['username'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
        ]);
        if (!empty($data['access_token'])) {
            $role = $data['user']['role'] ?? '';
            if ($role === 'student') {
                header('Location: ' . cfg()['portal_url']);
                exit;
            } else {
                $_SESSION['amgs_token'] = $data['access_token'];
                $_SESSION['amgs_user'] = $data['user'];
                $_SESSION['amgs_consent'] = !empty($data['consent_complete']);
                $_SESSION['amgs_requires_guardian'] = !empty($data['requires_guardian']);
                if (empty($data['consent_complete'])) {
                    header('Location: ' . url('accept.php'));
                    exit;
                }
                $target = $role === 'teacher' ? 'teacher-portal.php' : 'admin-portal.php';
                header('Location: ' . url($target));
                exit;
            }
        } else {
            $error = is_array($data) ? (string) ($data['message'] ?? 'Login failed.') : 'Login failed.';
        }
    }
}

$school = cfg()['school'];
$pageTitle = 'Professor login — Holy Face AMGS';
$pageDescription = 'Professor login for grade encoding. The Registrar uses the desktop app. Students use the mobile app.';
$canonical = abs_url('login.php');
$nav = '';
require web_pkg_root() . '/templates/header.php';
?>
<section class="section portal-login">
  <div class="container narrow">
    <h1>Professor login</h1>
    <p class="lead">Professors encode grades here. Registrar: use the desktop app for files and system maintenance. Students and parents: use the <a href="<?= e(cfg()['portal_url']) ?>" rel="noopener">Student/Parent Portal</a>.</p>
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
    <p class="muted small">Professor account after login. Registrar: <a href="<?= e(url('apps.php')) ?>">download the desktop app</a>. Students: <a href="<?= e(cfg()['portal_url']) ?>" rel="noopener">Student/Parent Portal</a> or mobile app.</p>
  </div>
</section>
<?php require web_pkg_root() . '/templates/footer.php';
