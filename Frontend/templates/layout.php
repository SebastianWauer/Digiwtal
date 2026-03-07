<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $brandVersion = @filemtime(__DIR__ . '/../assets/css/brand.php') ?: time();
    $themeVersion = @filemtime(__DIR__ . '/../assets/css/theme.css') ?: time();
    ?>
    <link rel="stylesheet" href="/assets/css/brand.php?v=<?= (int)$brandVersion ?>">
    <link rel="stylesheet" href="/assets/css/theme.css?v=<?= (int)$themeVersion ?>">
    <style>
      /* Safety override: honor per-block hero overlay even with cached/legacy theme.css */
      .block-hero[style*="background-image"]::before {
        background: rgba(0, 0, 0, var(--hero-overlay-opacity, 0)) !important;
      }
    </style>
    <title><?php echo e($title ?? 'Seite'); ?></title>
    <?php
    $seo = is_array($seo ?? null) ? $seo : [];
    $metaDesc   = (string)($seo['description'] ?? $seo['meta_description'] ?? '');
    $robots     = (string)($seo['robots'] ?? '');
    $canonical  = (string)($seo['canonical_url'] ?? '');
    $ogTitle    = (string)($seo['og_title'] ?? $seo['title'] ?? $seo['meta_title'] ?? '');
    $ogDesc     = (string)($seo['og_description'] ?? $metaDesc);
    $ogImage    = (string)($seo['og_image_url'] ?? '');
    ?>
    <?php if ($metaDesc !== ''): ?>
    <meta name="description" content="<?= htmlspecialchars($metaDesc, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($robots !== ''): ?>
    <meta name="robots" content="<?= htmlspecialchars($robots, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($canonical !== ''): ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($ogTitle !== ''): ?>
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($ogDesc !== ''): ?>
    <meta property="og:description" content="<?= htmlspecialchars($ogDesc, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($ogImage !== ''): ?>
    <meta property="og:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
</head>
<body>
    <?php
    $headerNavItems = array_values(array_filter($navItems ?? [], static function (array $item): bool {
        return (string)($item['area'] ?? 'header') !== 'footer';
    }));
    $footerNavItems = array_values(array_filter($navItems ?? [], static function (array $item): bool {
        return (string)($item['area'] ?? '') === 'footer';
    }));
    render('templates/partials/header.php', compact('siteName', 'headerNavItems', 'slug'));
    ?>
    
    <main>
        <?php render('templates/page.php', compact('pageTitle', 'blocks')); ?>
    </main>
    
    <?php render('templates/partials/footer.php', compact('siteName', 'footerNavItems')); ?>
</body>
</html>
