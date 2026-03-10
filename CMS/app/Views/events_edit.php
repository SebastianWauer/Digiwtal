<?php
declare(strict_types=1);

echo flash_render($flash ?? null);

$id = (int)($row['id'] ?? 0);
$title = (string)($row['title'] ?? '');
$subtitle = (string)($row['subtitle'] ?? '');
$description = (string)($row['description'] ?? '');
$eventDateFrom = trim((string)($row['event_date_from'] ?? ''));
$eventDateTo = trim((string)($row['event_date_to'] ?? ''));
$categoryIdsCsv = trim((string)($row['category_ids_csv'] ?? ''));
$selectedCategoryIds = $categoryIdsCsv !== ''
  ? array_values(array_unique(array_filter(array_map(static fn($v): int => (int)$v, explode(',', $categoryIdsCsv)), static fn(int $v): bool => $v > 0)))
  : [];
$categoryImageMediaMap = is_array($row['category_image_media_map'] ?? null) ? $row['category_image_media_map'] : [];
$youtubeUrl = (string)($row['youtube_url'] ?? '');
$isPublished = !empty($row['is_published']);
$csrfField = function_exists('admin_csrf_field') ? admin_csrf_field() : '';
?>

<form method="post" action="/events/save" class="pages-edit-form">
  <?= $csrfField ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">

  <div class="pages-edit-layout">
    <section class="pages-edit-left">
      <div class="pages-edit-card">
        <div class="pages-edit-card-head">
          <div>
            <div class="pages-edit-card-title">Event</div>
            <div class="pages-edit-card-sub">Titel, Datum, Kategorien, Text und YouTube-Link.</div>
          </div>
        </div>

        <div class="pages-edit-fields">
          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Titel</div>
            <input class="pages-edit-input" type="text" name="title" value="<?= h($title) ?>" required>
          </div>

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Untertitel</div>
            <input class="pages-edit-input" type="text" name="subtitle" value="<?= h($subtitle) ?>" placeholder="optional">
          </div>

          <div class="pages-edit-grid2">
            <div class="pages-edit-field">
              <div class="pages-edit-field-label">Von (Datum)</div>
              <input class="pages-edit-input" type="date" name="event_date_from" value="<?= h($eventDateFrom) ?>">
            </div>

            <div class="pages-edit-field">
              <div class="pages-edit-field-label">Bis (Datum)</div>
              <input class="pages-edit-input" type="date" name="event_date_to" value="<?= h($eventDateTo) ?>">
            </div>
          </div>

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Kategorien (Mehrfachauswahl)</div>
            <div class="pages-edit-field-hint">Ein Event kann in mehreren Kategorien sein. Pro Kategorie kann ein eigenes Bild gesetzt werden.</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.35rem .75rem;margin-top:.45rem;">
              <?php foreach ($allCategories as $cat): ?>
                <?php $cid = (int)($cat['id'] ?? 0); if ($cid <= 0) continue; ?>
                <div style="border:1px solid var(--line,#e5e7eb);border-radius:10px;padding:.5rem;">
                  <label class="pages-edit-check" style="margin:0;">
                    <input type="checkbox" name="category_ids[]" value="<?= $cid ?>" data-category-checkbox="<?= $cid ?>" <?= in_array($cid, $selectedCategoryIds, true) ? 'checked' : '' ?>>
                    <span><?= h((string)($cat['name'] ?? 'Kategorie')) ?></span>
                  </label>
                  <div data-category-media-wrap="<?= $cid ?>" style="margin-top:.5rem;<?= in_array($cid, $selectedCategoryIds, true) ? '' : 'display:none;' ?>">
                    <?php
                      $cmid = (int)($categoryImageMediaMap[$cid] ?? 0);
                      media_picker_render('Bild für ' . (string)($cat['name'] ?? 'Kategorie'), 'category_image_media_ids[' . $cid . ']', $cmid > 0 ? (string)$cmid : '', ['showPreview' => true]);
                    ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Neue Kategorie(n) (optional)</div>
            <input class="pages-edit-input" type="text" name="category_new" value="" placeholder="z.B. Motorsport, TV, Live">
            <div class="pages-edit-field-hint">Mehrere neue Kategorien mit Komma trennen.</div>
          </div>

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">YouTube-URL (optional)</div>
            <input class="pages-edit-input" type="text" name="youtube_url" value="<?= h($youtubeUrl) ?>" placeholder="https://www.youtube.com/watch?v=...">
          </div>

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Beschreibung</div>
            <textarea class="pages-edit-textarea" name="description" rows="8"><?= h($description) ?></textarea>
          </div>

        </div>
      </div>

      <div class="pages-edit-actionsbar">
        <button type="submit" class="btn">Speichern</button>
        <a class="btn btn--ghost" href="/events">Zurück</a>
      </div>
    </section>

    <aside class="pages-edit-right">
      <div class="pages-edit-card">
        <div class="pages-edit-card-title">Status</div>
        <div class="pages-edit-card-sub">Steuert die Sichtbarkeit im Frontend.</div>
        <div class="pages-edit-fields">
          <label class="pages-edit-check">
            <input type="checkbox" name="is_published" value="1" <?= $isPublished ? 'checked' : '' ?>>
            <span>Event ist sichtbar</span>
          </label>
        </div>
      </div>
    </aside>
  </div>
</form>
<script>
(() => {
  const boxes = Array.from(document.querySelectorAll('input[data-category-checkbox]'));
  boxes.forEach((box) => {
    const cid = box.getAttribute('data-category-checkbox');
    if (!cid) return;
    const wrap = document.querySelector('[data-category-media-wrap="' + cid + '"]');
    if (!wrap) return;
    const toggle = () => {
      wrap.style.display = box.checked ? '' : 'none';
    };
    box.addEventListener('change', toggle);
    toggle();
  });
})();
</script>

