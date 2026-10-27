<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Senior High School · Grades 11 &amp; 12</p>
    <h1>Choose your strand &amp; enroll</h1>
    <p class="lead">Welcome to Holy Face! Five Senior High School strands — the same descriptions published on the official school website.</p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="strand-grid">
      <?php foreach (cfg()['strands'] as $strand): ?>
        <article class="card strand-card">
          <p class="strand-code"><?= e($strand['code']) ?></p>
          <h2><?= e($strand['name']) ?></h2>
          <p><?= e($strand['blurb']) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="mt">AMGS accounts are issued after the Registrar confirms enrollment. Enrollment details are on the <a href="<?= e(cfg()['official_website']) ?>">official school website</a>.</p>
  </div>
</section>
