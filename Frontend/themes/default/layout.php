<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <?php
  $e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

  $metaTitle  = (string)($seo['meta_title']       ?? '');
  $metaDesc   = (string)($seo['meta_description'] ?? '');
  $robots     = (string)($seo['robots']           ?? '');
  $canonical  = (string)($seo['canonical_url']    ?? '');
  $ogTitle    = (string)($seo['og_title']         ?? '') ?: $metaTitle;
  $ogDesc     = (string)($seo['og_description']   ?? '') ?: $metaDesc;
  $ogImage    = (string)($seo['og_image_url']     ?? '');

  // Nav-Helpers
  $navHref  = fn(array $item): string => !empty($item['is_home']) ? '/' : (string)($item['slug'] ?? '/');
  $navLabel = function(array $item): string {
      $l = (string)($item['nav_label'] ?? '');
      return $l !== '' ? $l : ((string)($item['title'] ?? '') ?: (string)($item['slug'] ?? ''));
  };
  $isActive = function(array $item) use ($page): bool {
      // Home-Item ist aktiv wenn aktuelle Seite ebenfalls is_home=1
      if (!empty($item['is_home']) && !empty($page['is_home'])) return true;
      return (string)($item['slug'] ?? '') === (string)($page['slug'] ?? '');
  };
  ?>

  <title><?= $e($metaTitle ?: (string)($page['frontend_title'] ?: $page['title'] ?? '')) ?></title>

  <?php if ($metaDesc !== ''): ?>
  <meta name="description" content="<?= $e($metaDesc) ?>">
  <?php endif; ?>

  <?php if ($robots !== ''): ?>
  <meta name="robots" content="<?= $e($robots) ?>">
  <?php endif; ?>

  <?php if ($canonical !== ''): ?>
  <link rel="canonical" href="<?= $e($canonical) ?>">
  <?php endif; ?>

  <?php if ($ogTitle !== ''): ?>
  <meta property="og:title" content="<?= $e($ogTitle) ?>">
  <?php endif; ?>

  <?php if ($ogDesc !== ''): ?>
  <meta property="og:description" content="<?= $e($ogDesc) ?>">
  <?php endif; ?>

  <?php if ($ogImage !== ''): ?>
  <meta property="og:image" content="<?= $e($ogImage) ?>">
  <?php endif; ?>

  <link rel="stylesheet" href="/assets/css/theme.css">

</head>
<body>

<?php if (!empty($nav['header'])): ?>
<header>
  <nav>
    <?php foreach ($nav['header'] as $item): ?>
      <a href="<?= $e($navHref($item)) ?>"<?= $isActive($item) ? ' aria-current="page"' : '' ?>><?= $e($navLabel($item)) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
<?php endif; ?>

<?php foreach ($blocks as $block): ?>
  <?= $renderer->renderBlock($block) ?>
<?php endforeach; ?>

<?php if (!empty($nav['footer'])): ?>
<footer>
  <nav>
    <?php foreach ($nav['footer'] as $item): ?>
      <a href="<?= $e($navHref($item)) ?>"><?= $e($navLabel($item)) ?></a>
    <?php endforeach; ?>
  </nav>
</footer>
<?php endif; ?>

</body>
</html>
