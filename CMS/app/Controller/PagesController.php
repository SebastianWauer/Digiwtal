<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repositories\PageRepositoryDb;
use App\Repositories\MediaRepositoryDb;
use App\Repositories\MediaUsageRepositoryDb;
use App\Repositories\SeoRepositoryDb;
use App\Repositories\SiteSettingsRepositoryDb;
use App\Services\PageService;
use App\Services\MediaUsageService;
use App\Services\SeoService;
use App\Setup\EnsureDefaultPages;

final class PagesController
{
    public function preview(): void
    {
        $user = \admin_require_perm('pages.view');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        [$rawUser, $rawTheme, $_pdo, $repo] = $this->deps($user);
        unset($rawUser, $rawTheme);

        $rawBody = (string)file_get_contents('php://input');
        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            $data = $_POST;
        }
        if (!is_array($data)) {
            $data = [];
        }

        $pageId = (int)($data['id'] ?? 0);
        $page = $pageId > 0 ? $repo->findById($pageId) : null;
        if (!is_array($page)) {
            $page = [
                'id' => 0,
                'slug' => (string)($data['slug'] ?? ''),
                'title' => (string)($data['title'] ?? ''),
                'frontend_title' => (string)($data['frontend_title'] ?? ''),
                'subtitle' => (string)($data['subtitle'] ?? ''),
                'is_home' => !empty($data['is_home']) ? 1 : 0,
            ];
        }

        $page['slug'] = (string)($data['slug'] ?? $page['slug'] ?? '');
        $page['title'] = (string)($data['title'] ?? $page['title'] ?? '');
        $page['frontend_title'] = (string)($data['frontend_title'] ?? $page['frontend_title'] ?? '');
        $page['subtitle'] = (string)($data['subtitle'] ?? $page['subtitle'] ?? '');
        $page['is_home'] = !empty($data['is_home']) ? 1 : (int)($page['is_home'] ?? 0);

        $contentJson = (string)($data['content_json'] ?? '');
        if ($contentJson === '') {
            $contentJson = (string)($page['content_json'] ?? '{"blocks":[]}');
        }

        $decoded = json_decode($contentJson, true);
        $blocks = [];
        if (is_array($decoded)) {
            if (array_is_list($decoded)) {
                $blocks = $decoded;
            } elseif (isset($decoded['blocks']) && is_array($decoded['blocks'])) {
                $blocks = $decoded['blocks'];
            }
        }
        try {
            $blocks = $this->enrichBlocksWithFocusFromDb($_pdo, $blocks);
        } catch (\Throwable $e) {
            // Preview darf nie komplett ausfallen: bei Fehlern ohne Focus-Enrichment weiter rendern.
            if (\class_exists('FileLogger')) {
                \FileLogger::channel('cms')->error('preview_enrich_blocks_failed', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $seo = [
            'meta_title'       => (string)($data['seo_meta_title'] ?? ''),
            'meta_description' => (string)($data['seo_meta_description'] ?? ''),
            'robots'           => (string)($data['seo_robots'] ?? ''),
            'canonical_url'    => (string)($data['seo_canonical_url'] ?? ''),
            'og_title'         => (string)($data['seo_og_title'] ?? ''),
            'og_description'   => (string)($data['seo_og_description'] ?? ''),
            'og_image_url'     => (string)($data['seo_og_image_url'] ?? ''),
        ];

        $settingsRepo = new SiteSettingsRepositoryDb($_pdo);
        $settings = $settingsRepo->getAll();
        $siteName = (string)($settings['site_name'] ?? 'Website');
        $pageTitle = (string)($page['title'] ?? 'Seite');
        $title = $pageTitle . ' – ' . $siteName;
        $slug = trim((string)($page['slug'] ?? ''), '/');
        if ($slug === '') {
            $slug = 'home';
        }

        $navItems = [];
        foreach ($repo->listPublicNav('header') as $item) {
            if (!is_array($item)) {
                continue;
            }
            $item['area'] = 'header';
            $navItems[] = $item;
        }
        foreach ($repo->listPublicNav('footer') as $item) {
            if (!is_array($item)) {
                continue;
            }
            $item['area'] = 'footer';
            $navItems[] = $item;
        }

        $frontendView = dirname(__DIR__, 3) . '/Frontend/app/view.php';
        if (!is_file($frontendView)) {
            http_response_code(500);
            echo 'Frontend-View-Helper nicht gefunden.';
            return;
        }

        require_once $frontendView;
        if (!function_exists('render')) {
            http_response_code(500);
            echo 'Frontend-Renderer nicht verfügbar.';
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex, nofollow');

        ob_start();
        \render('templates/layout.php', compact('siteName', 'title', 'pageTitle', 'blocks', 'navItems', 'slug', 'seo'));
        $html = (string)ob_get_clean();

        $frontendBase = rtrim((string)\App\Core\Env::get('FRONTEND_BASE_URL', ''), '/');
        if ($frontendBase !== '') {
            $baseTag = '<base href="' . htmlspecialchars($frontendBase . '/', ENT_QUOTES, 'UTF-8') . '">';
            $patched = preg_replace('/<head(\s*)>/i', '<head$1>' . $baseTag, $html, 1);
            if (is_string($patched) && $patched !== '') {
                $html = $patched;
            }
        }

        echo $html;
    }

    private function parseMediaIdFromUrl(string $url): ?int
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

    private function cmsBaseUrlFromRequest(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return '';
        }
        return $scheme . '://' . $host;
    }

    private function absolutizeCmsMediaUrl(string $url, string $cmsBaseUrl): string
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
            return $url;
        }
        $path = (string)($parts['path'] ?? '');
        if (!in_array($path, ['/media/file', '/media/thumb'], true)) {
            return $url;
        }
        $query = (string)($parts['query'] ?? '');
        return rtrim($cmsBaseUrl, '/') . $path . ($query !== '' ? ('?' . $query) : '');
    }

    private function enrichBlocksWithFocusFromDb(\PDO $pdo, array $blocks): array
    {
        $cmsBaseUrl = $this->cmsBaseUrlFromRequest();
        $mediaIds = [];
        $collect = function ($value) use (&$collect, &$mediaIds): void {
            if (!is_array($value)) {
                return;
            }
            foreach ($value as $k => $v) {
                if (is_array($v)) {
                    $collect($v);
                    continue;
                }
                $key = (string)$k;
                if (in_array($key, ['media_id', 'image_media_id', 'poster_media_id'], true)) {
                    $mid = (int)$v;
                    if ($mid > 0) {
                        $mediaIds[$mid] = true;
                    }
                    continue;
                }
                if (!is_string($v)) {
                    continue;
                }
                if (!in_array($key, ['url', 'image_url', 'poster_url'], true)) {
                    continue;
                }
                $parts = parse_url(trim($v));
                if (!is_array($parts)) {
                    continue;
                }
                $path = (string)($parts['path'] ?? '');
                if (!in_array($path, ['/media/file', '/media/thumb'], true)) {
                    continue;
                }
                parse_str((string)($parts['query'] ?? ''), $q);
                $id = (int)($q['id'] ?? 0);
                if ($id > 0) {
                    $mediaIds[$id] = true;
                }
            }
        };
        $collect($blocks);

        if ($mediaIds === []) {
            return $blocks;
        }

        $ids = array_keys($mediaIds);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT id, focus_x, focus_y FROM media_items WHERE is_deleted = 0 AND id IN ($ph)");
        foreach ($ids as $i => $id) {
            $st->bindValue($i + 1, (int)$id, \PDO::PARAM_INT);
        }
        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        $focusMap = [];
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $id = (int)($r['id'] ?? 0);
            if ($id <= 0) continue;
            $focusMap[$id] = [
                'x' => ($r['focus_x'] !== null && $r['focus_x'] !== '') ? (float)$r['focus_x'] : null,
                'y' => ($r['focus_y'] !== null && $r['focus_y'] !== '') ? (float)$r['focus_y'] : null,
            ];
        }

        $apply = function ($value) use (&$apply, $focusMap) {
            if (!is_array($value)) {
                return $value;
            }

            if (array_is_list($value)) {
                foreach ($value as $i => $item) {
                    $value[$i] = $apply($item);
                }
                return $value;
            }

            foreach ($value as $k => $v) {
                if (is_array($v)) {
                    $value[$k] = $apply($v);
                    continue;
                }
                $key = (string)$k;

                if (in_array($key, ['url', 'image_url', 'poster_url'], true) && is_string($v)) {
                    $value[$key] = $this->absolutizeCmsMediaUrl($v, $cmsBaseUrl);
                }

                if (in_array($key, ['media_id', 'image_media_id', 'poster_media_id'], true)) {
                    $mid = (int)$v;
                    if ($mid > 0 && isset($focusMap[$mid])) {
                        $targetField = $key === 'poster_media_id' ? 'poster_url' : 'image_url';
                        if (!isset($value[$targetField]) || trim((string)$value[$targetField]) === '') {
                            $value[$targetField] = $this->absolutizeCmsMediaUrl('/media/file?id=' . $mid, $cmsBaseUrl);
                        }
                        if ($key === 'media_id' && (!isset($value['url']) || trim((string)$value['url']) === '')) {
                            $value['url'] = $this->absolutizeCmsMediaUrl('/media/file?id=' . $mid, $cmsBaseUrl);
                        }

                        if ($focusMap[$mid]['x'] !== null) {
                            $value[$targetField . '_focus_x'] = $focusMap[$mid]['x'];
                            if ($key === 'media_id') {
                                $value['url_focus_x'] = $focusMap[$mid]['x'];
                            }
                        }
                        if ($focusMap[$mid]['y'] !== null) {
                            $value[$targetField . '_focus_y'] = $focusMap[$mid]['y'];
                            if ($key === 'media_id') {
                                $value['url_focus_y'] = $focusMap[$mid]['y'];
                            }
                        }
                    }
                    continue;
                }

                if (!is_string($v)) {
                    continue;
                }
                if (!in_array($key, ['url', 'image_url', 'poster_url'], true)) {
                    continue;
                }
                $mediaId = $this->parseMediaIdFromUrl($v);
                if ($mediaId === null || !isset($focusMap[$mediaId])) {
                    continue;
                }
                if ($focusMap[$mediaId]['x'] !== null) {
                    $value[$key . '_focus_x'] = $focusMap[$mediaId]['x'];
                }
                if ($focusMap[$mediaId]['y'] !== null) {
                    $value[$key . '_focus_y'] = $focusMap[$mediaId]['y'];
                }
            }
            return $value;
        };

        return $apply($blocks);
    }

    /** @return array{0:array,1:string,2:\PDO,3:PageRepositoryDb,4:PageService} */
    private function deps(array $user): array
    {
        $theme = \admin_theme_for_user((int)$user['id']);
        $pdo = \admin_pdo();

        EnsureDefaultPages::run($pdo);

        $repo = new PageRepositoryDb($pdo);
        $svc  = new PageService($repo);

        return [$user, $theme, $pdo, $repo, $svc];
    }

    public function index(): void
    {
        $user = \admin_require_perm('pages.view');
        [$user, $theme, $_pdo, $repo] = $this->deps($user);

        $rows = $repo->listActive();
        $deletedCount = $repo->countDeleted();
        $flash = null;

        \admin_layout_begin([
            'title'    => 'Seiten',
            'theme'    => $theme,
            'active'   => 'pages',
            'user'     => $user,
            'next'     => '/pages',
            'pageCss'  => 'pages-list',
            'headline' => 'Seiten',
            'subtitle' => 'Seiten anlegen, bearbeiten oder löschen (Soft-Delete).',
        ]);

        require __DIR__ . '/../Views/pages_list.php';
        \admin_layout_end();
    }

    public function deleted(): void
    {
        $user = \admin_require_perm('pages.view');
        [$user, $theme, $_pdo, $repo] = $this->deps($user);

        $rows = $repo->listDeleted();
        $deletedCount = $repo->countDeleted();
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        \admin_layout_begin([
            'title'    => 'Gelöschte Seiten',
            'theme'    => $theme,
            'active'   => 'pages',
            'user'     => $user,
            'next'     => '/pages',
            'pageCss'  => 'pages-list',
            'headline' => 'Gelöschte Seiten',
            'subtitle' => 'Hier kannst du gelöschte Seiten wiederherstellen.',
        ]);

        require __DIR__ . '/../Views/pages_deleted.php';
        \admin_layout_end();
    }

    public function edit(): void
    {
        $user = \admin_require_perm('pages.view');
        [$user, $theme, $_pdo, $repo, $svc] = $this->deps($user);

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        // Neue Seite? => zusätzlich pages.create benötigen
        if ($id <= 0) {
            \admin_require_perm('pages.create');
        } else {
            // Bearbeiten-Seite öffnen ist "edit"
            \admin_require_perm('pages.edit');
        }

        $page = null;
        if ($id > 0) {
            $page = $repo->findById($id);
        }

        if (!is_array($page)) {
            $page = [
                'id'             => 0,
                'slug'           => '/',
                'title'          => '',
                'frontend_title' => '',
                'subtitle'       => '',
                'status'         => 'live',
                'content_json'   => json_encode(['blocks' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"blocks":[]}',
                'is_deleted'     => 0,

                'is_home'        => 0,
                'nav_visible'    => 0,
                'nav_label'      => '',
                'nav_area'       => 'header',
                'nav_order'      => 0,
            ];
        } else {
            $page['frontend_title'] = (string)($page['frontend_title'] ?? '');
            $page['subtitle']       = (string)($page['subtitle'] ?? '');
            $page['status']         = (string)($page['status'] ?? 'live');

            $page['is_home']     = (int)($page['is_home'] ?? 0);
            $page['nav_visible'] = (int)($page['nav_visible'] ?? 0);
            $page['nav_label']   = (string)($page['nav_label'] ?? '');
            $page['nav_area']    = (string)($page['nav_area'] ?? 'header');
            $page['nav_order']   = (int)($page['nav_order'] ?? 0);
        }

        $flash = null;
        $revisions = [];
        $selectedRevision = null;
        $navCandidates = $repo->listActive();

        // SEO-Override (nur seitenspezifische Werte) für das Formular laden
        $seoSvc      = new SeoService(new SeoRepositoryDb($_pdo), new SiteSettingsRepositoryDb($_pdo));
        $seoOverride = $id > 0 ? ($seoSvc->getForPage($id, $page)['_override'] ?? []) : [];

        if ($id > 0) {
            $revisions = $repo->listRevisions($id, 20);
            $previewRevisionId = (int)($_GET['revision'] ?? 0);
            if ($previewRevisionId > 0) {
                $selectedRevision = $repo->findRevision($id, $previewRevisionId);
            }
        }

        \admin_layout_begin([
            'title'    => 'Seite bearbeiten',
            'theme'    => $theme,
            'active'   => 'pages',
            'user'     => $user,
            'next'     => '/pages',
            'pageCss'  => 'pages-edit',
            'headline' => 'Seite',
            'subtitle' => 'Slug muss eindeutig sein. Löschen ist Soft-Delete.',
        ]);

        require __DIR__ . '/../Views/pages_edit.php';
        \admin_layout_end();
    }

    public function save(): void
    {
        $user = \admin_require_perm('pages.view'); // Basis: darf überhaupt Pages nutzen
        [$user, $theme, $_pdo, $repo, $svc] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        // ✅ CSRF prüfen (POST + Sideeffect)
        \admin_verify_csrf();

        $id = (int)($_POST['id'] ?? 0);

        // Permission: create vs edit
        if ($id > 0) {
            \admin_require_perm('pages.edit');
        } else {
            \admin_require_perm('pages.create');
        }

        // Status-Änderung ist separat
        $postedStatus = (string)($_POST['status'] ?? 'live');
        if (!in_array($postedStatus, ['live', 'draft'], true)) $postedStatus = 'live';

        if ($id > 0) {
            $existing = $repo->findById($id);
            $oldStatus = is_array($existing) ? (string)($existing['status'] ?? 'live') : 'live';
            if ($oldStatus !== $postedStatus) {
                \admin_require_perm('pages.status.edit');
            }
        } else {
            if ($postedStatus === 'live') {
                \admin_require_perm('pages.status.edit');
            }
        }

        $slug    = (string)($_POST['slug'] ?? '/');
        $title   = (string)($_POST['title'] ?? '');

        $frontendTitle = (string)($_POST['frontend_title'] ?? '');
        $subtitle      = (string)($_POST['subtitle'] ?? '');
        $status        = $postedStatus;

        $content = (string)($_POST['content_json'] ?? '{"blocks":[]}');
        $restoreRevisionId = (int)($_POST['restore_revision_id'] ?? 0);

        if ($id > 0 && $restoreRevisionId > 0) {
            $rev = $repo->findRevision($id, $restoreRevisionId);
            if (is_array($rev)) {
                $title = (string)($rev['title'] ?? $title);
                $content = (string)($rev['content_json'] ?? $content);
            }
        }

        $isHome     = !empty($_POST['is_home']);
        $navVisible = !empty($_POST['nav_visible']);
        $navLabel   = (string)($_POST['nav_label'] ?? '');
        $navArea    = (string)($_POST['nav_area'] ?? 'header');
        $navOrder   = (int)($_POST['nav_order'] ?? 0);
        $navPlaceMode = (string)($_POST['nav_place_mode'] ?? 'after');
        if (!in_array($navPlaceMode, ['before', 'after'], true)) {
            $navPlaceMode = 'after';
        }
        $navPlaceRef = (int)($_POST['nav_place_ref'] ?? 0);

        $res = $svc->save(
            $id > 0 ? $id : null,
            $slug,
            $title,
            $frontendTitle,
            $subtitle,
            $status,
            $content,
            $isHome,
            $navVisible,
            $navLabel,
            $navArea,
            $navOrder,
            (int)($user['id'] ?? 0)
        );

        $id2   = (int)($res['id'] ?? 0);
        $flash = $res['flash'] ?? null;

        if (!empty($_POST['save_return']) && $id2 > 0) {
            header('Location: /pages');
            exit;
        }
        
        $seoSvc = new SeoService(new SeoRepositoryDb($_pdo), new SiteSettingsRepositoryDb($_pdo));

        if (($res['ok'] ?? false) && $id2 > 0) {
            $mus = new MediaUsageService($_pdo, new MediaUsageRepositoryDb($_pdo), new MediaRepositoryDb($_pdo));
            $mus->syncPageUsages($id2, $content);

            // SEO-Override speichern
            $seoSvc->saveForPage($id2, [
                'meta_title'       => $_POST['seo_meta_title']       ?? '',
                'meta_description' => $_POST['seo_meta_description'] ?? '',
                'robots'           => $_POST['seo_robots']           ?? '',
                'canonical_url'    => $_POST['seo_canonical_url']    ?? '',
                'og_title'         => $_POST['seo_og_title']         ?? '',
                'og_description'   => $_POST['seo_og_description']   ?? '',
                'og_image_url'     => $_POST['seo_og_image_url']     ?? '',
            ]);

            if ($navVisible && $navPlaceRef > 0) {
                $this->reorderNavigation($_pdo, $repo, $id2, $navArea, $navPlaceRef, $navPlaceMode);
            }
        }

        $page = $id2 > 0 ? $repo->findById($id2) : null;
        $revisions = $id2 > 0 ? $repo->listRevisions($id2, 20) : [];
        $selectedRevision = null;
        $navCandidates = $repo->listActive();
        if (!is_array($page)) {
            $page = [
                'id'             => 0,
                'slug'           => $svc->normalizeSlug($slug),
                'title'          => $title,
                'frontend_title' => $frontendTitle,
                'subtitle'       => $subtitle,
                'status'         => $status,
                'content_json'   => $content,
                'is_deleted'     => 0,

                'is_home'        => $isHome ? 1 : 0,
                'nav_visible'    => $navVisible ? 1 : 0,
                'nav_label'      => $navLabel,
                'nav_area'       => $navArea,
                'nav_order'      => $navOrder,
                '_nav_place_mode'=> $navPlaceMode,
                '_nav_place_ref' => $navPlaceRef,
            ];
        }

        // SEO-Override für die Formular-Wiederanzeige
        $seoOverride = $id2 > 0 ? ($seoSvc->getForPage($id2, is_array($page) ? $page : [])['_override'] ?? []) : [];

        \admin_layout_begin([
            'title'    => 'Seite bearbeiten',
            'theme'    => $theme,
            'active'   => 'pages',
            'user'     => $user,
            'next'     => '/pages',
            'pageCss'  => 'pages-edit',
            'headline' => 'Seite',
            'subtitle' => 'Slug muss eindeutig sein. Löschen ist Soft-Delete.',
        ]);

        require __DIR__ . '/../Views/pages_edit.php';
        \admin_layout_end();
    }

    private function navAppearsInArea(array $row, string $area): bool
    {
        if ((int)($row['is_deleted'] ?? 0) !== 0) return false;
        if ((int)($row['nav_visible'] ?? 0) !== 1) return false;
        $a = (string)($row['nav_area'] ?? 'header');
        return $a === $area || $a === 'both';
    }

    private function reorderNavigation(
        \PDO $pdo,
        PageRepositoryDb $repo,
        int $pageId,
        string $navArea,
        int $refId,
        string $mode
    ): void {
        $baseArea = in_array($navArea, ['header', 'footer'], true) ? $navArea : 'header';
        $rows = $repo->listActive();

        $ordered = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            if (!$this->navAppearsInArea($row, $baseArea)) continue;
            $rid = (int)($row['id'] ?? 0);
            if ($rid > 0) $ordered[] = $rid;
        }

        $ordered = array_values(array_filter($ordered, static fn(int $id): bool => $id !== $pageId));
        if (!in_array($pageId, $ordered, true)) {
            $ordered[] = $pageId;
        }

        $refPos = array_search($refId, $ordered, true);
        if ($refPos !== false) {
            $ordered = array_values(array_filter($ordered, static fn(int $id): bool => $id !== $pageId));
            $insertPos = ($mode === 'before') ? (int)$refPos : ((int)$refPos + 1);
            array_splice($ordered, $insertPos, 0, [$pageId]);
        }

        $upd = $pdo->prepare('UPDATE pages SET nav_order = :nav_order WHERE id = :id LIMIT 1');
        $n = 10;
        foreach ($ordered as $rid) {
            $upd->execute([
                ':nav_order' => $n,
                ':id' => $rid,
            ]);
            $n += 10;
        }
    }

    public function delete(): void
    {
        $user = \admin_require_perm('pages.delete');
        [$user, $_theme, $_pdo, $repo] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        // ✅ CSRF prüfen
        \admin_verify_csrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) $repo->softDelete($id);

        header('Location: /pages');
        exit;
    }

    public function restore(): void
    {
        // Restore hängt an delete
        $user = \admin_require_perm('pages.delete');
        [$user, $_theme, $_pdo, $repo] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        // ✅ CSRF prüfen
        \admin_verify_csrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) $repo->restore($id);

        header('Location: /pages/deleted');
        exit;
    }
    public function purge(): void
    {
        $user = \admin_require_perm('pages.delete');
        [$user, $_theme, $_pdo, $repo] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        \admin_verify_csrf();

        $n = $repo->purgeDeleted();
        $_SESSION['flash'] = ($n > 0)
            ? ['type' => 'success', 'msg' => 'Papierkorb geleert (' . $n . ').']
            : ['type' => 'success', 'msg' => 'Papierkorb ist bereits leer.'];

        header('Location: /pages/deleted');
        exit;
    }

}
