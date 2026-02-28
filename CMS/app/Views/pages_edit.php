<?php
declare(strict_types=1);

/** @var array $page */
/** @var ?array $flash */
/** @var array $seoOverride */

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
            <input class="pages-edit-input" type="text" name="title" value="<?= h($title) ?>" required <?= $canSave ? '' : 'readonly' ?>>
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
            <input class="pages-edit-input" type="text" name="slug" value="<?= h($slug) ?>" placeholder="/Titel" <?= $canSave ? '' : 'readonly' ?>>
            <div class="pages-edit-field-hint">
              Optional. Wenn leer, wird der Slug automatisch aus dem Titel erzeugt.
              Beispiel: <code>/kontakt</code>
            </div>
          </div>

          <hr class="pages-edit-sep">

          <div class="pages-edit-card-title pages-edit-pb-title">PageBuilder</div>
          <div class="pages-edit-card-sub pages-edit-pb-sub">Text, Bild, Hero — Reihenfolge & Inhalte bearbeiten.</div>

          <?php if ($canSave): ?>
            <div class="pages-edit-pb-actions">
              <button type="button" class="btn btn--ghost btn--sm" data-add-block="text">+ Text</button>
              <button type="button" class="btn btn--ghost btn--sm" data-add-block="image">+ Bild</button>
              <button type="button" class="btn btn--ghost btn--sm" data-add-block="hero">+ Hero</button>
              <button type="button" class="btn btn--ghost btn--sm" data-add-block="columns">+ Spalten</button>
              <button type="button" class="btn btn--ghost btn--sm" data-add-block="cta">+ CTA</button>
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

    </aside>

  </div>
</form>

<script>
(() => {
  const defs = <?= $defsJson ?>;
  const CAN_EDIT = <?= $canSave ? 'true' : 'false' ?>;

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

  function serialize() {
    const out = { blocks: model.blocks.map(b => Object.assign({ type: b.type }, b.data)) };
    const json = JSON.stringify(out);
    contentInput.value = json;
    if (rawTextarea) rawTextarea.value = json;
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

    if (control === 'select' && enumVals) {
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
      input = el('textarea', {class: 'pages-edit-textarea', rows});
      input.value = val;
      input.readOnly = !CAN_EDIT;
      input.addEventListener('input', () => {
        if (!CAN_EDIT) return;
        block.data[key] = input.value;
        serialize();
      });
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

    fieldWrap.appendChild(input);
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
      left.appendChild(el('strong', {html: defs[block.type].label}));
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

      const card = el('div', {class: 'pages-edit-card pages-edit-blockcard'});
      card.appendChild(head);
      card.appendChild(renderBlockFields(block));

      container.appendChild(card);
    });

    serialize();
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
      serialize();
    });
  });

  form.addEventListener('submit', () => serialize());
  render();
})();
</script>
