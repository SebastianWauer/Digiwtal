<?php
declare(strict_types=1);

/** @var array $page */
/** @var ?array $flash */
/** @var array $seoOverride */
/** @var array $revisions */
/** @var ?array $selectedRevision */
/** @var array $navCandidates */

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
$navPlaceMode = (string)($page['_nav_place_mode'] ?? 'after');
if (!in_array($navPlaceMode, ['before', 'after'], true)) $navPlaceMode = 'after';
$navPlaceRef = (int)($page['_nav_place_ref'] ?? 0);

// Meta (008_pages_meta.sql)
$frontendTitle = (string)($page['frontend_title'] ?? '');
$subtitle      = (string)($page['subtitle'] ?? '');
$status        = (string)($page['status'] ?? 'live');
if (!in_array($status, ['live','draft'], true)) $status = 'live';

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
$navCandidates = is_array($navCandidates ?? null) ? $navCandidates : [];
?>
<form method="post" action="/pages/save" class="pages-edit-form" id="pageEditForm">
  <?= admin_csrf_field() ?>
  <input type="hidden" name="id" id="pageIdInput" value="<?= (int)$id ?>">
  <input type="hidden" name="content_json" id="contentJsonInput" value="<?= h($content) ?>">

  <?php if ($canSave && !$canStatusEdit): ?>
    <!-- Status mitschicken, damit Save NICHT versucht auf "live" zu springen -->
    <input type="hidden" name="status" value="<?= h($status) ?>">
  <?php endif; ?>

  <nav class="pages-edit-quicknav" aria-label="Bereichsnavigation">
    <a href="#section-content">Inhalt</a>
    <a href="#section-builder">PageBuilder</a>
    <button type="button" data-open-modal="navigation">Navigation</button>
    <button type="button" data-open-modal="seo">SEO</button>
    <?php if ($id > 0): ?><button type="button" data-open-modal="revisions">Versionen</button><?php endif; ?>
  </nav>

  <div class="pages-edit-layout">

    <section class="pages-edit-left">

      <div class="pages-edit-card" id="section-content">

        <div class="pages-edit-fields">
          <div class="pages-edit-meta-preview">
            <div class="pages-edit-meta-preview__meta">
          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Titel (intern)</div>
            <input id="pageTitleInput" class="pages-edit-input" type="text" name="title" value="<?= h($title) ?>" required <?= $canSave ? '' : 'readonly' ?>>
            <div class="pages-edit-field-hint">Technischer Titel im CMS (z.B. „Kontaktseite“).</div>
          </div>

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

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Slug</div>
            <input id="pageSlugInput" class="pages-edit-input" type="text" name="slug" value="<?= h($slug) ?>" placeholder="/Titel" <?= $canSave ? '' : 'readonly' ?>>
            <div class="pages-edit-field-hint">
              Optional. Wenn leer, wird der Slug automatisch aus dem Titel erzeugt.
              Beispiel: <code>/kontakt</code>
            </div>
            <div class="pages-edit-field-hint pages-edit-slug-live" id="pageSlugLiveHint"></div>
          </div>

          <div class="pages-edit-grid2 pages-edit-grid2--compact" id="section-status">
            <div class="pages-edit-field">
              <div class="pages-edit-field-label">Veröffentlicht</div>
              <?php if ($canStatusEdit && $canSave): ?>
                <label class="pages-edit-switch pages-edit-switch--status">
                  <input type="checkbox" name="status_toggle" value="1" <?= $status === 'live' ? 'checked' : '' ?>>
                  <span class="pages-edit-switch__slider"></span>
                  <span class="pages-edit-switch__label"><?= $status === 'live' ? 'Live' : 'Entwurf' ?></span>
                </label>
                <input type="hidden" name="status" id="pageStatusHidden" value="<?= h($status) ?>">
              <?php else: ?>
                <div class="pages-hint">Status: <strong><?= h($statusLabel) ?></strong></div>
                <input type="hidden" name="status" value="<?= h($status) ?>">
              <?php endif; ?>
            </div>

            <div class="pages-edit-field" id="section-home">
              <div class="pages-edit-field-label">Startseite</div>
              <label class="pages-edit-switch pages-edit-switch--home">
                <input type="checkbox" name="is_home" value="1" <?= $isHome ? 'checked' : '' ?> <?= $canSave ? '' : 'disabled' ?>>
                <span class="pages-edit-switch__slider"></span>
                <span class="pages-edit-switch__label"><?= $isHome ? 'Ja' : 'Nein' ?></span>
              </label>
              <?php if (!$canSave): ?>
                <input type="hidden" name="is_home" value="<?= $isHome ? '1' : '0' ?>">
              <?php endif; ?>
            </div>
          </div>

            </div>
            <div class="pages-edit-meta-preview__preview">
              <iframe id="pageBuilderPreview" class="pages-edit-preview-frame" title="PageBuilder Vorschau"></iframe>
            </div>
          </div>

          <hr class="pages-edit-sep">

          <div class="pages-edit-card-title pages-edit-pb-title" id="section-builder">PageBuilder</div>
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

          <div id="blocksContainer"></div>

          <details class="pages-edit-raw">
            <summary class="pages-edit-raw__summary">Raw JSON anzeigen</summary>
            <textarea class="pages-edit-textarea pages-edit-raw__textarea" id="rawJsonTextarea" rows="10" <?= $canSave ? '' : 'readonly' ?>><?= h($content) ?></textarea>
            <div class="pages-edit-field-hint">Nur Debug. Gespeichert wird der PageBuilder-Stand.</div>
          </details>

        </div>
      </div>

    </section>
  </div>

      <div class="pages-edit-card pages-edit-modal" id="modal-navigation-card">
        <button type="button" class="pages-edit-modal__close" data-close-modal="navigation" aria-label="Schließen">×</button>
        <details class="pages-edit-collapsible" open>
          <summary class="pages-edit-collapsible__summary">
            <span class="pages-edit-card-title">Navigation</span>
            <span class="pages-edit-collapsible__meta">Sichtbarkeit & Reihenfolge</span>
          </summary>
          <div class="pages-edit-card-sub">Sichtbarkeit, Bereich, Label und Position dieser Seite.</div>
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
                <div class="pages-edit-field-label">Einordnen</div>
                <div class="pages-edit-grid2 pages-edit-grid2--compact">
                  <select class="pages-edit-input" name="nav_place_mode" <?= $canSave ? '' : 'disabled' ?>>
                    <option value="before" <?= $navPlaceMode === 'before' ? 'selected' : '' ?>>vor</option>
                    <option value="after" <?= $navPlaceMode === 'after' ? 'selected' : '' ?>>hinter</option>
                  </select>
                  <select class="pages-edit-input" name="nav_place_ref" <?= $canSave ? '' : 'disabled' ?>>
                    <option value="0">am Ende</option>
                    <?php foreach ($navCandidates as $cand): ?>
                      <?php
                        if (!is_array($cand)) continue;
                        $cid = (int)($cand['id'] ?? 0);
                        if ($cid <= 0 || $cid === (int)$id) continue;
                        if ((int)($cand['nav_visible'] ?? 0) !== 1) continue;
                        $clabel = (string)($cand['nav_label'] ?? '');
                        if ($clabel === '') $clabel = (string)($cand['title'] ?? ('Seite #' . $cid));
                      ?>
                      <option value="<?= $cid ?>" <?= $navPlaceRef === $cid ? 'selected' : '' ?>><?= h($clabel) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="pages-edit-field-hint">Reihenfolge ohne Zahlenfeld: vor/hinter vorhandene Navigation setzen.</div>
              </div>
            </div>
            <input type="hidden" name="nav_order" value="<?= (int)$navOrder ?>">

            <div class="pages-edit-field">
              <div class="pages-edit-field-label">Label</div>
              <input class="pages-edit-input" type="text" name="nav_label" value="<?= h($navLabel) ?>" placeholder="z.B. Kontakt" <?= $canSave ? '' : 'readonly' ?>>
              <div class="pages-edit-field-hint">Pflicht, wenn „In Navigation anzeigen“ aktiv ist.</div>
            </div>

            <div class="pages-edit-field">
              <div class="pages-edit-field-label">Unterseite</div>
              <select class="pages-edit-input" disabled>
                <option>Keine (Feature folgt)</option>
              </select>
              <div class="pages-edit-field-hint">Hierarchie/Unterseiten sind aktuell noch nicht im Datenmodell vorhanden.</div>
            </div>
          </div>
        </details>
      </div>

      <div class="pages-edit-card pages-edit-modal" id="modal-seo-card">
        <button type="button" class="pages-edit-modal__close" data-close-modal="seo" aria-label="Schließen">×</button>
        <details class="pages-edit-collapsible" open>
          <summary class="pages-edit-collapsible__summary">
            <span class="pages-edit-card-title">SEO</span>
            <span class="pages-edit-collapsible__meta">Overrides pro Seite</span>
          </summary>
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
        </details>
      </div>

      <?php if ($id > 0): ?>
      <div class="pages-edit-card pages-edit-modal" id="modal-revisions-card">
        <button type="button" class="pages-edit-modal__close" data-close-modal="revisions" aria-label="Schließen">×</button>
        <details class="pages-edit-collapsible">
          <summary class="pages-edit-collapsible__summary">
            <span class="pages-edit-card-title">Versionen</span>
            <span class="pages-edit-collapsible__meta"><?= (int)count($revisions) ?> Einträge</span>
          </summary>
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
        </details>
      </div>
      <?php endif; ?>

  <div class="pages-edit-floating-actions" aria-label="Seitenaktionen">
    <?php if ($canSave): ?>
      <button type="submit" class="pages-edit-iconbtn pages-edit-iconbtn--save" title="Speichern" aria-label="Speichern">💾</button>
    <?php endif; ?>

    <a class="pages-edit-iconbtn pages-edit-iconbtn--cancel" href="/pages" title="Abbrechen" aria-label="Abbrechen">✕</a>

    <?php if ($id > 0 && !$deleted && $canDelete): ?>
      <button
        type="submit"
        class="pages-edit-iconbtn pages-edit-iconbtn--delete"
        formaction="/pages/delete"
        formmethod="post"
        name="id"
        value="<?= (int)$id ?>"
        title="Löschen"
        aria-label="Löschen"
      >🗑</button>
    <?php endif; ?>

    <?php if ($id > 0 && $deleted && $canRestore): ?>
      <button
        type="submit"
        class="pages-edit-iconbtn pages-edit-iconbtn--restore"
        formaction="/pages/restore"
        formmethod="post"
        name="id"
        value="<?= (int)$id ?>"
        title="Wiederherstellen"
        aria-label="Wiederherstellen"
      >↺</button>
    <?php endif; ?>
  </div>

  <div class="pages-edit-modal-backdrop" id="pagesEditModalBackdrop" hidden></div>

</form>

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
  const collapsedBlocks = new Set();

  function safeParseJson(s) { try { return JSON.parse(s); } catch { return null; } }
  function ensureModel(model) {
    if (!model || typeof model !== 'object') model = {};
    if (Array.isArray(model)) model = {blocks: model};
    if (!Array.isArray(model.blocks)) model.blocks = [];
    return model;
  }
  function uuid() { return 'b' + Math.random().toString(16).slice(2) + Date.now().toString(16); }

  const contentInput = document.getElementById('contentJsonInput');
  const pageIdInput  = document.getElementById('pageIdInput');
  const rawTextarea  = document.getElementById('rawJsonTextarea');
  const container    = document.getElementById('blocksContainer');
  const previewFrame = document.getElementById('pageBuilderPreview');
  const titleInput   = document.getElementById('pageTitleInput');
  const slugInput    = document.getElementById('pageSlugInput');
  const slugLiveHint = document.getElementById('pageSlugLiveHint');
  const undoBtn      = document.getElementById('pbUndoBtn');
  const redoBtn      = document.getElementById('pbRedoBtn');
  const form         = document.getElementById('pageEditForm');
  const quickNavLinks = Array.from(document.querySelectorAll('.pages-edit-quicknav a[href^="#"]'));
  const openModalBtns = Array.from(document.querySelectorAll('[data-open-modal]'));
  const closeModalBtns = Array.from(document.querySelectorAll('[data-close-modal]'));
  const modalBackdrop = document.getElementById('pagesEditModalBackdrop');
  const navigationModal = document.getElementById('modal-navigation-card');
  const seoModal = document.getElementById('modal-seo-card');
  const revisionsModal = document.getElementById('modal-revisions-card');
  const statusToggle = document.querySelector('input[name="status_toggle"]');
  const statusHidden = document.getElementById('pageStatusHidden');
  const homeToggle = document.querySelector('input[name="is_home"]');
  let previewDebounceTimer = null;
  let previewRequestId = 0;

  quickNavLinks.forEach((link) => {
    link.addEventListener('click', (ev) => {
      const href = link.getAttribute('href') || '';
      if (!href.startsWith('#')) return;
      const targetId = href.slice(1);
      const target = document.getElementById(targetId);
      if (!target) return;
      ev.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      if (typeof history.replaceState === 'function') {
        history.replaceState(null, '', href);
      }
    });
  });

  function modalByName(name) {
    if (name === 'navigation') return navigationModal;
    if (name === 'seo') return seoModal;
    if (name === 'revisions') return revisionsModal;
    return null;
  }

  function closeAllModals() {
    [navigationModal, seoModal, revisionsModal].forEach((m) => m && m.classList.remove('is-open'));
    if (modalBackdrop) {
      modalBackdrop.classList.remove('is-open');
      modalBackdrop.hidden = true;
    }
  }

  function openModal(name) {
    const modal = modalByName(name);
    if (!modal) return;
    closeAllModals();
    modal.classList.add('is-open');
    if (modalBackdrop) {
      modalBackdrop.hidden = false;
      modalBackdrop.classList.add('is-open');
    }
  }

  openModalBtns.forEach((btn) => {
    btn.addEventListener('click', (ev) => {
      ev.preventDefault();
      const name = btn.getAttribute('data-open-modal') || '';
      openModal(name);
    });
  });

  closeModalBtns.forEach((btn) => {
    btn.addEventListener('click', (ev) => {
      ev.preventDefault();
      closeAllModals();
    });
  });

  if (modalBackdrop) {
    modalBackdrop.addEventListener('click', closeAllModals);
  }

  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape') closeAllModals();
  });

  function updateSwitchLabel(inputEl) {
    if (!inputEl) return;
    const wrapper = inputEl.closest('.pages-edit-switch');
    const label = wrapper ? wrapper.querySelector('.pages-edit-switch__label') : null;
    if (!label) return;
    if (inputEl.name === 'status_toggle') {
      label.textContent = inputEl.checked ? 'Live' : 'Entwurf';
    } else if (inputEl.name === 'is_home') {
      label.textContent = inputEl.checked ? 'Ja' : 'Nein';
    }
  }

  if (statusToggle && statusHidden) {
    statusToggle.addEventListener('change', () => {
      statusHidden.value = statusToggle.checked ? 'live' : 'draft';
      updateSwitchLabel(statusToggle);
      schedulePreviewUpdate();
    });
    updateSwitchLabel(statusToggle);
  }

  if (homeToggle) {
    homeToggle.addEventListener('change', () => {
      updateSwitchLabel(homeToggle);
      schedulePreviewUpdate();
    });
    updateSwitchLabel(homeToggle);
  }

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

  model.blocks.forEach((b) => collapsedBlocks.add(b.id));

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
    schedulePreviewUpdate();
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

  function valueOf(name) {
    if (!form) return '';
    const el = form.querySelector(`[name="${name}"]`);
    if (!el) return '';
    if (el instanceof HTMLInputElement && el.type === 'checkbox') {
      return el.checked ? (el.value || '1') : '';
    }
    return String(el.value ?? '');
  }

  function schedulePreviewUpdate() {
    if (previewDebounceTimer) {
      clearTimeout(previewDebounceTimer);
    }
    previewDebounceTimer = setTimeout(() => {
      updatePreview();
    }, 220);
  }

  async function updatePreview() {
    if (!previewFrame) return;

    const requestId = ++previewRequestId;
    const payload = {
      id: pageIdInput ? Number(pageIdInput.value || '0') : 0,
      title: valueOf('title'),
      frontend_title: valueOf('frontend_title'),
      subtitle: valueOf('subtitle'),
      slug: valueOf('slug'),
      status: valueOf('status'),
      is_home: valueOf('is_home') !== '',
      nav_visible: valueOf('nav_visible') !== '',
      nav_label: valueOf('nav_label'),
      nav_area: valueOf('nav_area'),
      nav_place_mode: valueOf('nav_place_mode'),
      nav_place_ref: valueOf('nav_place_ref'),
      content_json: contentInput ? contentInput.value : '{"blocks":[]}',
      seo_meta_title: valueOf('seo_meta_title'),
      seo_meta_description: valueOf('seo_meta_description'),
      seo_robots: valueOf('seo_robots'),
      seo_canonical_url: valueOf('seo_canonical_url'),
      seo_og_title: valueOf('seo_og_title'),
      seo_og_description: valueOf('seo_og_description'),
      seo_og_image_url: valueOf('seo_og_image_url'),
    };

    try {
      const resp = await fetch('/pages/preview', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload),
        credentials: 'same-origin',
      });
      const html = await resp.text();
      if (requestId !== previewRequestId) return;
      if (!resp.ok || !html || !html.trim()) {
        const msg = `Vorschau konnte nicht geladen werden (HTTP ${resp.status}).`;
        previewFrame.srcdoc = `<!doctype html><html lang="de"><body style="font-family:system-ui;padding:16px;color:#111">${msg}</body></html>`;
        return;
      }
      previewFrame.srcdoc = html;
    } catch (err) {
      if (requestId !== previewRequestId) return;
      previewFrame.srcdoc = '<!doctype html><html lang="de"><body style="font-family:system-ui;padding:16px">Vorschau konnte nicht geladen werden.</body></html>';
    }
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
      handle: '.pages-edit-drag-handle',
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

  function keepSelectionOnToolbarClick(btn) {
    btn.addEventListener('mousedown', (e) => e.preventDefault());
  }

  function createToolbarButton(label, title, onClick, className = '') {
    const btn = el('button', { type: 'button', class: `pages-edit-rt-btn ${className}`.trim(), title, html: label });
    keepSelectionOnToolbarClick(btn);
    btn.addEventListener('click', onClick);
    return btn;
  }

  function createWordLikeEditor(initialHtml, onCommit) {
    const wrap = el('div', { class: 'pages-edit-richtext' });
    const toolbar = el('div', { class: 'pages-edit-richtext__toolbar' });
    const groups = [
      el('div', { class: 'pages-edit-rt-group' }),
      el('div', { class: 'pages-edit-rt-group' }),
      el('div', { class: 'pages-edit-rt-group' }),
      el('div', { class: 'pages-edit-rt-group' }),
    ];
    groups.forEach((g) => toolbar.appendChild(g));

    const surface = el('div', { class: 'pages-edit-richtext__surface' });
    surface.contentEditable = CAN_EDIT ? 'true' : 'false';
    surface.innerHTML = initialHtml || '';

    const htmlArea = el('textarea', { class: 'pages-edit-textarea pages-edit-richtext__html', rows: '10' });
    htmlArea.value = initialHtml || '';
    htmlArea.style.display = 'none';
    htmlArea.readOnly = !CAN_EDIT;

    let htmlMode = false;
    const commit = () => {
      const html = htmlMode ? htmlArea.value : surface.innerHTML;
      onCommit(html);
    };

    const run = (cmd, val = null) => {
      if (!CAN_EDIT || htmlMode) return;
      surface.focus();
      try { document.execCommand(cmd, false, val); } catch (_e) {}
      commit();
    };

    const blockFormatSelect = el('select', { class: 'pages-edit-input pages-edit-rt-select' });
    [
      ['<p>', 'Absatz'],
      ['<h2>', 'Überschrift 2'],
      ['<h3>', 'Überschrift 3'],
      ['<blockquote>', 'Zitat'],
    ].forEach(([tag, label]) => {
      const o = document.createElement('option');
      o.value = tag;
      o.textContent = label;
      blockFormatSelect.appendChild(o);
    });
    blockFormatSelect.disabled = !CAN_EDIT;
    blockFormatSelect.addEventListener('change', () => {
      if (!CAN_EDIT || htmlMode) return;
      run('formatBlock', blockFormatSelect.value);
    });
    groups[0].appendChild(blockFormatSelect);
    groups[0].appendChild(createToolbarButton('B', 'Fett', () => run('bold'), 'is-strong'));
    groups[0].appendChild(createToolbarButton('I', 'Kursiv', () => run('italic'), 'is-italic'));
    groups[0].appendChild(createToolbarButton('U', 'Unterstreichen', () => run('underline'), 'is-underline'));

    groups[1].appendChild(createToolbarButton('↤', 'Linksbündig', () => run('justifyLeft')));
    groups[1].appendChild(createToolbarButton('↔', 'Zentriert', () => run('justifyCenter')));
    groups[1].appendChild(createToolbarButton('↦', 'Rechtsbündig', () => run('justifyRight')));
    groups[1].appendChild(createToolbarButton('☰', 'Blocksatz', () => run('justifyFull')));

    groups[2].appendChild(createToolbarButton('• Liste', 'Aufzählung', () => run('insertUnorderedList')));
    groups[2].appendChild(createToolbarButton('1. Liste', 'Nummerierung', () => run('insertOrderedList')));
    groups[2].appendChild(createToolbarButton('↦ Einzug', 'Einzug erhöhen', () => run('indent')));
    groups[2].appendChild(createToolbarButton('↤ Einzug', 'Einzug verringern', () => run('outdent')));

    const colorInput = el('input', { type: 'color', class: 'pages-edit-rt-color' });
    colorInput.value = '#111111';
    colorInput.disabled = !CAN_EDIT;
    colorInput.addEventListener('input', () => run('foreColor', colorInput.value));
    const highlightInput = el('input', { type: 'color', class: 'pages-edit-rt-color' });
    highlightInput.value = '#fff2a8';
    highlightInput.disabled = !CAN_EDIT;
    highlightInput.addEventListener('input', () => run('hiliteColor', highlightInput.value));

    groups[3].appendChild(createToolbarButton('🔗', 'Link einfügen', () => {
      if (!CAN_EDIT || htmlMode) return;
      const url = window.prompt('Link-URL', 'https://');
      if (!url) return;
      run('createLink', url);
    }));
    groups[3].appendChild(createToolbarButton('🚫🔗', 'Link entfernen', () => run('unlink')));
    groups[3].appendChild(createToolbarButton('Tx', 'Formatierung entfernen', () => {
      run('removeFormat');
      run('unlink');
    }));
    groups[3].appendChild(colorInput);
    groups[3].appendChild(highlightInput);

    const htmlModeBtn = createToolbarButton('&lt;&gt;', 'HTML anzeigen', () => {
      if (!CAN_EDIT) return;
      htmlMode = !htmlMode;
      wrap.classList.toggle('is-html-mode', htmlMode);
      if (htmlMode) {
        htmlArea.value = surface.innerHTML;
        htmlArea.style.display = '';
        surface.style.display = 'none';
      } else {
        surface.innerHTML = htmlArea.value || '';
        htmlArea.style.display = 'none';
        surface.style.display = '';
      }
      commit();
    }, 'pages-edit-rt-btn--mode');
    groups[3].appendChild(htmlModeBtn);

    surface.addEventListener('input', () => {
      if (htmlMode) return;
      commit();
    });
    htmlArea.addEventListener('input', () => {
      if (!htmlMode) return;
      commit();
    });

    wrap.appendChild(toolbar);
    wrap.appendChild(surface);
    wrap.appendChild(htmlArea);
    return wrap;
  }

  function renderField(block, key, rule) {
    const labelText = (rule && rule.label) ? rule.label : key;
    const hintText  = (rule && rule.hint) ? rule.hint : '';

    const fieldWrap = el('div', {class: 'pages-edit-field'});
    fieldWrap.appendChild(el('div', {class: 'pages-edit-field-label', html: labelText}));

    const control = (rule && rule.control) ? rule.control : 'input';
    const rows = (rule && rule.rows) ? String(rule.rows) : '6';
    const rangeMin = (rule && Object.prototype.hasOwnProperty.call(rule, 'min')) ? String(rule.min) : '0';
    const rangeMax = (rule && Object.prototype.hasOwnProperty.call(rule, 'max')) ? String(rule.max) : '100';
    const rangeStep = (rule && Object.prototype.hasOwnProperty.call(rule, 'step')) ? String(rule.step) : '1';
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
      const useWordEditor = key === 'text' || key === 'html' || block.type === 'text';
      if (useWordEditor) {
        input = createWordLikeEditor(val, (html) => {
          if (!CAN_EDIT) return;
          block.data[key] = html;
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
    } else if (control === 'range') {
      const rangeWrap = el('div', {class: 'pages-edit-range'});
      const range = el('input', {class: 'pages-edit-range__slider', type: 'range', min: rangeMin, max: rangeMax, step: rangeStep});
      const number = el('input', {class: 'pages-edit-input pages-edit-range__number', type: 'number', min: rangeMin, max: rangeMax, step: rangeStep});
      const display = el('div', {class: 'pages-edit-field-hint pages-edit-range__value'});

      const clampRangeValue = (raw) => {
        let n = Number.parseFloat(String(raw ?? '').trim());
        const min = Number.parseFloat(rangeMin);
        const max = Number.parseFloat(rangeMax);
        if (!Number.isFinite(n)) n = Number.isFinite(min) ? min : 0;
        if (Number.isFinite(min) && n < min) n = min;
        if (Number.isFinite(max) && n > max) n = max;
        return n;
      };

      const initial = clampRangeValue(val);
      range.value = String(initial);
      number.value = String(initial);
      display.textContent = `Aktuell: ${Math.round(initial)}%`;
      range.disabled = !CAN_EDIT;
      number.readOnly = !CAN_EDIT;

      const commit = (nextVal) => {
        const n = clampRangeValue(nextVal);
        const out = String(Math.round(n));
        range.value = out;
        number.value = out;
        display.textContent = `Aktuell: ${out}%`;
        if (!CAN_EDIT) return;
        block.data[key] = out;
        serialize();
      };

      range.addEventListener('input', () => commit(range.value));
      number.addEventListener('input', () => commit(number.value));

      rangeWrap.appendChild(range);
      rangeWrap.appendChild(number);
      rangeWrap.appendChild(display);
      input = rangeWrap;
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
      const isCollapsed = collapsedBlocks.has(block.id);

      const left = el('div', {class: 'pages-edit-blockhead-left'});
      left.appendChild(el('span', {class: 'pages-edit-drag-handle', html: '⋮⋮'}));
      const displayLabel = blockLabel(block.type);
      left.appendChild(el('strong', {html: displayLabel}));
      left.appendChild(el('span', {class: 'pages-edit-blockhead-meta', html: `(${block.type})`}));

      const right = el('div', {class: 'pages-edit-blockhead-actions'});
      const toggleBtn = el('button', {type:'button', class:'btn btn--ghost btn--sm', html: isCollapsed ? 'Ausklappen' : 'Einklappen'});
      toggleBtn.addEventListener('click', (ev) => {
        ev.preventDefault();
        ev.stopPropagation();
        if (collapsedBlocks.has(block.id)) {
          collapsedBlocks.delete(block.id);
          model.blocks.forEach((b) => {
            if (b.id !== block.id) collapsedBlocks.add(b.id);
          });
        } else {
          collapsedBlocks.add(block.id);
        }
        render();
      });
      right.appendChild(toggleBtn);

      if (CAN_EDIT) {
        const upBtn  = el('button', {type:'button', class:'btn btn--ghost btn--sm', html:'↑'});
        const dnBtn  = el('button', {type:'button', class:'btn btn--ghost btn--sm', html:'↓'});
        const delBtn = el('button', {type:'button', class:'btn btn--ghost btn--danger btn--sm', html:'Löschen'});

        upBtn.addEventListener('click', (ev) => {
          ev.preventDefault();
          ev.stopPropagation();
          if (idx <= 0) return;
          const tmp = model.blocks[idx - 1];
          model.blocks[idx - 1] = model.blocks[idx];
          model.blocks[idx] = tmp;
          render();
          serialize();
        });

        dnBtn.addEventListener('click', (ev) => {
          ev.preventDefault();
          ev.stopPropagation();
          if (idx >= model.blocks.length - 1) return;
          const tmp = model.blocks[idx + 1];
          model.blocks[idx + 1] = model.blocks[idx];
          model.blocks[idx] = tmp;
          render();
          serialize();
        });

        delBtn.addEventListener('click', (ev) => {
          ev.preventDefault();
          ev.stopPropagation();
          model.blocks.splice(idx, 1);
          collapsedBlocks.delete(block.id);
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
      if (isCollapsed) card.classList.add('is-collapsed');
      card.appendChild(head);
      const body = renderBlockFields(block);
      body.classList.add('pages-edit-blockbody');
      card.appendChild(body);

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
      model.blocks.forEach((b) => {
        if (b.id !== model.blocks[model.blocks.length - 1].id) collapsedBlocks.add(b.id);
      });
      collapsedBlocks.delete(model.blocks[model.blocks.length - 1].id);

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
    titleInput.addEventListener('input', () => {
      updateSlugHint();
      schedulePreviewUpdate();
    });
    updateSlugHint();
  }

  if (form) {
    form.addEventListener('input', () => schedulePreviewUpdate());
    form.addEventListener('change', () => schedulePreviewUpdate());
  }

  form.addEventListener('submit', () => serialize());
  render();
  pushHistory();
  updateHistoryButtons();
})();
</script>
