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

function resolveHomeSlug(CmsApiClient $client, string $fallback = 'home'): string
{
    try {
        $pages = $client->getPages(1);
    } catch (CmsApiException) {
        return $fallback;
    }

    $items = [];
    if (is_array($pages)) {
        if (array_is_list($pages)) {
            $items = $pages;
        } elseif (is_array($pages['items'] ?? null)) {
            $items = $pages['items'];
        }
    }

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $isHomeByUrl = (string)($item['url'] ?? '') === '/';
        $isHomeFlag  = !empty($item['is_home']);
        if (!$isHomeByUrl && !$isHomeFlag) {
            continue;
        }

        $candidate = strtolower(trim((string)($item['slug'] ?? '')));
        if ($candidate !== '' && preg_match('/^[a-z0-9\/-]+$/', $candidate)) {
            return trim($candidate, '/');
        }
    }

    return $fallback;
}

function extractMediaIdFromUrl(string $url): ?int
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return null;
    }

    $path = (string)($parts['path'] ?? '');
    if (!in_array($path, ['/media/file', '/media/thumb'], true)) {
        return null;
    }

    $query = (string)($parts['query'] ?? '');
    if ($query === '') {
        return null;
    }

    parse_str($query, $q);
    $id = (int)($q['id'] ?? 0);
    return $id > 0 ? $id : null;
}

function deriveCmsBaseUrlFromApiBase(string $apiBaseUrl): string
{
    $base = rtrim(trim($apiBaseUrl), '/');
    if ($base === '') {
        return '';
    }

    $patterns = [
        '#/api\.php/api/v1$#i',
        '#/api/v1$#i',
        '#/api\.php$#i',
        '#/api$#i',
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $base) === 1) {
            $base = preg_replace($p, '', $base) ?? $base;
            break;
        }
    }

    return rtrim($base, '/');
}

function absolutizeCmsMediaUrl(string $url, string $cmsBaseUrl): string
{
    $url = trim($url);
    if ($url === '' || $cmsBaseUrl === '') {
        return $url;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return $url;
    }

    if (!empty($parts['scheme']) && !empty($parts['host'])) {
        return $url; // bereits absolut
    }

    $path = (string)($parts['path'] ?? '');
    if (!in_array($path, ['/media/file', '/media/thumb'], true)) {
        return $url;
    }

    $query = (string)($parts['query'] ?? '');
    return rtrim($cmsBaseUrl, '/') . $path . ($query !== '' ? ('?' . $query) : '');
}

function enrichBlockFocusWithMedia(array $blocks, CmsApiClient $client, string $cmsBaseUrl = ''): array
{
    $cache = [];

    $enrich = function ($value) use (&$enrich, &$cache, $client, $cmsBaseUrl) {
        if (!is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        if ($isList) {
            foreach ($value as $i => $item) {
                $value[$i] = $enrich($item);
            }
            return $value;
        }

        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $enrich($v);
                continue;
            }

            if (!is_string($v)) {
                continue;
            }

            $key = (string)$k;

            // 1) URL-basierte Felder: URL ggf. absolutisieren + Fokus anreichern
            if (in_array($key, ['url', 'image_url', 'poster_url'], true)) {
                $value[$key] = absolutizeCmsMediaUrl($v, $cmsBaseUrl);
                $mediaId = extractMediaIdFromUrl($v);
                if ($mediaId === null) {
                    continue;
                }
                if (!array_key_exists($mediaId, $cache)) {
                    try {
                        $m = $client->getMedia($mediaId);
                    } catch (CmsApiException) {
                        $m = [];
                    }
                    $cache[$mediaId] = [
                        'url' => isset($m['url']) ? (string)$m['url'] : '',
                        'x' => isset($m['focus_x']) && $m['focus_x'] !== '' ? (float)$m['focus_x'] : null,
                        'y' => isset($m['focus_y']) && $m['focus_y'] !== '' ? (float)$m['focus_y'] : null,
                    ];
                }
                $fx = $cache[$mediaId]['x'];
                $fy = $cache[$mediaId]['y'];
                if ($fx !== null) {
                    $value[$key . '_focus_x'] = $fx;
                }
                if ($fy !== null) {
                    $value[$key . '_focus_y'] = $fy;
                }
                continue;
            }

            // 2) media_id-basierte Felder: URL-Fallback erzeugen + Fokus anreichern
            if (in_array($key, ['media_id', 'image_media_id', 'poster_media_id'], true)) {
                $mid = (int)$v;
                if ($mid <= 0) {
                    continue;
                }
                if (!array_key_exists($mid, $cache)) {
                    try {
                        $m = $client->getMedia($mid);
                    } catch (CmsApiException) {
                        $m = [];
                    }
                    $cache[$mid] = [
                        'url' => isset($m['url']) ? (string)$m['url'] : '',
                        'x' => isset($m['focus_x']) && $m['focus_x'] !== '' ? (float)$m['focus_x'] : null,
                        'y' => isset($m['focus_y']) && $m['focus_y'] !== '' ? (float)$m['focus_y'] : null,
                    ];
                }
                $mediaUrl = absolutizeCmsMediaUrl((string)($cache[$mid]['url'] ?? ''), $cmsBaseUrl);
                if ($mediaUrl === '') {
                    continue;
                }

                $targetField = $key === 'poster_media_id' ? 'poster_url' : (($key === 'media_id') ? 'image_url' : 'image_url');
                if (!isset($value[$targetField]) || (string)$value[$targetField] === '') {
                    $value[$targetField] = $mediaUrl;
                }
                // Bei Image-Blocks mit media_id zusätzlich "url" setzen.
                if ($key === 'media_id' && (!isset($value['url']) || (string)$value['url'] === '')) {
                    $value['url'] = $mediaUrl;
                }

                $fx = $cache[$mid]['x'];
                $fy = $cache[$mid]['y'];
                if ($fx !== null) {
                    $value[$targetField . '_focus_x'] = $fx;
                    if ($key === 'media_id') {
                        $value['url_focus_x'] = $fx;
                    }
                }
                if ($fy !== null) {
                    $value[$targetField . '_focus_y'] = $fy;
                    if ($key === 'media_id') {
                        $value['url_focus_y'] = $fy;
                    }
                }
            }
        }

        return $value;
    };

    return $enrich($blocks);
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
    $slug = resolveHomeSlug($client, $homeSlug);
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
$cmsBaseUrl = deriveCmsBaseUrlFromApiBase($baseUrl);
$blocks = enrichBlockFocusWithMedia($blocks, $client, $cmsBaseUrl);
$seo = is_array($page['seo'] ?? null) ? $page['seo'] : [];

try {
    render('templates/layout.php', compact('siteName', 'title', 'pageTitle', 'blocks', 'navItems', 'slug', 'seo'));
} catch (Throwable) {
    render500($siteName);
}
