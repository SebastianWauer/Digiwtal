<?php
declare(strict_types=1);

// public/api.php
require_once __DIR__ . '/../app/bootstrap.php';

if (!function_exists('cms_api_log_path')) {
    function cms_api_log_path(): string
    {
        return dirname(__DIR__) . '/storage/api_error.log';
    }
}

if (!function_exists('cms_api_debug_log')) {
    function cms_api_debug_log(string $message): void
    {
        $dir = dirname(__DIR__) . '/storage';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = '[' . gmdate('c') . '] ' . $message . "\n";
        @file_put_contents(cms_api_log_path(), $line, FILE_APPEND);
        FileLogger::channel('cms-api')->error($message);
    }
}

set_exception_handler(function (Throwable $e): void {
    cms_api_debug_log('[CMS_API] uncaught exception'
        . ' type=' . get_class($e)
        . ' message=' . $e->getMessage()
        . ' file=' . $e->getFile()
        . ' line=' . $e->getLine()
        . ' uri=' . (string)($_SERVER['REQUEST_URI'] ?? ''));

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'error' => 'server_error'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

register_shutdown_function(function (): void {
    $error = error_get_last();
    if (!is_array($error)) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int)($error['type'] ?? 0), $fatalTypes, true)) {
        return;
    }

    cms_api_debug_log('[CMS_API] fatal shutdown'
        . ' type=' . (string)($error['type'] ?? '')
        . ' message=' . (string)($error['message'] ?? '')
        . ' file=' . (string)($error['file'] ?? '')
        . ' line=' . (string)($error['line'] ?? '')
        . ' uri=' . (string)($_SERVER['REQUEST_URI'] ?? ''));
});

if (!function_exists('request_path')) {
    function request_path(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') return '/';
        return $path;
    }
}

/**
 * Public REST API:
 *  - GET /api/v1/pages
 *  - GET /api/v1/pages/{slug}
 *  - GET /api/v1/settings/public
 *  - GET /api/v1/media/{id}
 *
 * Internal / secured:
 *  - GET  /api/health (X-Health-Token header, query token deprecated fallback)
 *  - POST /api/internal/create-backup   (X-Deploy-Token)
 *  - POST /api/internal/run-migrations  (X-Deploy-Token)
 */

// --------------------------------------------------
// Pfad auflösen ("/api.php" abschneiden)
// --------------------------------------------------
$fullPath = request_path();

$prefix = '/api.php';
$path = $fullPath;

if (str_starts_with($path, $prefix)) {
    $path = substr($path, strlen($prefix));
    if ($path === '') $path = '/';
}

// --------------------------------------------------
// Hilfsfunktionen: Settings
// --------------------------------------------------

/**
 * Validiert und normalisiert einen Hex-Farbwert.
 * Akzeptiert #RGB und #RRGGBB, normalisiert auf #rrggbb.
 * Bei ungültigem Wert → $default zurückgeben.
 */
function validate_hex_color(string $color, string $default): string
{
    $color = trim($color);
    if (preg_match('/^#([0-9a-fA-F]{3})$/', $color, $m)) {
        $r = $m[1][0]; $g = $m[1][1]; $b = $m[1][2];
        $color = '#' . $r.$r . $g.$g . $b.$b;
    }
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        return strtolower($color);
    }
    return $default;
}

/**
 * Liest die öffentlichen Brand-Settings aus site_settings.
 * Liefert Defaults, wenn ein Key fehlt oder eine Farbe ungültig ist.
 * Mappt site_title → site_name.
 */
function get_public_settings(PDO $pdo): array
{
    $keys = ['site_title', 'brand_color_primary', 'brand_color_secondary', 'brand_color_tertiary', 'logo_url', 'favicon_media_id', 'cms_logo_light_media_id', 'cms_logo_dark_media_id'];
    $in   = implode(', ', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT `key`, `value` FROM site_settings WHERE `key` IN ({$in})");
    $stmt->execute($keys);
    $rows = $stmt->fetchAll();

    $raw = [];
    foreach (is_array($rows) ? $rows : [] as $r) {
        $raw[(string)($r['key'] ?? '')] = (string)($r['value'] ?? '');
    }

    $faviconId = (int)($raw['favicon_media_id'] ?? 0);
    $faviconUrl = $faviconId > 0 ? ('/media/file?id=' . $faviconId) : null;
    $cmsLogoLightId = (int)($raw['cms_logo_light_media_id'] ?? 0);
    $cmsLogoLightUrl = $cmsLogoLightId > 0 ? ('/media/file?id=' . $cmsLogoLightId) : null;
    $cmsLogoDarkId = (int)($raw['cms_logo_dark_media_id'] ?? 0);
    $cmsLogoDarkUrl = $cmsLogoDarkId > 0 ? ('/media/file?id=' . $cmsLogoDarkId) : null;

    return [
        'site_name'             => (string)($raw['site_title'] ?? ''),
        'brand_color_primary'   => validate_hex_color($raw['brand_color_primary']   ?? '', '#2563eb'),
        'brand_color_secondary' => validate_hex_color($raw['brand_color_secondary'] ?? '', '#64748b'),
        'brand_color_tertiary'  => validate_hex_color($raw['brand_color_tertiary']  ?? '', '#f59e0b'),
        'logo_url'              => (isset($raw['logo_url']) && $raw['logo_url'] !== '') ? $raw['logo_url'] : null,
        'cms_logo_light_url'    => $cmsLogoLightUrl,
        'cms_logo_dark_url'     => $cmsLogoDarkUrl,
        'favicon_url'           => $faviconUrl,
    ];
}

// --------------------------------------------------
// Hilfsfunktionen: Pages
// --------------------------------------------------
function normalize_slug(string $slug): string
{
    $slug = trim($slug);
    if ($slug === '') return '/';
    if ($slug[0] !== '/') $slug = '/' . $slug;
    if (strlen($slug) > 1) $slug = rtrim($slug, '/');
    return $slug;
}

// --------------------------------------------------
// Response Helper
// --------------------------------------------------
function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_client_ip(): string
{
    $xff = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($xff !== '') {
        $first = trim(explode(',', $xff)[0] ?? '');
        if ($first !== '') {
            return $first;
        }
    }

    $realIp = trim((string)($_SERVER['HTTP_X_REAL_IP'] ?? ''));
    if ($realIp !== '') {
        return $realIp;
    }

    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * Simple fixed-window limiter: 60 req/min per IP.
 * Uses APCu if available, otherwise DB table `rate_limits`.
 */
function enforce_api_rate_limit(int $maxRequests = 60, int $windowSeconds = 60): void
{
    $ip = api_client_ip();
    $now = time();
    $windowStart = (int)(floor($now / $windowSeconds) * $windowSeconds);
    $retryAfter = max(1, $windowSeconds - ($now - $windowStart));
    $count = 0;

    $apcuEnabled = function_exists('apcu_enabled') && apcu_enabled();
    if ($apcuEnabled) {
        $key = 'cms_api_rl:' . sha1($ip . ':' . (string)$windowStart);
        $ok = false;
        $count = (int)apcu_inc($key, 1, $ok, $windowSeconds + 5);
        if (!$ok) {
            apcu_store($key, 1, $windowSeconds + 5);
            $count = 1;
        }
    } else {
        try {
            $pdo = db();
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS rate_limits (
                    ip_hash      CHAR(64)      NOT NULL,
                    window_start INT UNSIGNED  NOT NULL,
                    requests     INT UNSIGNED  NOT NULL DEFAULT 0,
                    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (ip_hash, window_start)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $ipHash = hash('sha256', $ip);
            $stmt = $pdo->prepare("
                INSERT INTO rate_limits (ip_hash, window_start, requests, updated_at)
                VALUES (:ip_hash, :window_start, 1, NOW())
                ON DUPLICATE KEY UPDATE requests = requests + 1, updated_at = NOW()
            ");
            $stmt->execute([
                ':ip_hash' => $ipHash,
                ':window_start' => $windowStart,
            ]);

            $sel = $pdo->prepare("
                SELECT requests
                FROM rate_limits
                WHERE ip_hash = :ip_hash
                  AND window_start = :window_start
                LIMIT 1
            ");
            $sel->execute([
                ':ip_hash' => $ipHash,
                ':window_start' => $windowStart,
            ]);
            $count = (int)($sel->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            cms_api_debug_log('[CMS_API] rate_limit_fallback_error ip=' . $ip . ' msg=' . $e->getMessage());
            return; // fail-open
        }
    }

    if ($count > $maxRequests) {
        header('Retry-After: ' . (string)$retryAfter);
        json_response(['ok' => false, 'error' => 'rate_limited'], 429);
    }
}

// Per-request API rate limit (global, before routing)
enforce_api_rate_limit(60, 60);

// --------------------------------------------------
// Manifest Helpers (cms-manifest.json, außerhalb public/)
// --------------------------------------------------
function manifest_path(): string
{
    return dirname(__DIR__) . '/cms-manifest.json';
}

function read_manifest(): ?array
{
    $path = manifest_path();
    if (!is_file($path)) return null;
    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function write_manifest(array $data): void
{
    $path = manifest_path();
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return;
    $tmp = $path . '.tmp.' . getmypid();
    if (@file_put_contents($tmp, $json) === false) return;
    @rename($tmp, $path);
}

// --------------------------------------------------
// Deploy Auth + Backup + Migration Helpers
// --------------------------------------------------

function check_deploy_token(): void
{
    $expected = \App\Core\Env::get('DEPLOY_TOKEN', '');
    $given    = (string)($_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '');
    if ($expected === '' || !hash_equals($expected, $given)) {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }
}

/**
 * Schreibt einen vollständigen MySQL-Dump (alle Tabellen) in $outFile.
 * Arbeitet in Batches von 500 Zeilen – kein OOM bei großen Tabellen.
 */
function cms_dump_sql(PDO $pdo, string $outFile): void
{
    $fh = fopen($outFile, 'w');
    if ($fh === false) {
        throw new RuntimeException("Cannot open dump file for writing.");
    }

    fwrite($fh, "-- DigiWTAL CMS Database Dump\n");
    fwrite($fh, "-- Generated: " . gmdate('c') . "\n\n");
    fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (!is_array($tables)) $tables = [];

    foreach ($tables as $table) {
        $table = (string)$table;
        $tq    = '`' . str_replace('`', '``', $table) . '`';

        $createRow = $pdo->query("SHOW CREATE TABLE {$tq}")->fetch(PDO::FETCH_NUM);
        $createSql = (string)($createRow[1] ?? '');

        fwrite($fh, "-- Table: {$table}\n");
        fwrite($fh, "DROP TABLE IF EXISTS {$tq};\n");
        fwrite($fh, $createSql . ";\n\n");

        $offset = 0;
        $batch  = 500;
        do {
            $stmt = $pdo->query("SELECT * FROM {$tq} LIMIT {$batch} OFFSET {$offset}");
            $rows = $stmt ? $stmt->fetchAll() : [];
            if (!is_array($rows) || empty($rows)) break;

            $colNames = array_map(
                fn($c) => '`' . str_replace('`', '``', $c) . '`',
                array_keys($rows[0])
            );
            $colList = implode(', ', $colNames);

            foreach ($rows as $r) {
                $vals = array_map(
                    fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v),
                    array_values($r)
                );
                fwrite($fh, "INSERT INTO {$tq} ({$colList}) VALUES (" . implode(', ', $vals) . ");\n");
            }

            $offset += $batch;
        } while (count($rows) === $batch);

        fwrite($fh, "\n");
    }

    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);
}

/**
 * Fügt ein Verzeichnis rekursiv in ein ZipArchive ein.
 */
function zip_add_dir(ZipArchive $zip, string $dir, string $zipBase): void
{
    if (!is_dir($dir)) return;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iter as $file) {
        if (!$file->isFile()) continue;
        $zip->addFile((string)$file->getPathname(), $zipBase . '/' . $iter->getSubPathname());
    }
}

// --------------------------------------------------
// API v1 Router (minimal)
// --------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --------------------------------------------------
// Health-Check: GET /api/health (header token; query fallback deprecated)
// --------------------------------------------------
if ($method === 'POST' && $path === '/api/setup/init') {
    $expectedToken = trim((string)\App\Core\Env::get('SETUP_TOKEN', ''));
    $givenToken = trim((string)($_SERVER['HTTP_X_SETUP_TOKEN'] ?? ''));

    if ($expectedToken === '') {
        json_response(['ok' => false, 'error' => 'setup_token_not_configured'], 503);
    }
    if ($givenToken === '' || !hash_equals($expectedToken, $givenToken)) {
        json_response(['ok' => false, 'error' => 'forbidden'], 403);
    }

    try {
        if (!\App\Core\Setup::allowSetupRequest(db())) {
            json_response(['ok' => false, 'error' => 'setup_not_allowed'], 409);
        }
    } catch (Throwable $e) {
        json_response(['ok' => false, 'error' => 'db_not_available'], 503);
    }

    json_response([
        'ok' => true,
        'setup_allowed' => true,
        'csrf_token' => admin_csrf_token(),
    ]);
}

if ($method === 'GET' && $path === '/api/health') {
    $expectedToken = \App\Core\Env::get('HEALTH_TOKEN', '');
    $headerToken   = trim((string)($_SERVER['HTTP_X_HEALTH_TOKEN'] ?? ''));
    $queryToken    = trim((string)($_GET['token'] ?? ''));
    $givenToken    = $headerToken !== '' ? $headerToken : $queryToken;

    if ($headerToken === '' && $queryToken !== '') {
        cms_api_debug_log('[CMS_API] deprecated health token via query parameter used');
    }

    if ($expectedToken === '' || !hash_equals($expectedToken, $givenToken)) {
        json_response(['error' => 'Forbidden'], 403);
    }

    // Manifest laden (optional – fehlt beim ersten Deploy noch)
    $manifest = read_manifest();

    // CMS-Version: Manifest > config/version.php > Fallback
    $versionCfg = (static function () {
        $f = dirname(__DIR__) . '/config/version.php';
        if (!is_file($f)) return [];
        $v = include $f;
        return is_array($v) ? $v : [];
    })();
    $cmsVersion = (string)(
        $manifest['cms_version'] ??
        $versionCfg['cms_version'] ??
        '2.1.2'
    );

    // Frontend-Version: Manifest > null
    $frontendVersion = null;
    if ($manifest !== null && ($manifest['frontend_version'] ?? null) !== null) {
        $frontendVersion = (string)$manifest['frontend_version'];
    }

    // Modules: installed_modules aus Manifest > leeres Objekt
    $modules = (object)[];
    if ($manifest !== null && isset($manifest['installed_modules']) && is_array($manifest['installed_modules'])) {
        $modules = (object)$manifest['installed_modules'];
    }

    // DB-Verbindung prüfen (SELECT 1)
    $dbOk      = false;
    $healthPdo = null;
    try {
        $healthPdo = db();
        $healthPdo->query('SELECT 1');
        $dbOk = true;
    } catch (Throwable $e) {
        // $dbOk bleibt false
    }

    // Storage beschreibbar?
    $storageDir      = dirname(__DIR__) . '/storage';
    $storageWritable = false;
    if (!is_dir($storageDir)) {
        $storageWritable = (bool)@mkdir($storageDir, 0755, true);
    } else {
        $storageWritable = is_writable($storageDir);
    }

    // Letzten Migrations-Eintrag ermitteln
    $lastMigration = null;
    if ($dbOk && $healthPdo !== null) {
        try {
            $mStmt = $healthPdo->query(
                "SELECT id FROM schema_migrations ORDER BY applied_at DESC, id DESC LIMIT 1"
            );
            $mRow = $mStmt ? $mStmt->fetch() : false;
            if (is_array($mRow) && isset($mRow['id'])) {
                $lastMigration = (string)$mRow['id'];
            }
        } catch (Throwable $e) {
            // Tabelle existiert nicht → null
        }
    }

    // Gesamtstatus
    $status = ($dbOk && $storageWritable) ? 'healthy' : 'degraded';

    // Manifest aktualisieren (nur wenn es bereits existiert)
    if ($manifest !== null) {
        $manifest['status']         = $status;
        $manifest['last_checked_at'] = gmdate('c');
        write_manifest($manifest);
    }

    json_response([
        'status'           => $status,
        'cms_version'      => $cmsVersion,
        'frontend_version' => $frontendVersion,
        'php_version'      => PHP_VERSION,
        'db_connection'    => $dbOk,
        'storage_writable' => $storageWritable,
        'last_migration'   => $lastMigration,
        'modules'          => $modules,
        'checked_at'       => gmdate('c'),
    ]);
}

// --------------------------------------------------
// Internal Deploy API: /api/internal/...
// Auth: X-Deploy-Token header
// --------------------------------------------------
if (str_starts_with($path, '/api/internal/')) {
    check_deploy_token();

    if ($method !== 'POST') {
        json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }

    // --- POST /api/internal/create-backup ---
    if ($path === '/api/internal/create-backup') {
        if (!class_exists('ZipArchive')) {
            json_response(['ok' => false, 'error' => 'backup_failed', 'message' => 'ZipArchive extension not available.'], 500);
        }

        $root      = dirname(__DIR__);
        $backupDir = rtrim((string)(\App\Core\Env::get('BACKUP_DIR', '') ?: $root . '/storage/backups'), '/');
        $filename  = 'cms-backup-' . gmdate('Ymd-His') . '.zip';
        $filepath  = $backupDir . '/' . $filename;
        $tmpSql    = null;

        try {
            if (!is_dir($backupDir) && !@mkdir($backupDir, 0755, true)) {
                throw new RuntimeException('Cannot create backup dir: ' . $backupDir);
            }

            $pdo = db();

            $tmpSql = tempnam(sys_get_temp_dir(), 'cms_dump_');
            if ($tmpSql === false) throw new RuntimeException('Cannot create temp file.');
            cms_dump_sql($pdo, $tmpSql);

            $zip = new ZipArchive();
            $res = $zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($res !== true) {
                throw new RuntimeException('Cannot create ZIP (ZipArchive error ' . $res . ').');
            }

            $zip->addFile($tmpSql, 'database.sql');

            foreach (['uploads', 'data', 'media'] as $d) {
                zip_add_dir($zip, $root . '/storage/' . $d, 'storage/' . $d);
            }

            $zip->close();
            @unlink($tmpSql);
            $tmpSql = null;

            // Manifest aktualisieren (nur wenn bereits vorhanden)
            $manifest = read_manifest();
            if ($manifest !== null) {
                $manifest['last_backup_at'] = gmdate('c');
                write_manifest($manifest);
            }

            json_response([
                'ok'          => true,
                'backup_file' => $filename,
                'size_bytes'  => (int)@filesize($filepath),
                'created_at'  => gmdate('c'),
            ]);
        } catch (Throwable $e) {
            if ($tmpSql !== null) @unlink($tmpSql);
            if (is_file($filepath)) @unlink($filepath);
            json_response(['ok' => false, 'error' => 'backup_failed', 'message' => $e->getMessage()], 500);
        }
    }

    // --- POST /api/internal/run-migrations ---
    if ($path === '/api/internal/run-migrations') {
        try {
            $pdo = db();

            // schema_migrations sicherstellen (konsistent mit db.php)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS schema_migrations (
                    id VARCHAR(190) NOT NULL,
                    applied_at DATETIME NOT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Bereits angewendete Migrationen laden
            $applied = [];
            $rows    = $pdo->query("SELECT id FROM schema_migrations")->fetchAll();
            foreach (is_array($rows) ? $rows : [] as $r) {
                $applied[(string)($r['id'] ?? '')] = true;
            }

            // Ausstehende Migrationen anwenden (nutzt db_apply_migration aus db.php)
            $ran = 0;
            foreach (db_migration_files() as $file) {
                $id = basename($file);
                if (isset($applied[$id])) continue;

                $sql = file_get_contents($file);
                if (!is_string($sql) || trim($sql) === '') continue;

                db_apply_migration($pdo, $id, $sql);
                $ran++;
            }

            // Letzten Migrations-Eintrag auslesen
            $lastRow  = $pdo->query("SELECT id FROM schema_migrations ORDER BY applied_at DESC, id DESC LIMIT 1")->fetch();
            $lastId   = is_array($lastRow) ? (string)($lastRow['id'] ?? '') : '';
            $lastName = $lastId !== '' ? basename($lastId, '.sql') : null;

            // Manifest aktualisieren (nur wenn bereits vorhanden)
            $manifest = read_manifest();
            if ($manifest !== null && $lastName !== null) {
                $manifest['last_migration']    = $lastName;
                $manifest['last_migration_at'] = gmdate('c');
                write_manifest($manifest);
            }

            json_response([
                'ok'             => true,
                'applied'        => $ran,
                'last_migration' => $lastName,
                'ran_at'         => gmdate('c'),
            ]);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'error' => 'migration_failed', 'message' => $e->getMessage()], 500);
        }
    }

    json_response(['ok' => false, 'error' => 'not_found'], 404);
}

if (!str_starts_with($path, '/api/v1/')) {
    json_response(['error' => 'Not Found'], 404);
}

$sub = substr($path, strlen('/api/v1'));

try {
    $pdo = db();
} catch (Throwable $e) {
    json_response(['error' => 'DB not available'], 500);
}

// --- /settings/public ---
if ($sub === '/settings/public') {
    if ($method !== 'GET') {
        json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }
    header('Cache-Control: no-store');
    json_response(get_public_settings($pdo));
}

// --- GET /api/v1/pages (list of all public pages) ---
if ($sub === '/pages' && !isset($_GET['slug'])) {
    if ($method !== 'GET') {
        json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }
    $stmt = $pdo->query("
        SELECT id, slug, title, nav_label, nav_visible, nav_order, is_home, updated_at
        FROM pages
        WHERE is_deleted = 0 AND status = 'live'
        ORDER BY nav_order ASC, title ASC
    ");
    $rows = $stmt ? $stmt->fetchAll() : [];
    if (!is_array($rows)) $rows = [];

    $out = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $isHome    = (bool)($r['is_home'] ?? false);
        $slugRaw   = ltrim((string)($r['slug'] ?? ''), '/');
        $titleVal  = (($r['nav_label'] ?? '') !== '') ? (string)$r['nav_label'] : (string)($r['title'] ?? '');
        $updAt     = (string)($r['updated_at'] ?? '');
        $out[] = [
            'id'         => (int)($r['id'] ?? 0),
            'slug'       => $slugRaw,
            'title'      => $titleVal,
            'url'        => $isHome ? '/' : ('/' . $slugRaw),
            'in_nav'     => (bool)($r['nav_visible'] ?? false),
            'nav_order'  => (int)($r['nav_order'] ?? 0),
            'updated_at' => $updAt !== '' ? gmdate('c', (int)strtotime($updAt)) : null,
        ];
    }
    json_response($out);
}

// --- /pages?slug=/ ---
if ($method === 'GET' && $sub === '/pages') {
    $slug = normalize_slug((string)($_GET['slug'] ?? '/'));

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.slug,
            p.title,
            p.frontend_title,
            p.subtitle,
            p.status,
            sm.meta_title,
            sm.meta_description,
            p.content_json
        FROM pages p
        LEFT JOIN seo_meta sm
            ON sm.entity_type = 'page'
           AND sm.entity_id = p.id
        WHERE p.slug = :s
          AND p.is_deleted = 0
          AND p.status = 'live'
        LIMIT 1
    ");
    $stmt->execute([':s' => $slug]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        json_response(['error' => 'Not Found'], 404);
    }

    $content = null;
    if (isset($row['content_json']) && is_string($row['content_json']) && trim($row['content_json']) !== '') {
        $decoded = json_decode($row['content_json'], true);
        $content = is_array($decoded) ? $decoded : null;
    }

    json_response([
        'page' => [
            'id' => (int)$row['id'],
            'slug' => (string)$row['slug'],
            'title' => (string)($row['title'] ?? ''),
            'frontend_title' => (string)($row['frontend_title'] ?? ''),
            'subtitle' => (string)($row['subtitle'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'meta_title' => (string)($row['meta_title'] ?? ''),
            'meta_description' => (string)($row['meta_description'] ?? ''),
            'content' => $content,
        ]
    ], 200);
}

// --- /navigation ---
if ($method === 'GET' && $sub === '/navigation') {
    $stmt = $pdo->query("
        SELECT id, nav_label, slug, nav_area, nav_order
        FROM pages
        WHERE is_deleted = 0 AND status = 'live' AND nav_visible = 1
        ORDER BY nav_order ASC, id ASC
    ");
    $rows = $stmt ? $stmt->fetchAll() : [];
    if (!is_array($rows)) $rows = [];

    $out = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $out[] = [
            'id'         => (int)($r['id'] ?? 0),
            'title'      => (string)($r['nav_label'] ?? ''),
            'url'        => (string)($r['slug'] ?? ''),
            'area'       => (string)($r['nav_area'] ?? ''),
            'sort_order' => (int)($r['nav_order'] ?? 0),
        ];
    }

    json_response(['items' => $out], 200);
}

// --- GET /api/v1/pages/{slug} (single page with blocks + SEO, path param) ---
if (preg_match('/^\/pages\/(.+)$/', $sub, $m)) {
    if ($method !== 'GET') {
        json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }
    $slugRaw = $m[1];
    if (!preg_match('/^[a-z0-9][a-z0-9\/-]*$/', $slugRaw)) {
        json_response(['ok' => false, 'error' => 'bad_request'], 400);
    }
    $slugDb = normalize_slug($slugRaw);

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.slug,
            p.title,
            p.frontend_title,
            p.subtitle,
            sm.meta_title,
            sm.meta_description,
            p.content_json,
            p.updated_at
        FROM pages p
        LEFT JOIN seo_meta sm
            ON sm.entity_type = 'page'
           AND sm.entity_id = p.id
        WHERE p.slug = :s
          AND p.is_deleted = 0
          AND p.status = 'live'
        LIMIT 1
    ");
    $stmt->execute([':s' => $slugDb]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        json_response(['ok' => false, 'error' => 'not_found'], 404);
    }

    $blocks = [];
    $cj = trim((string)($row['content_json'] ?? ''));
    if ($cj !== '') {
        $decoded = json_decode($cj, true);
        if (is_array($decoded)) {
            $b      = $decoded['blocks'] ?? $decoded;
            $blocks = is_array($b) ? $b : [];
        }
    }

    $updAt = (string)($row['updated_at'] ?? '');
    json_response([
        'id'         => (int)$row['id'],
        'slug'       => ltrim((string)$row['slug'], '/'),
        'title'      => (string)($row['title'] ?? ''),
        'frontend_title' => (string)($row['frontend_title'] ?? ''),
        'subtitle'   => (string)($row['subtitle'] ?? ''),
        'seo'        => [
            'title'       => (string)($row['meta_title'] ?? ''),
            'description' => (string)($row['meta_description'] ?? ''),
        ],
        'blocks'     => $blocks,
        'updated_at' => $updAt !== '' ? gmdate('c', (int)strtotime($updAt)) : null,
    ]);
}

// --- GET /api/v1/media/{id} (media metadata) ---
if (preg_match('/^\/media\/(.+)$/', $sub, $m)) {
    if ($method !== 'GET') {
        json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }
    if (!preg_match('/^\d+$/', $m[1]) || (int)$m[1] <= 0) {
        json_response(['ok' => false, 'error' => 'bad_request'], 400);
    }
    $mediaId = (int)$m[1];

    $stmt = $pdo->prepare(
        "SELECT id, display_filename, mime, size_bytes, width, height, alt_text, focus_x, focus_y
         FROM media_items
         WHERE id = :id AND is_deleted = 0"
    );
    $stmt->execute([':id' => $mediaId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        json_response(['ok' => false, 'error' => 'not_found'], 404);
    }

    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = (string)($_SERVER['HTTP_HOST'] ?? '');
    $baseUrl = $host !== '' ? "{$scheme}://{$host}" : '';

    json_response([
        'id'         => (int)$row['id'],
        'url'        => $baseUrl . '/media/file?id=' . $mediaId,
        'mime'       => (string)($row['mime'] ?? ''),
        'size_bytes' => (int)($row['size_bytes'] ?? 0),
        'width'      => ($row['width'] !== null && $row['width'] !== '') ? (int)$row['width'] : null,
        'height'     => ($row['height'] !== null && $row['height'] !== '') ? (int)$row['height'] : null,
        'alt'        => (string)($row['alt_text'] ?? ''),
        'focus_x'    => ($row['focus_x'] !== null && $row['focus_x'] !== '') ? (float)$row['focus_x'] : null,
        'focus_y'    => ($row['focus_y'] !== null && $row['focus_y'] !== '') ? (float)$row['focus_y'] : null,
    ]);
}

json_response(['ok' => false, 'error' => 'not_found'], 404);
