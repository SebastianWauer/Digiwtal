<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repositories\EventCategoryRepositoryDb;
use App\Repositories\EventRepositoryDb;

final class EventsController
{
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
            'subtitle' => 'Events anlegen, kategorisieren und veroeffentlichen.',
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
            'title' => 'Geloeschte Events',
            'theme' => $theme,
            'active' => 'events',
            'user' => $user,
            'next' => '/events',
            'pageCss' => 'pages-list',
            'headline' => 'Events',
            'subtitle' => 'Papierkorb fuer Events.',
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
            $categories->updateName($id, $name);
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
                'description' => '',
                'event_date_from' => '',
                'event_date_to' => '',
                'image_media_id' => null,
                'youtube_url' => '',
                'is_published' => 1,
                'is_deleted' => 0,
                'category_ids_csv' => '',
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
        $description = trim((string)($_POST['description'] ?? ''));
        $youtubeUrl = trim((string)($_POST['youtube_url'] ?? ''));
        $isPublished = !empty($_POST['is_published']);
        $imageMediaId = (int)($_POST['image_media_id'] ?? 0);
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
        $newCategory = trim((string)($_POST['category_new'] ?? ''));
        $eventDateFromRaw = trim((string)($_POST['event_date_from'] ?? ''));
        $eventDateToRaw = trim((string)($_POST['event_date_to'] ?? ''));

        if ($title === '') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Titel ist erforderlich.'];
            header('Location: /events/edit' . ($id > 0 ? ('?id=' . $id) : ''));
            exit;
        }

        if ($youtubeUrl !== '' && preg_match('#^(https?://(www\.)?(youtube\.com|youtu\.be)/)#i', $youtubeUrl) !== 1) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'YouTube-URL ist ungueltig.'];
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

        $events->save(
            $id > 0 ? $id : null,
            $categoryIds,
            $categoryImageMediaIds,
            $title,
            $description,
            $eventDateFrom,
            $eventDateTo,
            $imageMediaId > 0 ? $imageMediaId : null,
            $youtubeUrl,
            0,
            $isPublished
        );

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
            $events = new EventRepositoryDb(\admin_pdo());
            $events->restore($id);
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
        $events = new EventRepositoryDb(\admin_pdo());
        $n = $events->purgeDeleted();
        $_SESSION['flash'] = ['type' => 'success', 'msg' => $n > 0 ? ('Papierkorb geleert (' . $n . ').') : 'Papierkorb ist bereits leer.'];
        header('Location: /events/deleted');
        exit;
    }
}
