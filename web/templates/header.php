<?php
/** @var array $school */
/** @var string $pageTitle */
/** @var string $pageDescription */
/** @var string $nav */
$school = $school ?? cfg()['school'];
$pageTitle = $pageTitle ?? $school['short'] . ' AMGS';
$pageDescription = $pageDescription ?? 'Academic Monitoring and Grading System of Holy Face of Jesus Lyceum of San Jose, Inc.';
$nav = $nav ?? '';
$canonical = $canonical ?? abs_url(ltrim($_SERVER['SCRIPT_NAME'] ? basename((string) $_SERVER['SCRIPT_NAME']) : '', '/'));
$ogImage = $school['logo_absolute'];
$portalUrl = cfg()['portal_url'];
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($pageDescription) ?>">
  <meta name="robots" content="index,follow">
  <link rel="canonical" href="<?= e($canonical) ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Holy Face AMGS">
  <meta property="og:title" content="<?= e($pageTitle) ?>">
  <meta property="og:description" content="<?= e($pageDescription) ?>">
  <meta property="og:url" content="<?= e($canonical) ?>">
  <meta property="og:image" content="<?= e($ogImage) ?>">
  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="<?= e($pageTitle) ?>">
  <meta name="twitter:description" content="<?= e($pageDescription) ?>">
  <meta name="theme-color" content="#0b1f3a">
  <link rel="icon" href="<?= e(url($school['logo'])) ?>">
  <link rel="apple-touch-icon" href="<?= e(url($school['logo'])) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/css/site.css')) ?>?v=3">
  <script type="application/ld+json">
  <?= json_encode([
      '@context' => 'https://schema.org',
      '@type' => 'EducationalOrganization',
      'name' => $school['name'],
      'alternateName' => $school['short'],
      'url' => cfg()['official_website'],
      'logo' => $ogImage,
      'address' => [
          '@type' => 'PostalAddress',
          'streetAddress' => 'R & J Bldg., Blk 09, Lot 6 & 8, Mayon Ave., Amityville, San Jose',
          'addressLocality' => 'Rodriguez',
          'addressRegion' => 'Rizal',
          'addressCountry' => 'PH',
      ],
      'description' => 'Senior High School in Rodriguez, Rizal. The Academic Monitoring and Grading System (AMGS) is an internal academic tool of the school.',
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
  </script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= e(url('index.php')) ?>">
      <img src="<?= e(url($school['logo'])) ?>" alt="Holy Face of Jesus Lyceum logo" width="52" height="52">
      <span class="brand-text">
        <strong>Holy Face</strong>
        <small>Academic Monitoring &amp; Grading</small>
      </span>
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" aria-label="Open menu">
      <span></span><span></span><span></span>
    </button>
    <nav id="primary-nav" aria-label="Primary">
      <ul class="nav-list">
        <li><a href="<?= e(url('index.php')) ?>" <?= $nav === 'home' ? 'aria-current="page"' : '' ?>>Home</a></li>
        <li><a href="<?= e(url('features.php')) ?>" <?= $nav === 'features' ? 'aria-current="page"' : '' ?>>Features</a></li>
        <li><a href="<?= e(url('how-it-works.php')) ?>" <?= $nav === 'how' ? 'aria-current="page"' : '' ?>>How it works</a></li>
        <li><a href="<?= e(url('strands.php')) ?>" <?= $nav === 'strands' ? 'aria-current="page"' : '' ?>>Strands</a></li>
        <li><a href="<?= e(url('apps.php')) ?>" <?= $nav === 'apps' ? 'aria-current="page"' : '' ?>>Apps</a></li>
        <li><a class="btn btn-gold btn-sm" href="<?= e($portalUrl) ?>" rel="noopener">Portal Login</a></li>
      </ul>
    </nav>
  </div>
</header>
<main id="main">
