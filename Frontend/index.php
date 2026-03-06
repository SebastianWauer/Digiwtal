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
$sharedLogger = dirname(__DIR__) . '/shared/FileLogger.php';
$localLogger  = __DIR__ . '/app/FileLogger.php';
if (is_file($sharedLogger)) {
    require_once $sharedLogger;
} elseif (is_file($localLogger)) {
    require_once $localLogger;
} else {
    // Minimal no-op fallback so the site doesn't crash without a logger
    if (!class_exists('FileLogger')) {
        class FileLogger {
            public static function channel(string $n): static { return new static(); }
            public function error(string $m, array $c = []): void {}
        }
    }
}

// -------------------------------------------------------
// Config from .env
// -------------------------------------------------------
$baseUrl  = (string)(getenv('CMS_API_URL')   ?: '');
$token    = (string)(getenv('CMS_API_TOKEN') ?: '');
$timeout  = (int)(getenv('CMS_TIMEOUT')      ?: 5);
$cacheTtl = 0; // Live-Frontend: Seiteninhalte immer direkt aus dem CMS holen.
$frontendBaseUrl = (string)(getenv('FRONTEND_BASE_URL') ?: '');
$cmsSitemapUrl   = (string)(getenv('CMS_SITEMAP_URL') ?: '');

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
    FileLogger::channel('frontend')->error($message);
}

function renderErrorPage(int $statusCode, string $siteName, string $title, string $message, ?string $hint = null): never
{
    http_response_code($statusCode);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    $pageTitle = $title;
    $fullTitle = $title . ' – ' . $siteName;
    $slug = 'error';
    $navItems = [];
    $blocks = [
        [
            'type' => 'hero',
            'headline' => $title,
            'subtitle' => $message,
        ],
    ];
    if ($hint !== null && trim($hint) !== '') {
        $blocks[] = [
            'type' => 'text',
            'text' => $hint,
        ];
    }

    render('templates/layout.php', [
        'siteName' => $siteName,
        'title' => $fullTitle,
        'pageTitle' => $pageTitle,
        'blocks' => $blocks,
        'navItems' => $navItems,
        'slug' => $slug,
    ]);
    exit;
}

function render404(string $siteName = 'Website'): never {
    renderErrorPage(
        404,
        $siteName,
        '404 – Seite nicht gefunden',
        'Die angeforderte Seite existiert nicht oder wurde verschoben.'
    );
}

function render500(string $siteName = 'Website'): never {
    renderErrorPage(
        500,
        $siteName,
        '500 – Interner Serverfehler',
        'Der Server ist momentan nicht erreichbar. Bitte versuche es später erneut.'
    );
}

// -------------------------------------------------------
// Routing & slug normalization
// -------------------------------------------------------
$homeSlug = 'home';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri  = is_string($path) ? $path : '/';

if ($uri === '/sitemap.xml') {
    try {
        $xml = $client->getSitemapXml($cmsSitemapUrl !== '' ? $cmsSitemapUrl : null);
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        echo $xml;
        exit;
    } catch (CmsApiException $e) {
        frontendDebugLog('[FRONTEND] sitemap fetch failed'
            . ' status=' . $e->statusCode
            . ' api_error=' . $e->apiError
            . ' body=' . substr($e->rawBody, 0, 500));
        http_response_code(502);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Sitemap currently unavailable\n";
        exit;
    }
}

if ($uri === '/robots.txt') {
    $base = trim($frontendBaseUrl);
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = (string)($_SERVER['HTTP_HOST'] ?? '');
        $base   = $host !== '' ? ($scheme . '://' . $host) : '';
    }
    $base = rtrim($base, '/');
    $sitemapLine = $base !== '' ? ($base . '/sitemap.xml') : '/sitemap.xml';

    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    echo "User-agent: *\n";
    echo "Allow: /\n";
    echo 'Sitemap: ' . $sitemapLine . "\n";
    exit;
}

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
    render500('Website');
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
        render500($siteName);
    }
    render500($siteName);
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
$seo = is_array($page['seo'] ?? null) ? $page['seo'] : [];

try {
    render('templates/layout.php', compact('siteName', 'title', 'pageTitle', 'blocks', 'navItems', 'slug', 'seo'));
} catch (Throwable) {
    render500($siteName);
}
