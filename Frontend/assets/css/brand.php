<?php
declare(strict_types=1);

// -------------------------------------------------------
// Minimal .env loader (no Composer required)
// -------------------------------------------------------
(static function (): void {
    $file = dirname(__DIR__, 2) . '/.env';
    if (!is_file($file)) {
        return;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $k = trim(substr($line, 0, $eq));
        $v = trim(substr($line, $eq + 1));
        // Strip surrounding quotes
        if (strlen($v) >= 2 && $v[0] === $v[-1] && ($v[0] === '"' || $v[0] === "'")) {
            $v = substr($v, 1, -1);
        }
        putenv($k . '=' . $v);
        $_ENV[$k] = $v;
    }
})();

require_once dirname(__DIR__, 2) . '/app/CmsApiClient.php';

// -------------------------------------------------------
// Config from .env
// -------------------------------------------------------
$baseUrl  = (string)(getenv('CMS_API_URL')   ?: '');
$token    = (string)(getenv('CMS_API_TOKEN') ?: '');
$timeout  = (int)(getenv('CMS_TIMEOUT')      ?: 5);
$cacheTtl = (int)(getenv('CMS_CACHE_TTL') ?: 300);

// -------------------------------------------------------
// Defaults
// -------------------------------------------------------
$colorPrimary   = '#2563eb';
$colorSecondary = '#64748b';
$colorTertiary  = '#f59e0b';

// -------------------------------------------------------
// Load settings if API available
// -------------------------------------------------------
if ($baseUrl !== '') {
    $client = new CmsApiClient(
        baseUrl:  $baseUrl,
        token:    $token !== '' ? $token : null,
        timeout:  $timeout,
        cacheTtl: $cacheTtl,
        cacheDir: dirname(__DIR__, 2) . '/storage/cache'
    );

    try {
        $settings = $client->getPublicSettings();
        
        // Validate and apply brand_color_primary
        $c = (string)($settings['brand_color_primary'] ?? '');
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $c)) {
            $colorPrimary = strtolower($c);
        }
        
        // Validate and apply brand_color_secondary
        $c = (string)($settings['brand_color_secondary'] ?? '');
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $c)) {
            $colorSecondary = strtolower($c);
        }
        
        // Validate and apply brand_color_tertiary
        $c = (string)($settings['brand_color_tertiary'] ?? '');
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $c)) {
            $colorTertiary = strtolower($c);
        }
    } catch (CmsApiException) {
        // Use defaults
    }
}

// -------------------------------------------------------
// Output CSS
// -------------------------------------------------------
header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=300, stale-while-revalidate=60');
?>:root {
  --color-primary:   <?php echo $colorPrimary; ?>;
  --color-secondary: <?php echo $colorSecondary; ?>;
  --color-tertiary:  <?php echo $colorTertiary; ?>;
}
