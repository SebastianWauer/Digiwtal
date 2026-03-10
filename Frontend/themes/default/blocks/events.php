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
        $key = $slug !== '' ? $slug : strtolower($name);
        if (!isset($categoryCounts[$key])) {
            $categoryCounts[$key] = ['name' => $name, 'slug' => $slug, 'count' => 0];
        }
        $categoryCounts[$key]['count']++;
    }

    $prepared[] = [
        'title' => trim((string)($item['title'] ?? 'Event')),
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
    ];
}

ksort($yearCounts, SORT_NUMERIC);
uasort($categoryCounts, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
?>
<section class="block block-events" id="<?= htmlspecialchars($blockUid, ENT_QUOTES, 'UTF-8') ?>">
  <div class="block-events__inner">
    <?php if ($headline !== ''): ?>
      <h2><?= htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php endif; ?>
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
      <p class="block-events__empty">Aktuell sind keine Events verfuegbar.</p>
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

      <div class="block-events__grid">
        <?php foreach (array_merge($upcoming, $past) as $item): ?>
          <?php
            $title = $item['title'] !== '' ? $item['title'] : 'Event';
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
                $variants[] = [
                    'category_slug' => strtolower(trim((string)($v['category_slug'] ?? ''))),
                    'category_name' => trim((string)($v['category_name'] ?? '')),
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
            $baseCat = htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8');
          ?>
          <article class="block-events__item<?= $isPast ? ' is-past' : '' ?>" data-year="<?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?>" data-categories="<?= htmlspecialchars($catCsv, ENT_QUOTES, 'UTF-8') ?>" data-past="<?= $isPast ? '1' : '0' ?>" data-image-variants="<?= $variantsJson ?>">
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
      <p class="block-events__empty block-events__empty--filtered" data-filter-empty hidden>Keine Events fuer die aktuelle Filterauswahl.</p>
      <script>
      (() => {
        const root = document.getElementById(<?= json_encode($blockUid, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
        if (!root) return;
        const yearSelect = root.querySelector('[data-filter-year]');
        const catSelect = root.querySelector('[data-filter-category]');
        const cards = Array.from(root.querySelectorAll('.block-events__item'));
        const empty = root.querySelector('[data-filter-empty]');
        const pastLabel = root.querySelector('[data-past-label]');
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
          img.src = String(chosen.image_url);
          const [px, py] = focusPairToPercent(chosen.image_focus_x, chosen.image_focus_y, 50, 50);
          img.dataset.focusX = String(px);
          img.dataset.focusY = String(py);
          img.setAttribute('data-focus-x', String(px));
          img.setAttribute('data-focus-y', String(py));
          applyCoverFocus(img, px, py);

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

        const apply = () => {
          const y = yearSelect ? String(yearSelect.value || '').trim() : '';
          const c = catSelect ? String(catSelect.value || '').trim().toLowerCase() : '';
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
        };

        if (yearSelect) yearSelect.addEventListener('change', apply);
        if (catSelect) catSelect.addEventListener('change', apply);
        apply();
        window.addEventListener('resize', refreshVisibleFocus);
        window.setInterval(() => {
          if (catSelect && String(catSelect.value || '').trim() !== '') return;
          rot = (rot + 1) % 100000;
          cards.forEach((card) => {
            if (card.hidden) return;
            setVariant(card, '');
          });
        }, 3500);
      })();
      </script>
    <?php endif; ?>
  </div>
</section>
