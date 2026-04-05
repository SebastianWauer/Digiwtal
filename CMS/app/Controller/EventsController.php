<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repositories\EventCategoryRepositoryDb;
use App\Repositories\EventRepositoryDb;
use App\Repositories\MediaRepositoryDb;
use App\Repositories\MediaUsageRepositoryDb;
use App\Services\MediaUsageService;

final class EventsController
{
    private function mediaUsageService(): MediaUsageService
    {
        $pdo = \admin_pdo();
        return new MediaUsageService(
            $pdo,
            new MediaUsageRepositoryDb($pdo),
            new MediaRepositoryDb($pdo)
        );
    }

    private function deps(array $user): array
    {
        $theme = \admin_theme_for_user((int)($user['id'] ?? 0));
        $pdo = \admin_pdo();
        $events = new EventRepositoryDb($pdo);
        $categories = new EventCategoryRepositoryDb($pdo);
        return [$user, $theme, $pdo, $events, $categories];
    }

    public function index(): void
    {
        $user = \admin_require_perm('events.view');
        [$user, $theme, $_pdo, $events, $categories] = $this->deps($user);

        $categoryId = (int)($_GET['category_id'] ?? 0);
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        try {
            $this->mediaUsageService()->rebuildAllEventUsages();
            $rows = $events->listActive($categoryId > 0 ? $categoryId : null);
            $allCategories = $categories->listActive();
            $deletedCount = $events->countDeleted();
        } catch (\Throwable $e) {
            $rows = [];
            $allCategories = [];
            $deletedCount = 0;
            $flash = ['type' => 'error', 'msg' => 'Events konnten nicht geladen werden: ' . $e->getMessage()];
        }

        \admin_layout_begin([
            'title' => 'Events',
            'theme' => $theme,
            'active' => 'events',
            'user' => $user,
            'next' => '/events',
            'pageCss' => 'pages-list',
            'headline' => 'Events & Termine',
            'subtitle' => 'Events anlegen, kategorisieren und veröffentlichen.',
        ]);

        require __DIR__ . '/../Views/events_list.php';
        \admin_layout_end();
    }

    public function deleted(): void
    {
        $user = \admin_require_perm('events.view');
        [$user, $theme, $_pdo, $events] = $this->deps($user);
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        try {
            $rows = $events->listDeleted();
            $deletedCount = $events->countDeleted();
        } catch (\Throwable $e) {
            $rows = [];
            $deletedCount = 0;
            $flash = ['type' => 'error', 'msg' => 'Papierkorb konnte nicht geladen werden: ' . $e->getMessage()];
        }

        \admin_layout_begin([
            'title' => 'Gelöschte Events',
            'theme' => $theme,
            'active' => 'events',
            'user' => $user,
            'next' => '/events',
            'pageCss' => 'pages-list',
            'headline' => 'Events',
            'subtitle' => 'Papierkorb für Events.',
        ]);

        require __DIR__ . '/../Views/events_deleted.php';
        \admin_layout_end();
    }

    public function categories(): void
    {
        $user = \admin_require_perm('events.view');
        \admin_require_perm('events.edit');
        [$user, $theme, $_pdo, $_events, $categories] = $this->deps($user);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        try {
            $rows = $categories->listActive();
        } catch (\Throwable $e) {
            $rows = [];
            $flash = ['type' => 'error', 'msg' => 'Kategorien konnten nicht geladen werden: ' . $e->getMessage()];
        }

        \admin_layout_begin([
            'title' => 'Event-Kategorien',
            'theme' => $theme,
            'active' => 'events',
            'user' => $user,
            'next' => '/events/categories',
            'pageCss' => 'pages-list',
            'headline' => 'Event-Kategorien',
            'subtitle' => 'Kategorien ansehen und Namen bearbeiten.',
        ]);

        require __DIR__ . '/../Views/events_categories.php';
        \admin_layout_end();
    }

    public function saveCategory(): void
    {
        $user = \admin_require_perm('events.view');
        \admin_require_perm('events.edit');
        [$user, $_theme, $_pdo, $_events, $categories] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }
        \admin_verify_csrf();

        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $colorHexRaw = trim((string)($_POST['color_hex'] ?? ''));
        $colorHex = null;
        if ($colorHexRaw !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $colorHexRaw) === 1) {
            $colorHex = strtoupper($colorHexRaw);
        }

        if ($id <= 0 || $name === '') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Kategorie-ID und Name sind erforderlich.'];
            header('Location: /events/categories');
            exit;
        }

        try {
            $existing = $categories->findById($id);
            if (!is_array($existing)) {
                $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Kategorie nicht gefunden.'];
                header('Location: /events/categories');
                exit;
            }
            $categories->update($id, $name, $colorHex);
            $saved = $categories->findById($id);
            if ($colorHex !== null) {
                $savedColor = strtoupper(trim((string)($saved['color_hex'] ?? '')));
                if ($savedColor !== $colorHex) {
                    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Farbe konnte nicht gespeichert werden (DB-Spalte color_hex fehlt oder ist nicht beschreibbar).'];
                    header('Location: /events/categories');
                    exit;
                }
            }
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Kategorie gespeichert.'];
        } catch (\Throwable $e) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Kategorie konnte nicht gespeichert werden: ' . $e->getMessage()];
        }

        header('Location: /events/categories');
        exit;
    }

    public function edit(): void
    {
        $user = \admin_require_perm('events.view');
        [$user, $theme, $_pdo, $events, $categories] = $this->deps($user);

        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            \admin_require_perm('events.edit');
        } else {
            \admin_require_perm('events.create');
        }

        $flash = null;
        try {
            $row = $id > 0 ? $events->findById($id) : null;
            $allCategories = $categories->listActive();
        } catch (\Throwable $e) {
            $row = null;
            $allCategories = [];
            $flash = ['type' => 'error', 'msg' => 'Event konnte nicht geladen werden: ' . $e->getMessage()];
        }
        if (!is_array($row)) {
            $row = [
                'id' => 0,
                'title' => '',
                'subtitle' => '',
                'description' => '',
                'event_date_from' => '',
                'event_date_to' => '',
                'image_media_id' => null,
                'youtube_url' => '',
                'is_published' => 1,
                'is_deleted' => 0,
                'category_ids_csv' => '',
                'category_links_map' => [],
            ];
        }
        if (!empty($row['is_deleted'])) {
            header('Location: /events/deleted');
            exit;
        }

        \admin_layout_begin([
            'title' => 'Event bearbeiten',
            'theme' => $theme,
            'active' => 'events',
            'user' => $user,
            'next' => '/events',
            'pageCss' => 'pages-edit',
            'headline' => 'Event',
            'subtitle' => 'Titel, Datum, Kategorie, Bild, Text und YouTube-Link.',
        ]);

        require __DIR__ . '/../Views/events_edit.php';
        \admin_layout_end();
    }

    public function save(): void
    {
        $user = \admin_require_perm('events.view');
        [$user, $theme, $_pdo, $events, $categories] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }
        \admin_verify_csrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            \admin_require_perm('events.edit');
        } else {
            \admin_require_perm('events.create');
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $subtitle = trim((string)($_POST['subtitle'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $youtubeUrl = trim((string)($_POST['youtube_url'] ?? ''));
        $isPublished = !empty($_POST['is_published']);
        $postedCategoryIds = $_POST['category_ids'] ?? [];
        if (!is_array($postedCategoryIds)) {
            $postedCategoryIds = [];
        }
        $categoryIds = array_values(array_unique(array_filter(array_map(static fn($v): int => (int)$v, $postedCategoryIds), static fn(int $v): bool => $v > 0)));
        $postedCategoryImageMediaIds = $_POST['category_image_media_ids'] ?? [];
        if (!is_array($postedCategoryImageMediaIds)) {
            $postedCategoryImageMediaIds = [];
        }
        $categoryImageMediaIds = [];
        foreach ($postedCategoryImageMediaIds as $cidRaw => $midRaw) {
            $cid = (int)$cidRaw;
            $mid = (int)$midRaw;
            if ($cid > 0) {
                $categoryImageMediaIds[$cid] = $mid > 0 ? $mid : 0;
            }
        }
        $postedCategoryLinks = $_POST['category_links'] ?? [];
        if (!is_array($postedCategoryLinks)) {
            $postedCategoryLinks = [];
        }
        $categoryLinksMap = [];
        foreach ($postedCategoryLinks as $cidRaw => $groupRaw) {
            $cid = (int)$cidRaw;
            if ($cid <= 0 || !is_array($groupRaw)) {
                continue;
            }
            $labels = $groupRaw['label'] ?? [];
            $urls = $groupRaw['url'] ?? [];
            $types = $groupRaw['type'] ?? [];
            $pdfMediaIds = $groupRaw['pdf_media_id'] ?? [];
            if (!is_array($labels) || !is_array($urls) || !is_array($types) || !is_array($pdfMediaIds)) {
                continue;
            }
            $max = max(count($labels), count($urls), count($types), count($pdfMediaIds));
            if ($max <= 0) {
                continue;
            }
            for ($i = 0; $i < $max; $i++) {
                $type = strtolower(trim((string)($types[$i] ?? 'link')));
                if (!in_array($type, ['link', 'youtube', 'pdf'], true)) {
                    $type = 'link';
                }
                $label = trim((string)($labels[$i] ?? ''));
                $url = trim((string)($urls[$i] ?? ''));
                $pdfMediaId = (int)($pdfMediaIds[$i] ?? 0);
                if ($label === '' && $url === '') {
                    if ($pdfMediaId <= 0) {
                        continue;
                    }
                }

                if ($label === '') {
                    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Bitte eine Beschriftung pro Eintrag angeben.'];
                    header('Location: /events/edit' . ($id > 0 ? ('?id=' . $id) : ''));
                    exit;
                }

                if ($type === 'youtube') {
                    if ($url === '' || preg_match('#^(https?://(www\.)?(youtube\.com|youtu\.be)/)#i', $url) !== 1) {
                        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'YouTube-Einträge benötigen eine gültige YouTube-URL.'];
                        header('Location: /events/edit' . ($id > 0 ? ('?id=' . $id) : ''));
                        exit;
                    }
                } elseif ($type === 'pdf') {
                    if ($pdfMediaId <= 0 && $url === '') {
                        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'PDF-Einträge benötigen entweder eine PDF-Media-ID oder eine PDF-URL.'];
                        header('Location: /events/edit' . ($id > 0 ? ('?id=' . $id) : ''));
                        exit;
                    }
                    if ($url !== '' && preg_match('#^(https?://|/)#i', $url) !== 1) {
                        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'PDF-URL ist ungültig. Erlaubt: https://, http:// oder /pfad'];
                        header('Location: /events/edit' . ($id > 0 ? ('?id=' . $id) : ''));
                        exit;
                    }
                } else {
                    if ($url === '') {
                        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Weiterleitungs-Einträge benötigen eine URL.'];
                        header('Location: /events/edit' . ($id > 0 ? ('?id=' . $id) : ''));
                        exit;
                    }
                    if (preg_match('#^(https?://|mailto:|tel:|/)#i', $url) !== 1) {
                        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Ungültige Link-URL. Erlaubt: https://, http://, mailto:, tel: oder /pfad'];
                        header('Location: /events/edit' . ($id > 0 ? ('?id=' . $id) : ''));
                        exit;
                    }
                }

                if ($url === '' && $pdfMediaId > 0 && $type === 'pdf') {
                    $url = '/media/file?id=' . $pdfMediaId;
                }

                if ($url === '' && $type !== 'pdf') {
                    continue;
                }
                if (!isset($categoryLinksMap[$cid]) || !is_array($categoryLinksMap[$cid])) {
                    $categoryLinksMap[$cid] = [];
                }
                $categoryLinksMap[$cid][] = [
                    'type' => $type,
                    'label' => mb_substr($label, 0, 120, 'UTF-8'),
                    'url' => mb_substr($url, 0, 2048, 'UTF-8'),
                    'pdf_media_id' => $pdfMediaId > 0 ? $pdfMediaId : 0,
                ];
            }
        }
        $newCategory = trim((string)($_POST['category_new'] ?? ''));
        $eventDateFromRaw = trim((string)($_POST['event_date_from'] ?? ''));
        $eventDateToRaw = trim((string)($_POST['event_date_to'] ?? ''));

        if ($title === '') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Titel ist erforderlich.'];
            header('Location: /events/edit' . ($id > 0 ? ('?id=' . $id) : ''));
            exit;
        }

        if ($youtubeUrl !== '' && preg_match('#^(https?://(www\.)?(youtube\.com|youtu\.be)/)#i', $youtubeUrl) !== 1) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'YouTube-URL ist ungültig.'];
            header('Location: /events/edit' . ($id > 0 ? ('?id=' . $id) : ''));
            exit;
        }

        if ($newCategory !== '') {
            $parts = preg_split('/[,;]+/', $newCategory) ?: [];
            foreach ($parts as $part) {
                $name = trim((string)$part);
                if ($name === '') {
                    continue;
                }
                $slug = EventCategoryRepositoryDb::slugify($name);
                $existing = $categories->findBySlug($slug);
                if (is_array($existing)) {
                    $categoryIds[] = (int)($existing['id'] ?? 0);
                } else {
                    $categoryIds[] = $categories->create($name, $slug);
                }
            }
        }
        $categoryIds = array_values(array_unique(array_filter($categoryIds, static fn(int $v): bool => $v > 0)));

        $eventDateFrom = null;
        $eventDateTo = null;
        if ($eventDateFromRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDateFromRaw) === 1) {
            $eventDateFrom = $eventDateFromRaw;
        }
        if ($eventDateToRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDateToRaw) === 1) {
            $eventDateTo = $eventDateToRaw;
        }
        if ($eventDateFrom !== null && $eventDateTo !== null && $eventDateTo < $eventDateFrom) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Bis-Datum darf nicht vor Von-Datum liegen.'];
            header('Location: /events/edit' . ($id > 0 ? ('?id=' . $id) : ''));
            exit;
        }

        $savedEventId = $events->save(
            $id > 0 ? $id : null,
            $categoryIds,
            $categoryImageMediaIds,
            $categoryLinksMap,
            $title,
            $subtitle,
            $description,
            $eventDateFrom,
            $eventDateTo,
            null,
            $youtubeUrl,
            0,
            $isPublished
        );
        $allowedCategoryIds = array_fill_keys($categoryIds, true);
        $usageCategoryMap = [];
        foreach ($categoryImageMediaIds as $cidRaw => $midRaw) {
            $cid = (int)$cidRaw;
            $mid = (int)$midRaw;
            if ($cid <= 0 || $mid <= 0 || !isset($allowedCategoryIds[$cid])) {
                continue;
            }
            $usageCategoryMap[$cid] = $mid;
        }
        $this->mediaUsageService()->syncEventUsages($savedEventId, $usageCategoryMap);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Event gespeichert.'];
        if (!empty($_POST['save_return'])) {
            header('Location: /events');
            exit;
        }
        header('Location: /events');
        exit;
    }

    public function delete(): void
    {
        \admin_require_perm('events.delete');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }
        \admin_verify_csrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->mediaUsageService()->clearEntityUsages('event', $id);
            $events = new EventRepositoryDb(\admin_pdo());
            $events->softDelete($id);
        }
        header('Location: /events');
        exit;
    }

    public function restore(): void
    {
        \admin_require_perm('events.delete');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }
        \admin_verify_csrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo = \admin_pdo();
            $events = new EventRepositoryDb($pdo);
            $events->restore($id);
            $row = $events->findById($id);
            if (is_array($row)) {
                $categoryMapRaw = is_array($row['category_image_media_map'] ?? null) ? $row['category_image_media_map'] : [];
                $categoryMap = [];
                foreach ($categoryMapRaw as $cidRaw => $midRaw) {
                    $cid = (int)$cidRaw;
                    $mid = (int)$midRaw;
                    if ($cid > 0 && $mid > 0) {
                        $categoryMap[$cid] = $mid;
                    }
                }
                $this->mediaUsageService()->syncEventUsages($id, $categoryMap);
            }
        }
        header('Location: /events/deleted');
        exit;
    }

    public function purge(): void
    {
        \admin_require_perm('events.delete');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }
        \admin_verify_csrf();
        $pdo = \admin_pdo();
        $events = new EventRepositoryDb($pdo);
        try {
            $idsStmt = $pdo->query("SELECT id FROM events WHERE is_deleted = 1");
            $rows = $idsStmt ? $idsStmt->fetchAll() : [];
            foreach (is_array($rows) ? $rows : [] as $r) {
                if (!is_array($r)) continue;
                $eid = (int)($r['id'] ?? 0);
                if ($eid > 0) {
                    $this->mediaUsageService()->clearEntityUsages('event', $eid);
                }
            }
        } catch (\Throwable) {
            // Ignore usage cleanup issues in purge flow.
        }
        $n = $events->purgeDeleted();
        $_SESSION['flash'] = ['type' => 'success', 'msg' => $n > 0 ? ('Papierkorb geleert (' . $n . ').') : 'Papierkorb ist bereits leer.'];
        header('Location: /events/deleted');
        exit;
    }
}

