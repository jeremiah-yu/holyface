<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_portal_user(['admin']);
$school = cfg()['school'];
$pageTitle = 'Registrar workspace — Holy Face AMGS';
$pageDescription = 'Registrar uses the desktop app to keep files and maintain the system.';
$canonical = abs_url('admin-portal.php');
$nav = '';
$desktopUrl = download_url('desktop');
require web_pkg_root() . '/templates/header.php';
?>
<section class="section">
  <div class="container narrow">
    <h1>Registrar workspace</h1>
    <p>Keep school files, create accounts, lock grading periods, and review audit logs in the <strong>AMGS Registrar desktop app</strong>. Professors encode grades on the web. This public site never shows learner records.</p>
    <div class="qr-block">
      <img src="<?= e(qr_image_url($desktopUrl, 200)) ?>" alt="QR code to download the Registrar desktop app" width="200" height="200">
    </div>
    <p><a class="btn btn-navy" href="<?= e($desktopUrl) ?>">Download Windows installer</a></p>
  </div>
</section>
<?php require web_pkg_root() . '/templates/footer.php';
