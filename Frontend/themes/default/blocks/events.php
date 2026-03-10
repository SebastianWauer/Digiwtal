<?php
/** @var array $block */
/** @var array|null $data */

$p = [];
if (isset($data) && is_array($data)) {
    $p = $data;
} elseif (isset($block['data']) && is_array($block['data'])) {
    $p = array_merge($block, $block['data']);
} elseif (is_array($block)) {
    $p = $block;
}

$headline = trim((string)($p['headline'] ?? 'Events & Termine'));
$items = is_array($p['items'] ?? null) ? $p['items'] : [];
$blockUid = 'events-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string)($p['_render_index'] ?? uniqid('', true)));

if ($headline === '' && $items === []) {
    return;
}

$prepared = [];
$yearCounts = [];
$categoryCounts = [];
$nowDate = date('Y-m-d');

foreach ($items as $item) {
    if (!is_array($item)) continue;

    $dateFrom = trim((string)($item['date_from'] ?? ''));
    $dateTo = trim((string)($item['date_to'] ?? ''));
    $dateRaw = trim((string)($item['date'] ?? ''));
    if ($dateFrom === '' && $dateRaw !== '') {
        $dateFrom = (string)date('Y-m-d', (int)strtotime($dateRaw));
    }
    if ($dateFrom === '' && $dateTo !== '') {
        $dateFrom = $dateTo;
    }

    $year = '';
    if ($dateFrom !== '' && preg_match('/^\d{4}/', $dateFrom, $ym) === 1) {
        $year = $ym[0];
        $yearCounts[$year] = (int)($yearCounts[$year] ?? 0) + 1;
    }

    $categoryNames = is_array($item['category_names'] ?? null)
        ? array_values(array_filter(array_map(static fn($v): string => trim((string)$v), $item['category_names']), static fn(string $v): bool => $v !== ''))
        : [];
    $categorySlugs = is_array($item['category_slugs'] ?? null)
        ? array_values(array_filter(array_map(static fn($v): string => trim((string)$v), $item['category_slugs']), static fn(string $v): bool => $v !== ''))
        : [];
    $categoryColors = is_array($item['category_colors'] ?? null)
        ? array_values(array_map(static fn($v): string => strtoupper(trim((string)$v)), $item['category_colors']))
        : [];
    $categoryColorMap = is_array($item['category_color_map'] ?? null) ? $item['category_color_map'] : [];

    if ($categoryNames === []) {
        $singleName = trim((string)($item['category_name'] ?? ''));
        if ($singleName !== '') {
            $categoryNames = array_values(array_filter(array_map('trim', explode(',', $singleName)), static fn(string $v): bool => $v !== ''));
        }
    }
    if ($categorySlugs === []) {
        $singleSlug = trim((string)($item['category_slug'] ?? ''));
        if ($singleSlug !== '') {
            $categorySlugs = array_values(array_filter(array_map('trim', explode(',', $singleSlug)), static fn(string $v): bool => $v !== ''));
        }
    }

    foreach ($categoryNames as $i => $name) {
        if ($name === '') continue;
        $slug = $categorySlugs[$i] ?? '';
        if ($slug === '') {
            $slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        }
        $color = strtoupper(trim((string)($categoryColors[$i] ?? ($categoryColorMap[$slug] ?? ''))));
        if (preg_match('/^#[0-9A-F]{6}$/', $color) !== 1) {
            $color = '';
        }
        $key = $slug !== '' ? $slug : strtolower($name);
        if (!isset($categoryCounts[$key])) {
            $categoryCounts[$key] = ['name' => $name, 'slug' => $slug, 'count' => 0, 'color' => $color];
        } elseif ($categoryCounts[$key]['color'] === '' && $color !== '') {
            $categoryCounts[$key]['color'] = $color;
        }
        $categoryCounts[$key]['count']++;
    }

    $prepared[] = [
        'title' => trim((string)($item['title'] ?? 'Event')),
        'subtitle' => trim((string)($item['subtitle'] ?? '')),
        'text' => trim((string)($item['text'] ?? '')),
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'year' => $year,
        'image_url' => trim((string)($item['image_url'] ?? '')),
        'image_focus_x' => $item['image_focus_x'] ?? null,
        'image_focus_y' => $item['image_focus_y'] ?? null,
        'image_variants' => is_array($item['image_variants'] ?? null) ? $item['image_variants'] : [],
        'youtube_url' => trim((string)($item['youtube_url'] ?? '')),
        'category_names' => $categoryNames,
        'category_slugs' => $categorySlugs,
        'category_colors' => $categoryColors,
        'category_color_map' => $categoryColorMap,
    ];
}

ksort($yearCounts, SORT_NUMERIC);
uasort($categoryCounts, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
?>
<section class="block block-events" id="<?= htmlspecialchars($blockUid, ENT_QUOTES, 'UTF-8') ?>">
  <div class="block-events__inner">
    <div class="block-events__head">
      <?php if ($headline !== ''): ?>
        <h2><?= htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') ?></h2>
      <?php endif; ?>
      <div class="block-events__view-switch" role="group" aria-label="Ansicht wechseln">
        <button type="button" class="block-events__view-btn is-active" data-view-btn="cards">Kacheln</button>
        <button type="button" class="block-events__view-btn" data-view-btn="calendar">Kalender</button>
      </div>
    </div>
    <?php if ($prepared !== []): ?>
      <div class="block-events__filters">
        <label>
          Jahr
          <select class="block-events__filter-year" data-filter-year>
            <option value="">Alle Jahre</option>
            <?php foreach ($yearCounts as $year => $count): ?>
              <option value="<?= htmlspecialchars((string)$year, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$year, ENT_QUOTES, 'UTF-8') ?> (<?= (int)$count ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Kategorie
          <select class="block-events__filter-category" data-filter-category>
            <option value="">Alle Kategorien</option>
            <?php foreach ($categoryCounts as $entry): ?>
              <option value="<?= htmlspecialchars((string)$entry['slug'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$entry['name'], ENT_QUOTES, 'UTF-8') ?> (<?= (int)$entry['count'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
    <?php endif; ?>

    <?php if ($prepared === []): ?>
      <p class="block-events__empty">Aktuell sind keine Events verfügbar.</p>
    <?php else: ?>
      <?php
        $upcoming = [];
        $past = [];
        foreach ($prepared as $item) {
          $cmp = $item['date_to'] !== '' ? $item['date_to'] : $item['date_from'];
          $isPast = ($cmp !== '' && $cmp < $nowDate);
          if ($isPast) {
            $past[] = $item;
          } else {
            $upcoming[] = $item;
          }
        }
      ?>

      <div class="block-events__list-view" data-view="cards">
      <div class="block-events__grid">
        <?php foreach (array_merge($upcoming, $past) as $item): ?>
          <?php
            $title = $item['title'] !== '' ? $item['title'] : 'Event';
            $subtitle = trim((string)($item['subtitle'] ?? ''));
            $text = $item['text'];
            $dateFrom = $item['date_from'];
            $dateTo = $item['date_to'];
            $dateLabel = '';
            if ($dateFrom !== '' && $dateTo !== '') {
                $dateLabel = date('d.m.Y', (int)strtotime($dateFrom)) . ' - ' . date('d.m.Y', (int)strtotime($dateTo));
            } elseif ($dateFrom !== '') {
                $dateLabel = date('d.m.Y', (int)strtotime($dateFrom));
            } elseif ($dateTo !== '') {
                $dateLabel = date('d.m.Y', (int)strtotime($dateTo));
            }
            $monthMap = [
                '01' => 'JAN', '02' => 'FEB', '03' => 'MAR', '04' => 'APR',
                '05' => 'MAI', '06' => 'JUN', '07' => 'JUL', '08' => 'AUG',
                '09' => 'SEP', '10' => 'OKT', '11' => 'NOV', '12' => 'DEZ',
            ];
            $fmtDateParts = static function (string $ymd) use ($monthMap): ?array {
                if ($ymd === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) !== 1) {
                    return null;
                }
                $y = substr($ymd, 0, 4);
                $m = substr($ymd, 5, 2);
                $d = substr($ymd, 8, 2);
                return [
                    'day' => $d,
                    'month' => (string)($monthMap[$m] ?? strtoupper($m)),
                    'year' => $y,
                ];
            };
            $fromParts = $fmtDateParts($dateFrom);
            $toParts = $fmtDateParts($dateTo);
            if ($toParts !== null && $fromParts !== null && $toParts === $fromParts) {
                $toParts = null;
            }
            if ($fromParts === null && $toParts !== null) {
                $fromParts = $toParts;
                $toParts = null;
            }
            $imageUrl = $item['image_url'];
            $imageFocusX = isset($item['image_focus_x']) && $item['image_focus_x'] !== '' ? (float)$item['image_focus_x'] : null;
            $imageFocusY = isset($item['image_focus_y']) && $item['image_focus_y'] !== '' ? (float)$item['image_focus_y'] : null;
            $variants = [];
            foreach ($item['image_variants'] as $v) {
                if (!is_array($v)) continue;
                $vUrl = trim((string)($v['image_url'] ?? ''));
                if ($vUrl === '') continue;
                $vColor = strtoupper(trim((string)($v['category_color'] ?? '')));
                if (preg_match('/^#[0-9A-F]{6}$/', $vColor) !== 1) {
                    $vColor = '';
                }
                $variants[] = [
                    'category_slug' => strtolower(trim((string)($v['category_slug'] ?? ''))),
                    'category_name' => trim((string)($v['category_name'] ?? '')),
                    'category_color' => $vColor,
                    'image_url' => $vUrl,
                    'image_focus_x' => $v['image_focus_x'] ?? null,
                    'image_focus_y' => $v['image_focus_y'] ?? null,
                ];
            }
            if ($variants !== []) {
                $imageUrl = (string)($variants[0]['image_url'] ?? $imageUrl);
                $imageFocusX = $variants[0]['image_focus_x'] ?? $imageFocusX;
                $imageFocusY = $variants[0]['image_focus_y'] ?? $imageFocusY;
            }
            $categoryNames = $item['category_names'];
            $categorySlugs = $item['category_slugs'];
            $categoryColors = is_array($item['category_colors'] ?? null) ? $item['category_colors'] : [];
            $categoryColorMap = is_array($item['category_color_map'] ?? null) ? $item['category_color_map'] : [];
            $categoryNameMap = [];
            foreach ($categorySlugs as $ci => $cSlugRaw) {
                $cSlug = strtolower(trim((string)$cSlugRaw));
                $cName = trim((string)($categoryNames[$ci] ?? ''));
                if ($cSlug !== '' && $cName !== '') {
                    $categoryNameMap[$cSlug] = $cName;
                }
            }
            foreach ($categorySlugs as $ci => $cSlugRaw) {
                $cSlug = strtolower(trim((string)$cSlugRaw));
                $cColor = strtoupper(trim((string)($categoryColors[$ci] ?? '')));
                if ($cSlug === '' || preg_match('/^#[0-9A-F]{6}$/', $cColor) !== 1) {
                    continue;
                }
                if (!isset($categoryColorMap[$cSlug]) || trim((string)$categoryColorMap[$cSlug]) === '') {
                    $categoryColorMap[$cSlug] = $cColor;
                }
            }
            $categoryName = $categoryNames !== [] ? implode("\n", $categoryNames) : '';
            $youtubeUrl = $item['youtube_url'];
            $youtubeEmbed = '';
            if ($youtubeUrl !== '') {
                if (preg_match('~(?:youtu\.be/|youtube\.com/watch\?v=|youtube\.com/embed/)([A-Za-z0-9_-]{6,})~i', $youtubeUrl, $m) === 1) {
                    $youtubeEmbed = 'https://www.youtube.com/embed/' . $m[1];
                }
            }
            $catCsv = implode(',', array_values(array_filter(array_map(static fn($v): string => strtolower(trim((string)$v)), $categorySlugs), static fn(string $v): bool => $v !== '')));
            $year = trim((string)$item['year']);
            $cmp = $dateTo !== '' ? $dateTo : $dateFrom;
            $isPast = ($cmp !== '' && $cmp < $nowDate);
            $variantsJson = htmlspecialchars((string)json_encode($variants, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
            $categoryColorMapJson = htmlspecialchars((string)json_encode($categoryColorMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
            $categoryNameMapJson = htmlspecialchars((string)json_encode($categoryNameMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
            $baseCat = htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8');
          ?>
          <article class="block-events__item<?= $isPast ? ' is-past' : '' ?>" data-year="<?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?>" data-categories="<?= htmlspecialchars($catCsv, ENT_QUOTES, 'UTF-8') ?>" data-past="<?= $isPast ? '1' : '0' ?>" data-image-variants="<?= $variantsJson ?>" data-category-color-map="<?= $categoryColorMapJson ?>" data-category-name-map="<?= $categoryNameMapJson ?>" data-date-from="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>" data-date-to="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>" data-title="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($fromParts !== null): ?>
              <div class="block-events__corner">
                <div class="block-events__date-badge" aria-label="<?= htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8') ?>">
                  <div class="block-events__date-col">
                    <span class="block-events__date-day"><?= htmlspecialchars((string)$fromParts['day'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="block-events__date-month"><?= htmlspecialchars((string)$fromParts['month'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="block-events__date-year"><?= htmlspecialchars((string)$fromParts['year'], ENT_QUOTES, 'UTF-8') ?></span>
                  </div>
                  <?php if ($toParts !== null): ?>
                    <div class="block-events__date-sep">-</div>
                    <div class="block-events__date-col">
                      <span class="block-events__date-day"><?= htmlspecialchars((string)$toParts['day'], ENT_QUOTES, 'UTF-8') ?></span>
                      <span class="block-events__date-month"><?= htmlspecialchars((string)$toParts['month'], ENT_QUOTES, 'UTF-8') ?></span>
                      <span class="block-events__date-year"><?= htmlspecialchars((string)$toParts['year'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="block-events__next-badge" data-next-badge hidden>Nächstes<br>Event</div>
              </div>
            <?php endif; ?>
            <?php if ($imageUrl !== ''): ?>
              <img
                src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"
                alt=""
                data-event-img
                <?php if ($imageFocusX !== null): ?>data-focus-x="<?= htmlspecialchars((string)$imageFocusX, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
                <?php if ($imageFocusY !== null): ?>data-focus-y="<?= htmlspecialchars((string)$imageFocusY, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
              >
            <?php endif; ?>

            <div class="block-events__body">
              <?php if ($categoryName !== ''): ?><div class="block-events__cat" data-event-cat data-base-cat="<?= $baseCat ?>"><?= $baseCat ?></div><?php endif; ?>
              <div class="block-events__meta-bottom">
                <?php if ($subtitle !== ''): ?><div class="block-events__item-subtitle"><?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                <h3><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
              </div>
              <?php if ($text !== ''): ?><p><?= nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) ?></p><?php endif; ?>
              <?php if ($youtubeEmbed !== ''): ?>
                <div class="block-events__yt">
                  <iframe src="<?= htmlspecialchars($youtubeEmbed, ENT_QUOTES, 'UTF-8') ?>" title="YouTube Video" loading="lazy" allowfullscreen></iframe>
                </div>
              <?php endif; ?>
              <?php if ($youtubeUrl !== ''): ?>
                <a class="block-events__video" href="<?= htmlspecialchars($youtubeUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Video ansehen</a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <p class="block-events__past-label" data-past-label hidden>Vergangene Events</p>
      <p class="block-events__empty block-events__empty--filtered" data-filter-empty hidden>Keine Events für die aktuelle Filterauswahl.</p>
      </div>
      <div class="block-events__calendar-view" data-view="calendar" hidden>
        <div class="block-events__calendar-toolbar">
          <button type="button" class="block-events__cal-nav" data-cal-prev aria-label="Vorheriger Monat">‹</button>
          <div class="block-events__cal-title" data-cal-title></div>
          <button type="button" class="block-events__cal-nav" data-cal-next aria-label="Nächster Monat">›</button>
        </div>
        <div class="block-events__calendar-months" data-cal-months></div>
        <p class="block-events__empty block-events__empty--filtered" data-cal-empty hidden>Keine Events in den angezeigten Monaten.</p>
      </div>
      <script>
      (() => {
        const root = document.getElementById(<?= json_encode($blockUid, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
        if (!root) return;
        const viewBtns = Array.from(root.querySelectorAll('[data-view-btn]'));
        const listView = root.querySelector('[data-view="cards"]');
        const calView = root.querySelector('[data-view="calendar"]');
        const yearSelect = root.querySelector('[data-filter-year]');
        const catSelect = root.querySelector('[data-filter-category]');
        const cards = Array.from(root.querySelectorAll('.block-events__item'));
        const empty = root.querySelector('[data-filter-empty]');
        const pastLabel = root.querySelector('[data-past-label]');
        const calPrev = root.querySelector('[data-cal-prev]');
        const calNext = root.querySelector('[data-cal-next]');
        const calTitle = root.querySelector('[data-cal-title]');
        const calMonths = root.querySelector('[data-cal-months]');
        const calEmpty = root.querySelector('[data-cal-empty]');
        const currentYear = String(new Date().getFullYear());
        let viewMode = 'cards';
        let calCursor = new Date();
        calCursor.setDate(1);
        const debugFocus = (() => {
          try {
            const p = new URLSearchParams(window.location.search);
            return p.get('focusdebug') === '1';
          } catch {
            return false;
          }
        })();
        let rot = 0;

        const clamp01 = (n) => Math.min(1, Math.max(0, n));
        const clamp100 = (n) => Math.min(100, Math.max(0, n));
        const toNum = (v) => {
          const n = Number(v);
          return Number.isFinite(n) ? n : null;
        };
        const focusPairToPercent = (xRaw, yRaw, fallbackX = 50, fallbackY = 50) => {
          const x = toNum(xRaw);
          const y = toNum(yRaw);
          if (x === null && y === null) return [fallbackX, fallbackY];

          // Project convention for focus values: [-1..1] on both axes.
          // If both are in unit range, always treat as signed unit space.
          const inUnitX = x !== null && Math.abs(x) <= 1;
          const inUnitY = y !== null && Math.abs(y) <= 1;
          const isSignedUnit = inUnitX && inUnitY;

          const norm = (n, fb) => {
            if (n === null) return fb;
            if (isSignedUnit) {
              return clamp100(((n + 1) / 2) * 100);
            }
            return clamp100(n);
          };

          return [norm(x, fallbackX), norm(y, fallbackY)];
        };

        const variantsOf = (card) => {
          const raw = String(card.getAttribute('data-image-variants') || '').trim();
          if (!raw) return [];
          try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
          } catch {
            return [];
          }
        };

        const setVariant = (card, slug) => {
          const img = card.querySelector('[data-event-img]');
          if (!img) return;
          const smoothSwapImage = (nextUrl, fx, fy) => {
            const currentUrl = String(img.getAttribute('src') || '').trim();
            const next = String(nextUrl || '').trim();
            if (next === '') return;

            const applyFocus = () => {
              img.dataset.focusX = String(fx);
              img.dataset.focusY = String(fy);
              img.setAttribute('data-focus-x', String(fx));
              img.setAttribute('data-focus-y', String(fy));
              applyCoverFocus(img, fx, fy);
            };

            if (currentUrl === next) {
              applyFocus();
              return;
            }

            const token = String((Number(img.dataset.swapToken || '0') + 1));
            img.dataset.swapToken = token;
            const probe = new Image();
            probe.decoding = 'async';

            const commit = () => {
              if (img.dataset.swapToken !== token) return;
              img.classList.add('is-swapping');
              window.setTimeout(() => {
                if (img.dataset.swapToken !== token) return;
                img.src = next;
                const afterLoad = () => {
                  if (img.dataset.swapToken !== token) return;
                  applyFocus();
                  window.requestAnimationFrame(() => {
                    if (img.dataset.swapToken === token) {
                      img.classList.remove('is-swapping');
                    }
                  });
                };
                if (img.complete && img.naturalWidth > 0) {
                  afterLoad();
                } else {
                  img.addEventListener('load', afterLoad, { once: true });
                }
              }, 170);
            };

            probe.onload = commit;
            probe.onerror = commit;
            probe.src = next;
            if (probe.complete) commit();
          };

          const variants = variantsOf(card);
          if (!variants.length) {
            const baseFx = Number(img.dataset.focusX ?? img.getAttribute('data-focus-x') ?? 50);
            const baseFy = Number(img.dataset.focusY ?? img.getAttribute('data-focus-y') ?? 50);
            applyCoverFocus(img, baseFx, baseFy);
            return;
          }
          const wanted = String(slug || '').trim().toLowerCase();
          let chosen = null;
          if (wanted !== '') {
            chosen = variants.find((v) => String(v.category_slug || '').toLowerCase() === wanted) || null;
          }
          if (!chosen) {
            chosen = variants[rot % variants.length] || variants[0];
          }
          if (!chosen || !chosen.image_url) return;
          const [px, py] = focusPairToPercent(chosen.image_focus_x, chosen.image_focus_y, 50, 50);
          smoothSwapImage(String(chosen.image_url), px, py);

          const cat = card.querySelector('[data-event-cat]');
          if (cat) {
            if (wanted !== '' && chosen.category_name) {
              cat.textContent = String(chosen.category_name);
            } else {
              const base = String(cat.getAttribute('data-base-cat') || '');
              if (base) cat.textContent = base;
            }
          }
        };

        const renderFocusDebug = (img, rawX, rawY, normX, normY, finalPos) => {
          if (!debugFocus || !img) return;
          const card = img.closest('.block-events__item');
          if (!card) return;
          let box = card.querySelector('[data-focus-debug]');
          if (!box) {
            box = document.createElement('div');
            box.setAttribute('data-focus-debug', '1');
            box.style.position = 'absolute';
            box.style.left = '8px';
            box.style.right = '8px';
            box.style.bottom = '8px';
            box.style.zIndex = '6';
            box.style.padding = '6px 8px';
            box.style.borderRadius = '8px';
            box.style.background = 'rgba(0,0,0,0.62)';
            box.style.color = '#fff';
            box.style.fontSize = '11px';
            box.style.lineHeight = '1.35';
            box.style.fontFamily = 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace';
            box.style.pointerEvents = 'none';
            card.appendChild(box);
          }
          box.textContent =
            'focus raw: ' + String(rawX) + ', ' + String(rawY)
            + ' | norm: ' + Number(normX).toFixed(2) + '%, ' + Number(normY).toFixed(2) + '%'
            + ' | final: ' + String(finalPos || '');
        };

        const applyCoverFocus = (img, fxRaw, fyRaw, retries = 4) => {
          if (!img) return;
          const [fxPct, fyPct] = focusPairToPercent(fxRaw, fyRaw, 50, 50);
          const fx = fxPct / 100;
          const fy = fyPct / 100;

          const run = () => {
            const cW = img.clientWidth || img.offsetWidth || 0;
            const cH = img.clientHeight || img.offsetHeight || 0;
            const nW = img.naturalWidth || 0;
            const nH = img.naturalHeight || 0;
            if (cW <= 0 || cH <= 0 || nW <= 0 || nH <= 0) {
              if (retries > 0) {
                window.requestAnimationFrame(() => applyCoverFocus(img, fx * 100, fy * 100, retries - 1));
              }
              return;
            }

            const scale = Math.max(cW / nW, cH / nH);
            const rW = nW * scale;
            const rH = nH * scale;

            // Center focal point when possible, clamp to keep full cover fill.
            const desiredOffsetX = (cW * 0.5) - (fx * rW);
            const desiredOffsetY = (cH * 0.5) - (fy * rH);
            const minOffsetX = cW - rW;
            const minOffsetY = cH - rH;
            const clampedOffsetX = Math.min(0, Math.max(minOffsetX, desiredOffsetX));
            const clampedOffsetY = Math.min(0, Math.max(minOffsetY, desiredOffsetY));

            const posX = rW > cW ? (clampedOffsetX / (cW - rW)) * 100 : 50;
            const posY = rH > cH ? (clampedOffsetY / (cH - rH)) * 100 : 50;
            const finalPos = posX.toFixed(2) + '% ' + posY.toFixed(2) + '%';
            img.style.objectPosition = finalPos;
            img.style.setProperty('--event-img-pos', finalPos);
            renderFocusDebug(
              img,
              img.getAttribute('data-focus-x') ?? fxRaw,
              img.getAttribute('data-focus-y') ?? fyRaw,
              fxPct,
              fyPct,
              finalPos
            );
          };

          if (!img.complete || !img.naturalWidth || !img.naturalHeight) {
            img.addEventListener('load', run, { once: true });
          }
          run();
        };

        const refreshVisibleFocus = () => {
          cards.forEach((card) => {
            if (card.hidden) return;
            const img = card.querySelector('[data-event-img]');
            if (!img) return;
            const fx = Number(img.dataset.focusX ?? 50);
            const fy = Number(img.dataset.focusY ?? 50);
            applyCoverFocus(img, fx, fy);
          });
        };

        const parseYmd = (raw) => {
          const s = String(raw || '').trim();
          if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) return null;
          const d = new Date(s + 'T00:00:00');
          if (Number.isNaN(d.getTime())) return null;
          return d;
        };

        const dayKey = (d) => {
          const y = d.getFullYear();
          const m = String(d.getMonth() + 1).padStart(2, '0');
          const day = String(d.getDate()).padStart(2, '0');
          return y + '-' + m + '-' + day;
        };

        const parseJsonMap = (card, attr) => {
          const raw = String(card.getAttribute(attr) || '').trim();
          if (!raw) return {};
          try {
            const parsed = JSON.parse(raw);
            return (parsed && typeof parsed === 'object') ? parsed : {};
          } catch {
            return {};
          }
        };

        const getVisibleEvents = () => cards.filter((card) => !card.hidden).map((card) => {
          const from = parseYmd(card.getAttribute('data-date-from') || '');
          const to = parseYmd(card.getAttribute('data-date-to') || '');
          const title = String(card.getAttribute('data-title') || 'Event').trim() || 'Event';
          const slugs = String(card.getAttribute('data-categories') || '')
            .split(',')
            .map((v) => v.trim().toLowerCase())
            .filter(Boolean);
          const colorMap = parseJsonMap(card, 'data-category-color-map');
          const nameMap = parseJsonMap(card, 'data-category-name-map');
          const categories = slugs.map((slug) => {
            const raw = String(colorMap[slug] || '').trim().toUpperCase();
            const color = /^#[0-9A-F]{6}$/.test(raw) ? raw : '';
            const name = String(nameMap[slug] || '').trim();
            return { slug, color, name };
          });
          return { from, to, title, categories };
        }).filter((e) => e.from || e.to).map((e) => {
          const from = e.from || e.to;
          const to = e.to || e.from;
          return { from, to, title: e.title, categories: e.categories };
        });

        const escapeHtml = (s) => String(s || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');

        const buildDayBars = (entries, dayDate, weekStartDate, weekEndDate) => {
          if (!entries || entries.length === 0) return '';
          const dayTs = dayDate.getTime();
          const msDay = 24 * 60 * 60 * 1000;
          const weekStartTs = weekStartDate.getTime();
          const weekEndTs = weekEndDate.getTime();
          const bars = [];
          const seen = new Set();

          entries.forEach((entry) => {
            const catList = Array.isArray(entry.categories) && entry.categories.length > 0
              ? entry.categories
              : [{ slug: '', color: '' }];

            catList.forEach((cat) => {
              const rawColor = String(cat.color || '').trim().toUpperCase();
              const color = /^#[0-9A-F]{6}$/.test(rawColor) ? rawColor : '';
              const fromTs = entry.from instanceof Date ? entry.from.getTime() : dayTs;
              const toTs = entry.to instanceof Date ? entry.to.getTime() : dayTs;
              const visibleStartTs = Math.max(fromTs, weekStartTs);
              const visibleEndTs = Math.min(toTs, weekEndTs);
              if (dayTs !== visibleStartTs) {
                return;
              }
              const spanDays = Math.max(1, Math.floor((visibleEndTs - visibleStartTs) / msDay) + 1);
              const key = [
                String(entry.title || ''),
                String(fromTs),
                String(toTs),
                String(cat.slug || ''),
                String(visibleStartTs),
              ].join('|');
              if (seen.has(key)) return;
              seen.add(key);

              const continuesPrev = fromTs < visibleStartTs;
              const continuesNext = toTs > visibleEndTs;
              const cls = 'block-events__cal-bar'
                + (continuesPrev ? ' is-continued-left' : '')
                + (continuesNext ? ' is-continued-right' : '');
              const styleBits = ['--bar-span:' + spanDays + ';'];
              if (color !== '') {
                styleBits.push('--bar-color:' + color + ';');
              }
              const styleAttr = ' style="' + styleBits.join('') + '"';
              const label = (String(cat.name || '').trim() !== '' ? (String(cat.name || '').trim() + ': ') : '') + String(entry.title || '').trim();
              const titleAttr = String(entry.title || '').trim() !== ''
                ? (' title="' + escapeHtml(String(entry.title || '')) + '"')
                : '';
              bars.push('<span class="' + cls + '"' + styleAttr + titleAttr + '>' + (label !== '' ? ('<span class="block-events__cal-bar-label">' + escapeHtml(label) + '</span>') : '') + '</span>');
            });
          });

          if (bars.length === 0) return '';
          return '<span class="block-events__cal-bars">' + bars.join('') + '</span>';
        };

        const renderMonth = (monthDate, className, visibleEvents, opts = {}) => {
          const y = monthDate.getFullYear();
          const m = monthDate.getMonth();
          const isActiveMonth = className.indexOf('is-active') !== -1;
          const trimStart = Math.max(0, Number(opts.trimStart || 0));
          const trimEnd = Math.max(0, Number(opts.trimEnd || 0));
          const monthNames = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
          const first = new Date(y, m, 1);
          const last = new Date(y, m + 1, 0);
          const daysInMonth = last.getDate();
          const startOffset = (first.getDay() + 6) % 7; // Monday first

          const byDay = new Map();
          visibleEvents.forEach((ev) => {
            let cur = new Date(ev.from.getTime());
            const end = new Date(ev.to.getTime());
            while (cur <= end) {
              const k = dayKey(cur);
              if (!byDay.has(k)) byDay.set(k, []);
              byDay.get(k).push({ title: ev.title, categories: ev.categories || [], from: ev.from, to: ev.to });
              cur.setDate(cur.getDate() + 1);
            }
          });

          const labels = ['Mo','Di','Mi','Do','Fr','Sa','So'];
          const wd = labels.map((l) => '<div class="block-events__cal-wd">' + l + '</div>').join('');

          const cells = [];
          if (isActiveMonth) {
            const start = new Date(first.getTime());
            start.setDate(first.getDate() - startOffset);
            const end = new Date(last.getTime());
            end.setDate(last.getDate() + (6 - ((last.getDay() + 6) % 7)));
            for (let cur = new Date(start.getTime()); cur <= end; cur.setDate(cur.getDate() + 1)) {
              const d = new Date(cur.getTime());
              const k = dayKey(d);
              cells.push({
                day: d.getDate(),
                date: d,
                entries: byDay.get(k) || [],
                inMonth: d.getMonth() === m,
              });
            }
          } else {
            const dayStart = Math.max(1, 1 + trimStart);
            const dayEnd = Math.max(0, daysInMonth - trimEnd);
            if (dayEnd >= dayStart) {
              const firstShown = new Date(y, m, dayStart);
              const shownOffset = (firstShown.getDay() + 6) % 7; // Monday first
              for (let i = 0; i < shownOffset; i++) cells.push(null);
            }
            for (let day = dayStart; day <= dayEnd; day++) {
              const d = new Date(y, m, day);
              const k = dayKey(d);
              const entries = byDay.get(k) || [];
              cells.push({ day, date: d, entries, inMonth: true });
            }
            while (cells.length % 7 !== 0) cells.push(null);
          }

          let weeks = '';
          for (let i = 0; i < cells.length; i += 7) {
            const week = cells.slice(i, i + 7);
            const weekDates = week.filter((c) => c && c.date instanceof Date).map((c) => c.date);
            const weekStartDate = weekDates[0] ?? null;
            const weekEndDate = weekDates[weekDates.length - 1] ?? null;
            const row = week.map((cell) => {
              if (!cell) return '<div class="block-events__cal-cell is-empty"></div>';
              const titles = Array.from(new Set((cell.entries || []).map((e) => String(e.title || '').trim()).filter(Boolean)));
              const tooltip = titles.join(' • ');
              const dayDate = cell.date instanceof Date ? cell.date : new Date(y, m, cell.day);
              const barsHtml = (weekStartDate && weekEndDate)
                ? buildDayBars(cell.entries || [], dayDate, weekStartDate, weekEndDate)
                : '';
              const titleAttr = tooltip !== '' ? (' title="' + escapeHtml(tooltip) + '"') : '';
              const monthCls = cell.inMonth ? '' : ' is-out-month';
              return (
                '<div class="block-events__cal-cell' + monthCls + (barsHtml !== '' ? ' has-event has-bar-start' : '') + '"' + titleAttr + '>' +
                  '<span class="block-events__cal-day">' + cell.day + '</span>' +
                  barsHtml +
                '</div>'
              );
            }).join('');
            weeks += '<div class="block-events__cal-week">' + row + '</div>';
          }

          return (
            '<section class="block-events__month ' + className + '">' +
              '<div class="block-events__month-head">' + monthNames[m] + ' ' + y + '</div>' +
              '<div class="block-events__month-grid">' +
                '<div class="block-events__cal-weekdays">' + wd + '</div>' +
                '<div class="block-events__cal-weeks">' + weeks + '</div>' +
              '</div>' +
            '</section>'
          );
        };

        const renderCalendar = () => {
          if (!calMonths || !calTitle) return;
          const y = calCursor.getFullYear();
          const m = calCursor.getMonth();
          const monthNames = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
          calTitle.textContent = monthNames[m] + ' ' + y;

          const visibleEvents = getVisibleEvents();
          const cur = new Date(y, m, 1);
          const next = new Date(y, m + 1, 1);
          const activeFirst = new Date(y, m, 1);
          const activeLast = new Date(y, m + 1, 0);
          const overlapNext = 6 - ((activeLast.getDay() + 6) % 7);

          calMonths.innerHTML =
            renderMonth(cur, 'is-active', visibleEvents)
            + renderMonth(next, 'is-side is-next', visibleEvents, { trimStart: overlapNext });

          if (calEmpty) {
            const windowStart = new Date(y, m - 1, 1);
            const windowEnd = new Date(y, m + 2, 0);
            const hasAny = visibleEvents.some((ev) => ev.to >= windowStart && ev.from <= windowEnd);
            calEmpty.hidden = hasAny;
          }
        };

        const setViewMode = (mode) => {
          viewMode = mode === 'calendar' ? 'calendar' : 'cards';
          if (listView) listView.hidden = viewMode !== 'cards';
          if (calView) calView.hidden = viewMode !== 'calendar';
          viewBtns.forEach((b) => b.classList.toggle('is-active', b.getAttribute('data-view-btn') === viewMode));
          if (viewMode === 'calendar') renderCalendar();
        };

        const apply = () => {
          const y = yearSelect ? String(yearSelect.value || '').trim() : '';
          const c = catSelect ? String(catSelect.value || '').trim().toLowerCase() : '';
          if (/^\d{4}$/.test(y)) {
            calCursor.setFullYear(Number(y));
          }
          let visible = 0;
          let visiblePast = 0;
          let firstPastShown = null;

          cards.forEach((card) => {
            const cy = String(card.getAttribute('data-year') || '').trim();
            const cats = String(card.getAttribute('data-categories') || '').split(',').map((x) => x.trim().toLowerCase()).filter(Boolean);
            const okYear = y === '' || cy === y;
            const okCat = c === '' || cats.includes(c);
            const show = okYear && okCat;
            card.hidden = !show;
            card.classList.remove('is-lead');
            card.style.order = card.getAttribute('data-past') === '1' ? '20' : '10';
            const badge = card.querySelector('[data-next-badge]');
            if (badge) badge.hidden = true;
            setVariant(card, c);
            if (show) {
              visible++;
              if (card.getAttribute('data-past') === '1') {
                visiblePast++;
                if (!firstPastShown) firstPastShown = card;
              }
            }
          });

          const visibleCards = cards.filter((card) => !card.hidden);
          if (visibleCards.length > 0) {
            const firstUpcoming = visibleCards.find((card) => card.getAttribute('data-past') !== '1') || null;
            const lead = firstUpcoming || visibleCards[0];
            if (lead) {
              lead.classList.add('is-lead');
              lead.style.order = '0';
              const badge = lead.querySelector('[data-next-badge]');
              if (badge) badge.hidden = false;
            }
          }

          if (empty) empty.hidden = visible > 0;
          if (pastLabel) {
            pastLabel.hidden = !(visiblePast > 0 && firstPastShown);
            if (!pastLabel.hidden && firstPastShown && firstPastShown.parentNode) {
              pastLabel.style.order = '19';
              firstPastShown.parentNode.insertBefore(pastLabel, firstPastShown);
            }
          }

          // Run once after class/layout changes so vertical focus is also correct.
          window.requestAnimationFrame(() => {
            refreshVisibleFocus();
            window.requestAnimationFrame(refreshVisibleFocus);
          });
          if (viewMode === 'calendar') {
            renderCalendar();
          }
        };

        if (yearSelect) {
          const hasCurrentYearOption = Array.from(yearSelect.options || []).some((opt) => String(opt.value) === currentYear);
          if (hasCurrentYearOption && String(yearSelect.value || '').trim() === '') {
            yearSelect.value = currentYear;
          }
          yearSelect.addEventListener('change', apply);
        }
        if (catSelect) catSelect.addEventListener('change', apply);
        viewBtns.forEach((btn) => {
          btn.addEventListener('click', () => setViewMode(String(btn.getAttribute('data-view-btn') || 'cards')));
        });
        if (calPrev) calPrev.addEventListener('click', () => { calCursor.setMonth(calCursor.getMonth() - 1); renderCalendar(); });
        if (calNext) calNext.addEventListener('click', () => { calCursor.setMonth(calCursor.getMonth() + 1); renderCalendar(); });
        apply();
        window.addEventListener('resize', refreshVisibleFocus);
        window.setInterval(() => {
          if (catSelect && String(catSelect.value || '').trim() !== '') return;
          rot = (rot + 1) % 100000;
          cards.forEach((card) => {
            if (card.hidden) return;
            setVariant(card, '');
          });
        }, 4600);
      })();
      </script>
    <?php endif; ?>
  </div>
</section>
