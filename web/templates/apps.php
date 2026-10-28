<?php
$mobileUrl = download_url('mobile');
$desktopUrl = download_url('desktop');
?>
<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Downloads</p>
    <h1>Get the apps</h1>
    <p class="lead">Scan the QR code, or use the download button. Mobile is for students. Desktop is for teachers.</p>
  </div>
</section>
<section class="section">
  <div class="container split">
    <article class="card download-card">
      <h2>Holy Face Student (mobile)</h2>
      <p>Grades, official card, and AI study tools on Android.</p>
      <div class="qr-block">
        <img
          src="<?= e(qr_image_url($mobileUrl, 240)) ?>"
          alt="QR code to download the Holy Face Student mobile app"
          width="240"
          height="240"
        >
        <p class="qr-caption">Scan to download the student app</p>
      </div>
      <a class="btn btn-gold" href="<?= e($mobileUrl) ?>">Download Android APK</a>
      <p class="muted small">Scan with your phone camera, or tap the button. Mag-log in with the student account issued by the Registrar.</p>
    </article>
    <article class="card download-card">
      <h2>Holy Face AMGS Teacher (desktop)</h2>
      <p>Grade encoding, automatic computation, and class lists for Windows.</p>
      <div class="qr-block">
        <img
          src="<?= e(qr_image_url($desktopUrl, 240)) ?>"
          alt="QR code to download the Holy Face teacher desktop app"
          width="240"
          height="240"
        >
        <p class="qr-caption">Scan to download the teacher app</p>
      </div>
      <a class="btn btn-navy" href="<?= e($desktopUrl) ?>">Download Windows installer</a>
      <p class="muted small">For faculty PCs. Install only from this school site — never from unofficial links.</p>
    </article>
  </div>
</section>
