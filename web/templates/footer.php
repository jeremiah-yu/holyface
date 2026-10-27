<?php
$school = cfg()['school'];
$year = date('Y');
?>
</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <a class="brand brand-footer" href="<?= e(url('index.php')) ?>">
        <img src="<?= e(url($school['logo'])) ?>" alt="" width="44" height="44" loading="lazy">
        <span><?= e($school['short']) ?> AMGS</span>
      </a>
      <p><?= e($school['name']) ?></p>
      <p><?= e($school['address']) ?></p>
      <p class="disclaimer">
        AMGS is an internal academic monitoring and grading tool of the school.
        It does not replace the Registrar’s official records, and it is not a third-party product endorsement.
      </p>
    </div>
    <div>
      <h2 class="footer-heading">Explore</h2>
      <ul class="footer-links">
        <li><a href="<?= e(url('features.php')) ?>">Features</a></li>
        <li><a href="<?= e(url('how-it-works.php')) ?>">How it works</a></li>
        <li><a href="<?= e(url('strands.php')) ?>">SHS Strands</a></li>
        <li><a href="<?= e(url('apps.php')) ?>">Get the apps</a></li>
        <li><a href="<?= e(cfg()['official_website']) ?>">Official school website</a></li>
      </ul>
    </div>
    <div>
      <h2 class="footer-heading">Legal</h2>
      <ul class="footer-links">
        <li><a href="<?= e(url('terms.php')) ?>">Terms of Use</a></li>
        <li><a href="<?= e(url('privacy.php')) ?>">Privacy Policy</a></li>
        <li><a href="<?= e(url('ai-policy.php')) ?>">AI Use Policy</a></li>
        <li><a href="<?= e(url('cookies.php')) ?>">Cookie notice</a></li>
      </ul>
      <p class="muted">&copy; <?= e($year) ?> <?= e($school['short']) ?>. All rights reserved.</p>
      <?php
        $legalMeta = legal_catalog();
      ?>
      <p class="muted small">Legal pack v<?= e((string) $legalMeta['version']) ?> · Last updated <?= e((string) $legalMeta['last_updated_label']) ?></p>
    </div>
  </div>
</footer>

<div id="cookie-banner" class="cookie-banner" hidden role="dialog" aria-labelledby="cookie-title" aria-describedby="cookie-desc">
  <div class="cookie-inner">
    <h2 id="cookie-title">Cookies</h2>
    <p id="cookie-desc">
      We use necessary cookies so this site works (for example, for security).
      Optional cookies are only set if you accept. Read our
      <a href="<?= e(url('cookies.php')) ?>">Cookie notice</a> and
      <a href="<?= e(url('privacy.php')) ?>">Privacy Policy</a>.
    </p>
    <div class="cookie-actions">
      <button type="button" class="btn btn-outline-dark" data-cookie="reject">Necessary only</button>
      <button type="button" class="btn btn-gold" data-cookie="accept">Accept all</button>
    </div>
  </div>
</div>
<script src="<?= e(url('assets/js/site.js')) ?>" defer></script>
</body>
</html>
