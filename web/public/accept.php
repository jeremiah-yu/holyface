<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if (empty($_SESSION['amgs_user']) || empty($_SESSION['amgs_token'])) {
    header('Location: ' . url('login.php'));
    exit;
}

$catalog = legal_catalog();
$error = null;
$ok = null;
$requiresGuardian = !empty($_SESSION['amgs_requires_guardian']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid CSRF token. Please refresh and try again.';
    } elseif (empty($_POST['scrolled']) || empty($_POST['accepted'])) {
        $error = 'Please scroll through the policies and tick the acceptance box.';
    } elseif ($requiresGuardian && (trim((string) ($_POST['guardian_name'] ?? '')) === '' || empty($_POST['guardian_confirmed']))) {
        $error = 'Parent or guardian name and confirmation are required for users under 18.';
    } else {
        $data = amgs_auth('POST', '/legal/accept', (string) $_SESSION['amgs_token'], [
            'scrolled' => true,
            'accepted' => true,
            'guardian_name' => trim((string) ($_POST['guardian_name'] ?? '')),
            'relationship' => trim((string) ($_POST['relationship'] ?? 'parent/guardian')),
            'guardian_confirmed' => !empty($_POST['guardian_confirmed']),
        ]);
        if (!empty($data['success']) && !empty($data['complete'])) {
            $_SESSION['amgs_consent'] = true;
            $role = $_SESSION['amgs_user']['role'] ?? '';
            $target = match ($role) {
                'student' => 'student-portal.php',
                'teacher' => 'teacher-portal.php',
                default => 'admin-portal.php',
            };
            header('Location: ' . url($target));
            exit;
        }
        $error = (string) ($data['message'] ?? 'Could not record acceptance.');
    }
}

$school = cfg()['school'];
$pageTitle = 'Accept Terms and Policies — Holy Face AMGS';
$pageDescription = 'First-login acceptance of Terms of Use, Privacy Policy, and AI Use Policy.';
$canonical = abs_url('accept.php');
$nav = '';
require dirname(__DIR__) . '/templates/header.php';
?>
<section class="section">
  <div class="container narrow">
    <h1>Accept Terms and Policies</h1>
    <p class="lead">Version <?= e((string) $catalog['version']) ?> · Last updated: <?= e((string) $catalog['last_updated_label']) ?></p>
    <?php
      $disclaimer = trim((string) ($catalog['disclaimer'] ?? ''));
      if ($disclaimer !== ''):
    ?>
      <p class="draft-banner" role="note"><?= e($disclaimer) ?></p>
    <?php endif; ?>
    <p>Scroll to the end of the documents, then confirm. Material updates require a new acceptance.</p>
    <?php if ($error): ?>
      <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <div id="policy-scroll" class="policy-scroll prose legal" tabindex="0">
      <?php foreach ($catalog['documents'] as $doc): ?>
        <h2><?= e((string) $doc['title']) ?></h2>
        <?php foreach ($doc['sections'] as $section): ?>
          <h3><?= e((string) $section['id']) ?> <?= e((string) $section['title']) ?></h3>
          <p><?= nl2br(e((string) $section['body'])) ?></p>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <p id="policy-end"><strong>End of policies.</strong></p>
    </div>
    <form method="post" class="accept-actions" id="accept-form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="scrolled" id="scrolled" value="">
      <label>
        <input type="checkbox" name="accepted" id="accepted" value="1" required disabled>
        I have read and agree to the Terms of Use, Privacy Policy, and AI Use Policy (v<?= e((string) $catalog['version']) ?>).
      </label>
      <?php if ($requiresGuardian): ?>
        <label>Parent / guardian full name
          <input name="guardian_name" required>
        </label>
        <label>Relationship
          <input name="relationship" value="parent/guardian" required>
        </label>
        <label>
          <input type="checkbox" name="guardian_confirmed" value="1" required>
          I confirm I am the parent or guardian (or I am with them now) and I consent to AMGS processing this learner’s data.
        </label>
      <?php endif; ?>
      <button type="submit" class="btn btn-gold" id="accept-btn" disabled>Accept and continue</button>
    </form>
  </div>
</section>
<script>
(function () {
  var box = document.getElementById('policy-scroll');
  var scrolled = document.getElementById('scrolled');
  var accepted = document.getElementById('accepted');
  var btn = document.getElementById('accept-btn');
  function nearEnd() {
    return box.scrollTop + box.clientHeight >= box.scrollHeight - 24;
  }
  function unlock() {
    if (!nearEnd()) return;
    scrolled.value = '1';
    accepted.disabled = false;
    btn.disabled = false;
  }
  box.addEventListener('scroll', unlock);
  if (box.scrollHeight <= box.clientHeight + 8) unlock();
})();
</script>
<?php require dirname(__DIR__) . '/templates/footer.php';