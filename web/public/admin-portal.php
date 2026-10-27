<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_portal_user(['admin']);
$school = cfg()['school'];
$pageTitle = 'Registrar workspace — Holy Face AMGS';
$pageDescription = 'Signed-in registrar workspace.';
$canonical = abs_url('admin-portal.php');
$nav = '';
require dirname(__DIR__) . '/templates/header.php';
?>
<section class="section">
  <div class="container narrow">
    <h1>Registrar workspace</h1>
    <p>Use the AMGS API and audit logs for official monitoring. This website remains a public information site only.</p>
  </div>
</section>
<?php require dirname(__DIR__) . '/templates/footer.php';
