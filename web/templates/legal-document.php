<?php
/** @var string $docCode */
$catalog = require dirname(__DIR__, 2) . '/shared/legal/policies.php';
$doc = $catalog['documents'][$docCode] ?? null;
if (!$doc) {
    echo '<p>Document not found.</p>';
    return;
}
?>
<section class="page-hero">
  <div class="container">
    <h1><?= e((string) $doc['title']) ?></h1>
    <p class="lead">Version <?= e((string) $catalog['version']) ?> · Last updated: <?= e((string) $catalog['last_updated_label']) ?></p>
  </div>
</section>
<section class="section">
  <div class="container prose legal">
    <?php
      $disclaimer = trim((string) ($catalog['disclaimer'] ?? ''));
      if ($disclaimer !== ''):
    ?>
      <p class="draft-banner" role="note"><?= e($disclaimer) ?></p>
    <?php endif; ?>
    <?php foreach ($doc['sections'] as $section): ?>
      <h2><?= e((string) $section['id']) ?> <?= e((string) $section['title']) ?></h2>
      <?php foreach (preg_split("/\n\n/", (string) $section['body']) as $para): ?>
        <p><?= nl2br(e($para)) ?></p>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <p class="legal-meta">Holy Face Academic Suite · <?= e((string) $catalog['school_name']) ?></p>
  </div>
</section>
