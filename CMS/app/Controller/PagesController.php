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
        }

        $page = $id2 > 0 ? $repo->findById($id2) : null;
        $revisions = $id2 > 0 ? $repo->listRevisions($id2, 20) : [];
        $selectedRevision = null;
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
