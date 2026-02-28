<?php
declare(strict_types=1);

// -------------------------------------------------------
// Minimal .env loader (no Composer required)
// -------------------------------------------------------
(static function (): void {
    $file = __DIR__ . '/.env';
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

require_once __DIR__ . '/app/CmsApiClient.php';
require_once __DIR__ . '/app/view.php';

// -------------------------------------------------------
// Config from .env
// -------------------------------------------------------
$baseUrl  = (string)(getenv('CMS_API_URL')   ?: '');
$token    = (string)(getenv('CMS_API_TOKEN') ?: '');
$timeout  = (int)(getenv('CMS_TIMEOUT')      ?: 5);
$cacheTtl = (int)(getenv('CMS_CACHE_TTL')    ?: 0);

if ($baseUrl === '') {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo "ERROR: CMS_API_URL not set in .env\n";
    exit(1);
}

$client = new CmsApiClient(
    baseUrl:  $baseUrl,
    token:    $token !== '' ? $token : null,
    timeout:  $timeout,
    cacheTtl: $cacheTtl,
    cacheDir: __DIR__ . '/storage/cache'
);

// -------------------------------------------------------
// Helper functions
// -------------------------------------------------------
function frontendLogPath(): string
{
    return __DIR__ . '/storage/frontend_error.log';
}

function frontendDebugLog(string $message): void
{
    $dir = __DIR__ . '/storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $line = '[' . gmdate('c') . '] ' . $message . "\n";
    @file_put_contents(frontendLogPath(), $line, FILE_APPEND);
    error_log($message);
}

function render404(string $siteName = 'Website'): never {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    ?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 – <?php echo e($siteName); ?></title>
</head>
<body>
    <header>
        <h1><a href="/"><?php echo e($siteName); ?></a></h1>
    </header>
    <main>
        <h1>404 – Seite nicht gefunden</h1>
        <p>Die angeforderte Seite existiert nicht.</p>
    </main>
    <footer>
        <p>&copy; <?php echo gmdate('Y'); ?> <?php echo e($siteName); ?></p>
    </footer>
</body>
</html><?php
    exit;
}

function render502(string $siteName = 'Website'): never {
    http_response_code(502);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    $logPath = frontendLogPath();
    $logHint = is_file($logPath) ? basename(dirname($logPath)) . '/' . basename($logPath) : '';
    ?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>502 – <?php echo e($siteName); ?></title>
</head>
<body>
    <header>
        <h1><a href="/"><?php echo e($siteName); ?></a></h1>
    </header>
    <main>
        <h1>502 – Bad Gateway</h1>
        <p>Der Server ist momentan nicht erreichbar. Bitte versuchen Sie es später erneut.</p>
        <?php if ($logHint !== ''): ?>
            <p style="color:#64748b;font-size:14px;margin-top:12px;">Debug-Log: <?php echo e($logHint); ?></p>
        <?php endif; ?>
    </main>
    <footer>
        <p>&copy; <?php echo gmdate('Y'); ?> <?php echo e($siteName); ?></p>
    </footer>
</body>
</html><?php
    exit;
}

// -------------------------------------------------------
// Routing & slug normalization
// -------------------------------------------------------
$homeSlug = 'home';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri  = is_string($path) ? $path : '/';
$slug = trim($uri, '/');

if ($slug === '') {
    $slug = $homeSlug;
} else {
    $slug = strtolower($slug);
    $slug = preg_replace('#/+#', '/', $slug);
    $slug = trim((string)$slug, '/');
}

// Validate slug format
if (!preg_match('/^[a-z0-9\/-]+$/', $slug)) {
    $siteName = 'Website';
    try {
        $settings = $client->getPublicSettings();
        $siteName = (string)($settings['site_name'] ?? 'Website');
    } catch (CmsApiException) {
        // Use default
    }
    render404($siteName);
}

// -------------------------------------------------------
// Load settings
// -------------------------------------------------------
try {
    $settings = $client->getPublicSettings();
    $siteName = (string)($settings['site_name'] ?? 'Website');
} catch (CmsApiException $e) {
    frontendDebugLog('[FRONTEND] settings/public failed'
        . ' base_url=' . $baseUrl
        . ' status=' . $e->statusCode
        . ' api_error=' . $e->apiError
        . ' body=' . substr($e->rawBody, 0, 500));
    render502('Website');
}

// -------------------------------------------------------
// Load navigation items
// -------------------------------------------------------
$navItems = [];
try {
    $navResult = $client->getNavigation();
    $navItems = $navResult['items'] ?? [];
} catch (CmsApiException) {
    // Navigation failure should not break the page
    $navItems = [];
}

// -------------------------------------------------------
// Load page
// -------------------------------------------------------
try {
    $page = $client->getPage($slug);
} catch (CmsApiException $e) {
    frontendDebugLog('[FRONTEND] page fetch failed'
        . ' base_url=' . $baseUrl
        . ' slug=' . $slug
        . ' status=' . $e->statusCode
        . ' api_error=' . $e->apiError
        . ' body=' . substr($e->rawBody, 0, 500));
    if ($e->statusCode === 404) {
        render404($siteName);
    }
    if (in_array($e->apiError, ['network_error', 'invalid_json'], true) 
        || $e->statusCode >= 500 
        || $e->statusCode === 0) {
        render502($siteName);
    }
    render502($siteName);
}

// -------------------------------------------------------
// Render HTML
// -------------------------------------------------------
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$pageTitle = (string)($page['title'] ?? 'Seite');
$title = $pageTitle . ' – ' . $siteName;
$blocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : [];

try {
    render('templates/layout.php', compact('siteName', 'title', 'pageTitle', 'blocks', 'navItems', 'slug'));
} catch (Throwable) {
    render502($siteName);
}
