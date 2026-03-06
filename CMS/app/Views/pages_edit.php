<?php
declare(strict_types=1);

/** @var array $page */
/** @var ?array $flash */
/** @var array $seoOverride */
/** @var array $revisions */
/** @var ?array $selectedRevision */

use App\PageBuilder\BlockRegistry;

echo flash_render($flash);

$id      = (int)($page['id'] ?? 0);
$slug    = (string)($page['slug'] ?? '');
$title   = (string)($page['title'] ?? '');
$content = (string)($page['content_json'] ?? '{"blocks":[]}');
$deleted = !empty($page['is_deleted']);

$isHome     = !empty($page['is_home']);
$navVisible = !empty($page['nav_visible']);
$navLabel   = (string)($page['nav_label'] ?? '');
$navArea    = (string)($page['nav_area'] ?? 'header');
$navOrder   = (int)($page['nav_order'] ?? 0);

// Meta (008_pages_meta.sql)
$frontendTitle = (string)($page['frontend_title'] ?? '');
$subtitle      = (string)($page['subtitle'] ?? '');
$status        = (string)($page['status'] ?? 'live');
if (!in_array($status, ['live','draft'], true)) $status = 'live';

// kleine Anzeige-URL (nur UI, nicht speichern)
$prettySlug = $slug !== '' ? $slug : '— automatisch aus Titel —';
if ($prettySlug !== '— automatisch aus Titel —' && $prettySlug[0] !== '/') $prettySlug = '/' . $prettySlug;

// Permissions (UI-only)
$canCreate     = function_exists('admin_can') && admin_can('pages.create');
$canEdit       = function_exists('admin_can') && admin_can('pages.edit');
$canDelete     = function_exists('admin_can') && admin_can('pages.delete');
$canStatusEdit = function_exists('admin_can') && admin_can('pages.status.edit');

// Restore soll an edit hängen
$canRestore = $canEdit;

$canSave = ($id > 0) ? $canEdit : $canCreate;

// Content JSON decode (defensiv)
$decoded = json_decode($content, true);
if (!is_array($decoded)) $decoded = ['blocks' => []];
if (array_is_list($decoded)) $decoded = ['blocks' => $decoded];
$blocks = $decoded['blocks'] ?? [];
if (!is_array($blocks)) $blocks = [];

$defs = BlockRegistry::definitions();
$defsJson = json_encode($defs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($defsJson) || $defsJson === '') $defsJson = '{}';

// Status label for readonly
$statusLabel = ($status === 'draft') ? 'Entwurf' : 'Live';

// SEO-Override-Werte (seitenspezifisch, leer = globaler Default gilt)
$seoOverride     = $seoOverride ?? [];
$seoMetaTitle    = (string)($seoOverride['meta_title']       ?? '');
$seoMetaDesc     = (string)($seoOverride['meta_description'] ?? '');
$seoRobots       = (string)($seoOverride['robots']           ?? '');
$seoCanonical    = (string)($seoOverride['canonical_url']    ?? '');
$seoOgTitle      = (string)($seoOverride['og_title']         ?? '');
$seoOgDesc       = (string)($seoOverride['og_description']   ?? '');
$seoOgImage      = (string)($seoOverride['og_image_url']     ?? '');
$revisions       = is_array($revisions ?? null) ? $revisions : [];
$selectedRevision = is_array($selectedRevision ?? null) ? $selectedRevision : null;
?>
<form method="post" action="/pages/save" class="pages-edit-form" id="pageEditForm">
  <?= admin_csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">
  <input type="hidden" name="content_json" id="contentJsonInput" value="<?= h($content) ?>">

  <?php if ($canSave && !$canStatusEdit): ?>
    <!-- Status mitschicken, damit Save NICHT versucht auf "live" zu springen -->
    <input type="hidden" name="status" value="<?= h($status) ?>">
  <?php endif; ?>

  <div class="pages-edit-layout">

    <section class="pages-edit-left">

      <div class="pages-edit-card">
        <div class="pages-edit-card-head">
          <div>
            <div class="pages-edit-card-title">Inhalt</div>
            <div class="pages-edit-card-sub">Baue die Seite aus Blöcken.</div>
          </div>
          <div class="pages-edit-url">
            <span class="label">URL</span>
            <span class="url"><?= h($prettySlug) ?></span>
          </div>
        </div>

        <div class="pages-edit-fields">

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Titel (intern)</div>
            <input id="pageTitleInput" class="pages-edit-input" type="text" name="title" value="<?= h($title) ?>" required <?= $canSave ? '' : 'readonly' ?>>
            <div class="pages-edit-field-hint">Technischer Titel im CMS (z.B. „Kontaktseite“).</div>
          </div>

          <div class="pages-edit-grid2">
            <div class="pages-edit-field">
              <div class="pages-edit-field-label">Frontend-Titel</div>
              <input class="pages-edit-input" type="text" name="frontend_title" value="<?= h($frontendTitle) ?>" placeholder="z.B. Kontakt" <?= $canSave ? '' : 'readonly' ?>>
              <div class="pages-edit-field-hint">Was im Frontend als Seitenüberschrift angezeigt wird.</div>
            </div>

            <div class="pages-edit-field">
              <div class="pages-edit-field-label">Untertitel</div>
              <input class="pages-edit-input" type="text" name="subtitle" value="<?= h($subtitle) ?>" placeholder="optional" <?= $canSave ? '' : 'readonly' ?>>
              <div class="pages-edit-field-hint">Optionaler Untertitel im Frontend.</div>
            </div>
          </div>

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Slug</div>
            <input id="pageSlugInput" class="pages-edit-input" type="text" name="slug" value="<?= h($slug) ?>" placeholder="/Titel" <?= $canSave ? '' : 'readonly' ?>>
            <div class="pages-edit-field-hint">
              Optional. Wenn leer, wird der Slug automatisch aus dem Titel erzeugt.
              Beispiel: <code>/kontakt</code>
            </div>
            <div class="pages-edit-field-hint pages-edit-slug-live" id="pageSlugLiveHint"></div>
          </div>

          <hr class="pages-edit-sep">

          <div class="pages-edit-card-title pages-edit-pb-title">PageBuilder</div>
          <div class="pages-edit-card-sub pages-edit-pb-sub">Text, Bild, Hero — Reihenfolge & Inhalte bearbeiten.</div>

          <?php if ($canSave): ?>
            <div class="pages-edit-pb-actions">
              <button type="button" class="btn btn--ghost btn--sm" id="pbUndoBtn">↩ Rückgängig</button>
              <button type="button" class="btn btn--ghost btn--sm" id="pbRedoBtn">↪ Wiederholen</button>
              <button type="button" class="btn btn--ghost btn--sm" data-add-block="text">+ Textblock</button>
              <button type="button" class="btn btn--ghost btn--sm" data-add-block="image">+ Bild</button>
              <button type="button" class="btn btn--ghost btn--sm" data-add-block="hero">+ Herobanner</button>
              <button type="button" class="btn btn--ghost btn--sm" data-add-block="columns">+ Spalten</button>
              <button type="button" class="btn btn--ghost btn--sm" data-add-block="cta">+ Call-to-Action</button>
              <button type="button" class="btn btn--ghost btn--sm" data-add-block="faq">+ FAQ</button>
              <button type="button" class="btn btn--ghost btn--sm" data-add-block="video">+ Video</button>
              <button type="button" class="btn btn--ghost btn--sm" data-add-block="gallery">+ Galerie</button>
            </div>
          <?php else: ?>
            <div class="pages-edit-field-hint pages-edit-pb-readonly">
              Du hast keine Berechtigung, diese Seite zu bearbeiten. Inhalte werden nur angezeigt.
            </div>
          <?php endif; ?>

          <div class="pages-edit-preview-split">
            <div class="pages-edit-preview-split__editor">
              <div id="blocksContainer"></div>
            </div>
            <div class="pages-edit-preview-split__preview">
              <div class="pages-edit-field-label">Live-Vorschau</div>
              <iframe id="pageBuilderPreview" class="pages-edit-preview-frame" title="PageBuilder Vorschau"></iframe>
            </div>
          </div>

          <details class="pages-edit-raw">
            <summary class="pages-edit-raw__summary">Raw JSON anzeigen</summary>
            <textarea class="pages-edit-textarea pages-edit-raw__textarea" id="rawJsonTextarea" rows="10" <?= $canSave ? '' : 'readonly' ?>><?= h($content) ?></textarea>
            <div class="pages-edit-field-hint">Nur Debug. Gespeichert wird der PageBuilder-Stand.</div>
          </details>

        </div>
      </div>

      <div class="pages-edit-actionsbar">
        <?php if ($canSave): ?>
          <button type="submit" class="btn">Speichern</button>
        <?php endif; ?>

        <a class="btn btn--ghost" href="/pages">Zurück</a>

        <div class="pages-edit-actionsbar-spacer"></div>

        <?php if ($id > 0 && !$deleted && $canDelete): ?>
          <form method="post" action="/pages/delete" class="form-reset">
            <?= admin_csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <button type="submit" class="btn btn--ghost btn--danger btn--sm">Löschen</button>
          </form>
        <?php endif; ?>

        <?php if ($id > 0 && $deleted && $canRestore): ?>
          <form method="post" action="/pages/restore" class="form-reset">
            <?= admin_csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <button type="submit" class="btn btn--ghost btn--warn btn--sm">Wiederherstellen</button>
          </form>
        <?php endif; ?>
      </div>

    </section>

    <aside class="pages-edit-right">

      <div class="pages-edit-card">
        <div class="pages-edit-card-title">Status</div>
        <div class="pages-edit-card-sub">Steuert Veröffentlichung im Frontend.</div>

        <div class="pages-edit-fields">
          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Veröffentlicht</div>

            <?php if ($canStatusEdit && $canSave): ?>
              <select class="pages-edit-input" name="status">
                <option value="live"  <?= $status === 'live' ? 'selected' : '' ?>>Live</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Entwurf</option>
              </select>
              <div class="pages-edit-field-hint">„Entwurf“ bleibt im Frontend unsichtbar.</div>
            <?php else: ?>
              <div class="pages-hint">
                Status: <strong><?= h($statusLabel) ?></strong>
              </div>
              <?php if ($canSave && !$canStatusEdit): ?>
                <div class="pages-edit-field-hint">Du darfst den Status nicht ändern.</div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="pages-edit-card">
        <div class="pages-edit-card-title">Startseite</div>
        <div class="pages-edit-card-sub">Es darf genau eine Startseite geben.</div>

        <div class="pages-edit-fields">
          <label class="pages-edit-check">
            <input type="checkbox" name="is_home" value="1" <?= $isHome ? 'checked' : '' ?> <?= $canSave ? '' : 'disabled' ?>>
            <span>Diese Seite ist die Startseite</span>
          </label>
          <?php if (!$canSave): ?>
            <input type="hidden" name="is_home" value="<?= $isHome ? '1' : '0' ?>">
          <?php endif; ?>
        </div>
      </div>

      <div class="pages-edit-card">
        <div class="pages-edit-card-title">Navigation</div>
        <div class="pages-edit-card-sub">Sichtbarkeit, Bereich, Label & Position.</div>

        <div class="pages-edit-fields">
          <label class="pages-edit-check">
            <input type="checkbox" name="nav_visible" value="1" <?= $navVisible ? 'checked' : '' ?> <?= $canSave ? '' : 'disabled' ?>>
            <span>In Navigation anzeigen</span>
          </label>
          <?php if (!$canSave): ?>
            <input type="hidden" name="nav_visible" value="<?= $navVisible ? '1' : '0' ?>">
          <?php endif; ?>

          <div class="pages-edit-grid2 pages-edit-grid2--compact">
            <div class="pages-edit-field">
              <div class="pages-edit-field-label">Bereich</div>
              <select class="pages-edit-input" name="nav_area" <?= $canSave ? '' : 'disabled' ?>>
                <option value="header" <?= $navArea === 'header' ? 'selected' : '' ?>>Header</option>
                <option value="footer" <?= $navArea === 'footer' ? 'selected' : '' ?>>Footer</option>
                <option value="both"   <?= $navArea === 'both'   ? 'selected' : '' ?>>Beides</option>
              </select>
              <?php if (!$canSave): ?>
                <input type="hidden" name="nav_area" value="<?= h($navArea) ?>">
              <?php endif; ?>
            </div>

            <div class="pages-edit-field">
              <div class="pages-edit-field-label">Position</div>
              <input class="pages-edit-input" type="number" name="nav_order" value="<?= (int)$navOrder ?>" min="0" step="1" <?= $canSave ? '' : 'readonly' ?>>
            </div>
          </div>

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Label</div>
            <input class="pages-edit-input" type="text" name="nav_label" value="<?= h($navLabel) ?>" placeholder="z.B. Kontakt" <?= $canSave ? '' : 'readonly' ?>>
            <div class="pages-edit-field-hint">Pflicht, wenn „In Navigation anzeigen“ aktiv ist.</div>
          </div>
        </div>
      </div>

      <div class="pages-edit-card">
        <div class="pages-edit-card-title">SEO</div>
        <div class="pages-edit-card-sub">Overrides für diese Seite. Leer = globaler Default aus Einstellungen.</div>

        <div class="pages-edit-fields">

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Meta-Titel</div>
            <input class="pages-edit-input" type="text" name="seo_meta_title" value="<?= h($seoMetaTitle) ?>" placeholder="Aus Seitentitel" <?= $canSave ? '' : 'readonly' ?>>
          </div>

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Meta-Description</div>
            <textarea class="pages-edit-textarea" name="seo_meta_description" rows="3" placeholder="Aus globalem Default" <?= $canSave ? '' : 'readonly' ?>><?= h($seoMetaDesc) ?></textarea>
          </div>

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Robots</div>
            <select class="pages-edit-input" name="seo_robots" <?= $canSave ? '' : 'disabled' ?>>
              <option value="">— globaler Default —</option>
              <?php foreach (['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'] as $rv): ?>
                <option value="<?= h($rv) ?>" <?= $seoRobots === $rv ? 'selected' : '' ?>><?= h($rv) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (!$canSave): ?>
              <input type="hidden" name="seo_robots" value="<?= h($seoRobots) ?>">
            <?php endif; ?>
          </div>

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Canonical URL</div>
            <input class="pages-edit-input" type="text" name="seo_canonical_url" value="<?= h($seoCanonical) ?>" placeholder="Leer = auto" <?= $canSave ? '' : 'readonly' ?>>
          </div>

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">OG-Titel</div>
            <input class="pages-edit-input" type="text" name="seo_og_title" value="<?= h($seoOgTitle) ?>" placeholder="Leer = Meta-Titel" <?= $canSave ? '' : 'readonly' ?>>
          </div>

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">OG-Description</div>
            <textarea class="pages-edit-textarea" name="seo_og_description" rows="2" placeholder="Leer = Meta-Description" <?= $canSave ? '' : 'readonly' ?>><?= h($seoOgDesc) ?></textarea>
          </div>

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">OG-Bild (URL)</div>
            <input class="pages-edit-input" type="text" name="seo_og_image_url" value="<?= h($seoOgImage) ?>" placeholder="https://..." <?= $canSave ? '' : 'readonly' ?>>
          </div>

        </div>
      </div>

      <?php if ($id > 0): ?>
      <div class="pages-edit-card">
        <div class="pages-edit-card-title">Versionen</div>
        <div class="pages-edit-card-sub">Letzte 20 Revisionen, automatische Historie.</div>
        <div class="pages-edit-fields">
          <?php if (!$revisions): ?>
            <div class="pages-edit-field-hint">Noch keine Revisionen vorhanden.</div>
          <?php else: ?>
            <?php foreach ($revisions as $rev): ?>
              <?php
                $rid = (int)($rev['id'] ?? 0);
                if ($rid <= 0) continue;
                $createdAt = (string)($rev['created_at'] ?? '');
              ?>
              <div class="pages-edit-field">
                <div class="pages-edit-field-label">#<?= (int)$rid ?> · <?= h($createdAt) ?></div>
                <div class="pages-edit-field-hint"><?= h((string)($rev['title'] ?? '')) ?></div>
                <div class="pages-edit-pb-actions">
                  <a class="btn btn--ghost btn--sm" href="/pages/edit?id=<?= (int)$id ?>&revision=<?= (int)$rid ?>">Vorschau</a>
                  <?php if ($canSave): ?>
                    <button type="submit" class="btn btn--ghost btn--sm" name="restore_revision_id" value="<?= (int)$rid ?>">Diese Version wiederherstellen</button>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

          <?php if (is_array($selectedRevision)): ?>
            <hr class="pages-edit-sep">
            <div class="pages-edit-field-label">Vorschau Revision #<?= (int)($selectedRevision['id'] ?? 0) ?></div>
            <textarea class="pages-edit-textarea" rows="8" readonly><?= h((string)($selectedRevision['content_json'] ?? '')) ?></textarea>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

    </aside>

  </div>
</form>

<link rel="stylesheet" href="https://cdn.quilljs.com/1.3.6/quill.snow.css">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
(() => {
  const defs = <?= $defsJson ?>;
  const CAN_EDIT = <?= $canSave ? 'true' : 'false' ?>;
  const BLOCK_LABELS = {
    text: 'Textblock',
    hero: 'Herobanner',
    image: 'Bild',
    columns: 'Spalten',
    cta: 'Call-to-Action',
    faq: 'FAQ',
    video: 'Video',
    gallery: 'Galerie',
  };
  function blockLabel(type) {
    const t = String(type || '').trim();
    if (t in BLOCK_LABELS) return BLOCK_LABELS[t];
    if (defs[t] && typeof defs[t].label === 'string' && defs[t].label.trim() !== '') {
      return defs[t].label.trim();
    }
    return t;
  }
  let activeMediaPickerInput = null;
  let sortableInstance = null;
  const historyStack = [];
  let historyIndex = -1;

  function safeParseJson(s) { try { return JSON.parse(s); } catch { return null; } }
  function ensureModel(model) {
    if (!model || typeof model !== 'object') model = {};
    if (Array.isArray(model)) model = {blocks: model};
    if (!Array.isArray(model.blocks)) model.blocks = [];
    return model;
  }
  function uuid() { return 'b' + Math.random().toString(16).slice(2) + Date.now().toString(16); }

  const contentInput = document.getElementById('contentJsonInput');
  const rawTextarea  = document.getElementById('rawJsonTextarea');
  const container    = document.getElementById('blocksContainer');
  const previewFrame = document.getElementById('pageBuilderPreview');
  const titleInput   = document.getElementById('pageTitleInput');
  const slugInput    = document.getElementById('pageSlugInput');
  const slugLiveHint = document.getElementById('pageSlugLiveHint');
  const undoBtn      = document.getElementById('pbUndoBtn');
  const redoBtn      = document.getElementById('pbRedoBtn');
  const form         = document.getElementById('pageEditForm');

  let model = ensureModel(
    safeParseJson(contentInput.value) ||
    safeParseJson(rawTextarea ? rawTextarea.value : '') ||
    {blocks: []}
  );

  model.blocks = model.blocks
    .filter(b => b && typeof b === 'object')
    .map(b => {
      const type = (typeof b.type === 'string' ? b.type : '');
      // Unterstützt beide Formate: {type, data:{...}} und flach {type, field1, field2}
      let data;
      if (b.data && typeof b.data === 'object') {
        data = b.data;
      } else {
        data = Object.assign({}, b);
        delete data.type;
        delete data.id;
      }
      return { id: (typeof b.id === 'string' && b.id) ? b.id : uuid(), type, data };
    })
    .filter(b => defs[b.type]);

  function snapshot() {
    return JSON.stringify({ blocks: model.blocks.map(b => ({ id: b.id, type: b.type, data: Object.assign({}, b.data) })) });
  }

  function pushHistory() {
    const snap = snapshot();
    if (historyIndex >= 0 && historyStack[historyIndex] === snap) return;
    if (historyIndex < historyStack.length - 1) {
      historyStack.splice(historyIndex + 1);
    }
    historyStack.push(snap);
    if (historyStack.length > 50) {
      historyStack.shift();
    }
    historyIndex = historyStack.length - 1;
    updateHistoryButtons();
  }

  function applyHistory(idx) {
    if (idx < 0 || idx >= historyStack.length) return;
    const parsed = safeParseJson(historyStack[idx]);
    if (!parsed || !Array.isArray(parsed.blocks)) return;
    model.blocks = parsed.blocks.map((b) => ({
      id: (typeof b.id === 'string' && b.id) ? b.id : uuid(),
      type: String(b.type || ''),
      data: (b.data && typeof b.data === 'object') ? b.data : {},
    })).filter((b) => defs[b.type]);
    historyIndex = idx;
    render();
    serialize(false);
    updateHistoryButtons();
  }

  function updateHistoryButtons() {
    if (undoBtn) undoBtn.disabled = !CAN_EDIT || historyIndex <= 0;
    if (redoBtn) redoBtn.disabled = !CAN_EDIT || historyIndex >= historyStack.length - 1;
  }

  function serialize(pushToHistory = true) {
    const out = { blocks: model.blocks.map(b => Object.assign({ type: b.type }, b.data)) };
    const json = JSON.stringify(out);
    contentInput.value = json;
    if (rawTextarea) rawTextarea.value = json;
    updatePreview();
    if (pushToHistory) pushHistory();
  }

  function el(tag, attrs = {}, children = []) {
    const n = document.createElement(tag);
    Object.entries(attrs).forEach(([k,v]) => {
      if (k === 'class') n.className = v;
      else if (k === 'html') n.innerHTML = v;
      else n.setAttribute(k, v);
    });
    children.forEach(c => n.appendChild(c));
    return n;
  }

  function openMediaPicker(input) {
    if (!CAN_EDIT) return;
    activeMediaPickerInput = input;
    const picker = window.open('/media?picker=1', 'mediapicker', 'width=900,height=600');
    if (picker && typeof picker.focus === 'function') {
      picker.focus();
    }
  }

  window.addEventListener('message', (ev) => {
    const data = (ev && ev.data && typeof ev.data === 'object') ? ev.data : null;
    if (!data || data.type !== 'media_picked') return;
    if (!activeMediaPickerInput) return;

    const url = (typeof data.url === 'string') ? data.url.trim() : '';
    if (url === '') return;

    activeMediaPickerInput.value = url;
    activeMediaPickerInput.dispatchEvent(new Event('input', { bubbles: true }));
  });

  function esc(s) {
    return String(s ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#39;');
  }

  function previewBlockHtml(block) {
    const data = block && block.data ? block.data : {};
    if (block.type === 'hero') {
      const bg = String(data.image_url || '').trim();
      const style = bg ? ` style="background-image:url('${esc(bg)}');background-size:cover;background-position:center;"` : '';
      return `<section class="pv-hero"${style}><h1>${esc(data.headline || '')}</h1><p>${esc(data.subtitle || '')}</p></section>`;
    }
    if (block.type === 'image') {
      return `<section class="pv-card"><img src="${esc(data.url || '')}" alt="${esc(data.alt || '')}"></section>`;
    }
    if (block.type === 'text') {
      const title = String(data.title || '').trim();
      const subtitle = String(data.subtitle || '').trim();
      const richText = String(data.text || '').trim();
      const imageUrl = String(data.image_url || '').trim();
      return `<section class="pv-card">${title ? `<h2>${esc(title)}</h2>` : ''}${subtitle ? `<p class="pv-sub">${esc(subtitle)}</p>` : ''}${imageUrl ? `<img src="${esc(imageUrl)}" alt="">` : ''}<div class="pv-rich">${richText}</div></section>`;
    }
    if (block.type === 'columns') {
      return `<section class="pv-card"><h3>${esc(data.headline || 'Spalten')}</h3><div class="pv-cols"><div><strong>${esc(data.col_1_title || '')}</strong><p>${esc(data.col_1_text || '')}</p></div><div><strong>${esc(data.col_2_title || '')}</strong><p>${esc(data.col_2_text || '')}</p></div></div></section>`;
    }
    if (block.type === 'cta') {
      return `<section class="pv-card pv-cta"><h3>${esc(data.headline || '')}</h3><p>${esc(data.text || '')}</p><a href="${esc(data.button_url || '#')}" class="pv-btn">${esc(data.button_text || 'Mehr')}</a></section>`;
    }
    if (block.type === 'faq') {
      return `<section class="pv-card"><h3>${esc(data.headline || 'FAQ')}</h3><pre>${esc(data.items_json || '')}</pre></section>`;
    }
    if (block.type === 'video') {
      return `<section class="pv-card"><h3>${esc(data.headline || 'Video')}</h3><p>${esc(data.video_url || data.video_id || '')}</p></section>`;
    }
    if (block.type === 'gallery') {
      return `<section class="pv-card"><h3>${esc(data.headline || 'Galerie')}</h3><pre>${esc(data.items_json || '')}</pre></section>`;
    }
    return `<section class="pv-card"><pre>${esc(JSON.stringify(data, null, 2))}</pre></section>`;
  }

  function updatePreview() {
    if (!previewFrame) return;
    const blocksHtml = model.blocks.map(previewBlockHtml).join('');
    previewFrame.srcdoc = `<!doctype html><html lang="de"><head><meta charset="utf-8"><style>
      body{font-family:system-ui,-apple-system,Segoe UI,sans-serif;margin:0;padding:16px;background:#f7f9fc;color:#0f172a}
      .pv-hero{padding:36px 20px;border-radius:14px;background:#0f172a;color:#fff;margin-bottom:14px}
      .pv-card{background:#fff;border:1px solid #dce4ef;border-radius:12px;padding:14px;margin-bottom:12px}
      .pv-card img{max-width:100%;border-radius:10px}
      .pv-sub{color:#64748b}
      .pv-cols{display:grid;grid-template-columns:1fr 1fr;gap:12px}
      .pv-rich{line-height:1.6}
      .pv-btn{display:inline-block;padding:8px 12px;background:#0f172a;color:#fff;border-radius:8px;text-decoration:none}
      pre{white-space:pre-wrap}
      @media (max-width:900px){.pv-cols{grid-template-columns:1fr}}
    </style></head><body>${blocksHtml || '<p>Keine Blöcke vorhanden.</p>'}</body></html>`;
  }

  function syncModelFromDomOrder() {
    const order = Array.from(container.querySelectorAll('[data-block-id]'))
      .map((el) => el.getAttribute('data-block-id'))
      .filter((id) => typeof id === 'string' && id !== '');
    if (!order.length) return;
    const byId = new Map(model.blocks.map((b) => [b.id, b]));
    const reordered = order.map((id) => byId.get(id)).filter(Boolean);
    if (reordered.length === model.blocks.length) {
      model.blocks = reordered;
      serialize();
    }
  }

  function ensureSortable() {
    if (!CAN_EDIT || !container || typeof Sortable === 'undefined') return;
    if (sortableInstance) return;
    sortableInstance = Sortable.create(container, {
      animation: 150,
      handle: '.pages-edit-card-head',
      draggable: '.pages-edit-blockcard',
      onEnd: () => syncModelFromDomOrder(),
    });
  }

  function slugifyLikeServer(input) {
    let s = String(input || '').trim().toLowerCase();
    s = s.replaceAll('ä', 'ae').replaceAll('ö', 'oe').replaceAll('ü', 'ue').replaceAll('ß', 'ss');
    s = s.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    if (s === '' || s === 'home' || s === 'startseite') s = 'home';
    return '/' + s;
  }

  function updateSlugHint() {
    if (!slugLiveHint || !titleInput) return;
    const generated = slugifyLikeServer(titleInput.value || '');
    slugLiveHint.textContent = 'URL wird: ' + generated;
  }

  function renderField(block, key, rule) {
    const labelText = (rule && rule.label) ? rule.label : key;
    const hintText  = (rule && rule.hint) ? rule.hint : '';

    const fieldWrap = el('div', {class: 'pages-edit-field'});
    fieldWrap.appendChild(el('div', {class: 'pages-edit-field-label', html: labelText}));

    const control = (rule && rule.control) ? rule.control : 'input';
    const rows = (rule && rule.rows) ? String(rule.rows) : '6';
    const enumVals = (rule && Array.isArray(rule.enum)) ? rule.enum : null;

    let val = '';
    if (block.data && typeof block.data[key] === 'string') val = block.data[key];
    else if (defs[block.type] && defs[block.type].defaults && typeof defs[block.type].defaults[key] === 'string') val = defs[block.type].defaults[key];

    let input;

    if (key === 'image_url') {
      input = el('input', {class: 'pages-edit-input', type: 'text', placeholder: '/media/file?id=123'});
      input.value = val;
      input.readOnly = true;
      input.addEventListener('input', () => {
        if (!CAN_EDIT) return;
        block.data[key] = input.value;
        serialize();
      });

      const pickerActions = el('div', {class: 'pages-edit-pb-actions'});
      const pickBtn = el('button', {type: 'button', class: 'btn btn--ghost btn--sm', html: 'Bild auswählen'});
      pickBtn.disabled = !CAN_EDIT;
      pickBtn.addEventListener('click', () => openMediaPicker(input));
      pickerActions.appendChild(pickBtn);

      if (CAN_EDIT) {
        const clearBtn = el('button', {type: 'button', class: 'btn btn--ghost btn--sm', html: 'Entfernen'});
        clearBtn.addEventListener('click', () => {
          input.value = '';
          input.dispatchEvent(new Event('input', { bubbles: true }));
        });
        pickerActions.appendChild(clearBtn);
      }

      fieldWrap.appendChild(input);
      fieldWrap.appendChild(pickerActions);
    } else if (control === 'select' && enumVals) {
      input = el('select', {class: 'pages-edit-input'});
      enumVals.forEach(opt => {
        const o = document.createElement('option');
        o.value = String(opt);
        o.textContent = String(opt);
        if (String(opt) === String(val)) o.selected = true;
        input.appendChild(o);
      });
      input.disabled = !CAN_EDIT;
      input.addEventListener('change', () => {
        if (!CAN_EDIT) return;
        block.data[key] = input.value;
        serialize();
      });
    } else if (control === 'textarea') {
      if (block.type === 'text' && key === 'text' && typeof Quill !== 'undefined') {
        input = el('div');
        const editor = el('div', {class: 'pages-edit-wysiwyg'});
        input.appendChild(editor);
        const q = new Quill(editor, {
          theme: 'snow',
          modules: { toolbar: [['bold', 'italic', 'underline'], [{header: [2, 3, false]}], [{list: 'ordered'}, {list: 'bullet'}], ['link']] },
        });
        q.root.innerHTML = val;
        if (!CAN_EDIT) q.enable(false);
        q.on('text-change', () => {
          if (!CAN_EDIT) return;
          block.data[key] = q.root.innerHTML;
          serialize();
        });
      } else {
        input = el('textarea', {class: 'pages-edit-textarea', rows});
        input.value = val;
        input.readOnly = !CAN_EDIT;
        input.addEventListener('input', () => {
          if (!CAN_EDIT) return;
          block.data[key] = input.value;
          serialize();
        });
      }
    } else {
      input = el('input', {class: 'pages-edit-input', type: 'text'});
      input.value = val;
      input.readOnly = !CAN_EDIT;
      input.addEventListener('input', () => {
        if (!CAN_EDIT) return;
        block.data[key] = input.value;
        serialize();
      });
    }

    if (key !== 'image_url') {
      fieldWrap.appendChild(input);
    }
    if (hintText) fieldWrap.appendChild(el('div', {class: 'pages-edit-field-hint', html: hintText}));
    return fieldWrap;
  }

  function renderBlockFields(block) {
    const def = defs[block.type];
    const fields = def.fields || {};
    const wrapper = el('div', {class: 'pages-edit-fields'});
    Object.keys(fields).forEach(key => wrapper.appendChild(renderField(block, key, fields[key])));
    return wrapper;
  }

  function render() {
    container.innerHTML = '';

    if (model.blocks.length === 0) {
      container.appendChild(el('div', {class: 'pages-edit-field-hint', html: 'Noch keine Blöcke. Füge oben einen Block hinzu.'}));
      serialize();
      return;
    }

    model.blocks.forEach((block, idx) => {
      const head = el('div', {class: 'pages-edit-card-head'});

      const left = el('div', {class: 'pages-edit-blockhead-left'});
      const displayLabel = blockLabel(block.type);
      left.appendChild(el('strong', {html: displayLabel}));
      left.appendChild(el('span', {class: 'pages-edit-blockhead-meta', html: `(${block.type})`}));

      const right = el('div', {class: 'pages-edit-blockhead-actions'});

      if (CAN_EDIT) {
        const upBtn  = el('button', {type:'button', class:'btn btn--ghost btn--sm', html:'↑'});
        const dnBtn  = el('button', {type:'button', class:'btn btn--ghost btn--sm', html:'↓'});
        const delBtn = el('button', {type:'button', class:'btn btn--ghost btn--danger btn--sm', html:'Löschen'});

        upBtn.addEventListener('click', () => {
          if (idx <= 0) return;
          const tmp = model.blocks[idx - 1];
          model.blocks[idx - 1] = model.blocks[idx];
          model.blocks[idx] = tmp;
          render();
          serialize();
        });

        dnBtn.addEventListener('click', () => {
          if (idx >= model.blocks.length - 1) return;
          const tmp = model.blocks[idx + 1];
          model.blocks[idx + 1] = model.blocks[idx];
          model.blocks[idx] = tmp;
          render();
          serialize();
        });

        delBtn.addEventListener('click', () => {
          model.blocks.splice(idx, 1);
          render();
          serialize();
        });

        right.appendChild(upBtn);
        right.appendChild(dnBtn);
        right.appendChild(delBtn);
      } else {
        right.appendChild(el('div', {class:'pages-hint', html:'read-only'}));
      }

      head.appendChild(left);
      head.appendChild(right);

      const card = el('div', {class: 'pages-edit-card pages-edit-blockcard', 'data-block-id': block.id});
      card.appendChild(head);
      card.appendChild(renderBlockFields(block));

      container.appendChild(card);
    });

    serialize(false);
    ensureSortable();
  }

  document.querySelectorAll('[data-add-block]').forEach(btn => {
    btn.addEventListener('click', () => {
      if (!CAN_EDIT) return;
      const type = btn.getAttribute('data-add-block');
      if (!defs[type]) return;

      const defaults = defs[type].defaults || {};
      model.blocks.push({
        id: uuid(),
        type,
        data: Object.assign({}, defaults)
      });

      render();
      serialize(true);
    });
  });

  if (undoBtn) {
    undoBtn.addEventListener('click', () => {
      if (!CAN_EDIT) return;
      applyHistory(historyIndex - 1);
    });
  }
  if (redoBtn) {
    redoBtn.addEventListener('click', () => {
      if (!CAN_EDIT) return;
      applyHistory(historyIndex + 1);
    });
  }

  document.addEventListener('keydown', (e) => {
    if (!CAN_EDIT) return;
    if (!(e.ctrlKey || e.metaKey)) return;
    const k = String(e.key || '').toLowerCase();
    if (k === 'z' && !e.shiftKey) {
      e.preventDefault();
      applyHistory(historyIndex - 1);
      return;
    }
    if (k === 'y' || (k === 'z' && e.shiftKey)) {
      e.preventDefault();
      applyHistory(historyIndex + 1);
    }
  });

  if (titleInput) {
    titleInput.addEventListener('input', updateSlugHint);
    updateSlugHint();
  }

  form.addEventListener('submit', () => serialize());
  render();
  pushHistory();
  updateHistoryButtons();
})();
</script>
