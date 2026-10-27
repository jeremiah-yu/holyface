<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . url('login.php'));
    exit;
}
require_portal_user(['student']);
$school = cfg()['school'];
$pageTitle = 'Student workspace — Holy Face AMGS';
$pageDescription = 'Signed-in student workspace.';
$canonical = abs_url('student-portal.php');
$nav = '';
require dirname(__DIR__) . '/templates/header.php';
?>
<section class="section">
  <div class="container narrow">
    <h1>Student workspace</h1>
    <p>Gamitin ang <strong>Holy Face Student Mobile App</strong> para sa grades, study tools, at official grading card. The official card appears only after every subject teacher has submitted finals.</p>
    <a class="btn btn-gold" href="<?= e(url('student-portal.php?logout=1')) ?>">Log out</a>
  </div>
</section>
<?php require dirname(__DIR__) . '/templates/footer.php';
