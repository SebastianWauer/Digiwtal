<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/brand.php">
    <link rel="stylesheet" href="/assets/css/theme.css">
    <title><?php echo e($title ?? 'Seite'); ?></title>
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
