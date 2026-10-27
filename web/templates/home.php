<?php
$school = cfg()['school'];
$portal = cfg()['portal_url'];
$strands = cfg()['strands'];
?>
<section class="hero">
  <div class="container hero-grid">
    <div>
      <p class="eyebrow"><?= e($school['name']) ?></p>
      <h1>Academic Monitoring &amp; Grading System</h1>
      <p class="promise"><?= e(cfg()['promise']) ?></p>
      <p class="lead">
        A faith-centered, student-focused tool for Senior High School — so learners and teachers
        can see progress clearly. Official grades still come from the Registrar.
      </p>
      <div class="hero-actions">
        <a class="btn btn-gold" href="<?= e($portal) ?>" rel="noopener">Student/Parent Portal</a>
        <a class="btn btn-outline" href="#how-it-works">See How It Works</a>
      </div>
    </div>
    <aside class="hero-card card" aria-label="What AMGS is">
      <h2>Three apps, one school record</h2>
      <ul class="check-list">
        <li><strong>Web</strong> — this information site</li>
        <li><strong>Mobile</strong> — grades and study tools</li>
        <li><strong>Desktop</strong> — teacher grade encoding</li>
      </ul>
      <p class="muted small">AMGS is used by Holy Face. It is not a commercial product sold independently of the school.</p>
    </aside>
  </div>
</section>

<section class="notice-bar" role="note">
  <div class="container">
    Public pages never show student names, LRN, photos, or grades. Log in through the portal to see your own records.
  </div>
</section>

<section id="features" class="section">
  <div class="container">
    <h2>Features</h2>
    <p class="section-intro">Built so students know where they stand — without waiting for card-giving day.</p>
    <div class="feature-grid">
      <?php
      $features = [
          ['title' => 'Real-time grade viewing', 'text' => 'See encoded scores as teachers save them — by subject and quarter.'],
          ['title' => 'Per-subject & per-quarter', 'text' => 'Written work, performance tasks, and quarterly assessment in one breakdown.'],
          ['title' => 'AI-assisted study tools', 'text' => 'Study plans, flashcards, and sample quizzes from class materials — never sending a student’s name or LRN to an outside AI.'],
      ];
      foreach ($features as $f):
      ?>
      <article class="card feature-card">
        <h3><?= e($f['title']) ?></h3>
        <p><?= e($f['text']) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
    <p class="center mt"><a class="text-link" href="<?= e(url('features.php')) ?>">All features in detail</a></p>
  </div>
</section>

<section id="roles" class="section section-soft">
  <div class="container">
    <h2>For students and teachers</h2>
    <div class="role-grid">
      <article class="card role-card">
        <p class="eyebrow">Student</p>
        <h3>Mobile app</h3>
        <p>Grades and study tools on a phone. Mag-log in with the account issued by the Registrar.</p>
      </article>
      <article class="card role-card">
        <p class="eyebrow">Teacher</p>
        <h3>Desktop app</h3>
        <p>Encode written work, performance tasks, and quarterly assessments. Grades compute automatically. Class lists stay on school servers.</p>
      </article>
    </div>
  </div>
</section>

<section id="how-it-works" class="section">
  <div class="container">
    <h2>How it works</h2>
    <ol class="steps">
      <li><strong>Enroll.</strong> Complete SHS requirements. The Registrar issues AMGS access.</li>
      <li><strong>Teachers encode.</strong> Scores are entered in the desktop app using DepEd-style components.</li>
      <li><strong>Students check progress.</strong> Open the portal or mobile app to see your grades.</li>
    </ol>
    <p class="center mt"><a class="btn btn-navy" href="<?= e(url('how-it-works.php')) ?>">Read the full walkthrough</a></p>
  </div>
</section>

<section id="strands" class="section">
  <div class="container">
    <h2>Choose your strand</h2>
    <p class="section-intro">Welcome to Holy Face! Piliin ang iyong strand — Grade 11 and Grade 12.</p>
    <div class="strand-grid">
      <?php foreach ($strands as $strand): ?>
      <article class="card strand-card">
        <p class="strand-code"><?= e($strand['code']) ?></p>
        <h3><?= e($strand['name']) ?></h3>
        <p><?= e($strand['blurb']) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
    <p class="center mt"><a class="text-link" href="<?= e(url('strands.php')) ?>">All SHS strands</a></p>
  </div>
</section>

<section id="apps" class="section">
  <div class="container split">
    <article class="card">
      <h2>Student mobile app</h2>
      <p>Grades and AI study tools.</p>
      <p class="badge">Coming soon</p>
    </article>
    <article class="card">
      <h2>Teacher desktop app</h2>
      <p>Grade encoding and class lists for faculty.</p>
      <p class="badge">Coming soon</p>
    </article>
  </div>
  <p class="center mt"><a class="btn btn-navy" href="<?= e(url('apps.php')) ?>">Get the apps</a></p>
</section>
