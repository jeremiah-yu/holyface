<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_portal_user(['teacher']);
$school = cfg()['school'];
$pageTitle = 'Teacher workspace — Holy Face AMGS';
$pageDescription = 'Signed-in teacher workspace.';
$canonical = abs_url('teacher-portal.php');
$nav = '';
require dirname(__DIR__) . '/templates/header.php';
?>
<section class="section">
  <div class="container narrow">
    <h1>Teacher workspace</h1>
    <p>I-launch ang <strong>AMGS Desktop App</strong> para mag-encode ng grades at tingnan ang class lists. This public website never shows a class roster.</p>
    <a class="btn btn-gold" href="<?= e(url('login.php')) ?>">Back</a>
  </div>
</section>
<?php require dirname(__DIR__) . '/templates/footer.php';
