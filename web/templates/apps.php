<?php
$mobileUrl = download_url('mobile');
$desktopUrl = download_url('desktop');
?>
<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Downloads</p>
    <h1>Get the apps</h1>
    <p class="lead">Students use mobile. Professors encode grades in the browser — on a phone, open Professor login, then choose Add to Home Screen or Install app. The Registrar desktop app keeps files and maintains the system.</p>
  </div>
</section>
<section class="section">
  <div class="container role-grid">
    <article class="card download-card">
      <h2>Student (mobile)</h2>
      <p>Grades, official card, and AI study tools on Android.</p>
      <div class="qr-block">
        <img
          src="<?= e(qr_image_url($mobileUrl, 240)) ?>"
          alt="QR code to download the Holy Face Student mobile app"
          width="240"
          height="240"
        >
        <p class="qr-caption">Scan to download the student APK</p>
      </div>
      <a class="btn btn-gold" href="<?= e($mobileUrl) ?>">Download Android APK</a>
      <p class="muted small">Mag-log in with the student account issued by the Registrar.</p>
    </article>
    <article class="card download-card">
      <h2>Registrar (desktop)</h2>
      <p>File vault, accounts, period locks, and system maintenance for Windows.</p>
      <div class="qr-block">
        <img
          src="<?= e(qr_image_url($desktopUrl, 240)) ?>"
          alt="QR code to download the Holy Face Registrar desktop app"
          width="240"
          height="240"
        >
        <p class="qr-caption">Scan to download the Windows installer</p>
      </div>
      <a class="btn btn-navy" href="<?= e($desktopUrl) ?>">Download Windows installer</a>
      <p class="muted small">For the Registrar office PC. Install only from this school site.</p>
    </article>
  </div>
</section>
