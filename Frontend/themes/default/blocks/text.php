<div class="block block-text">
  <h2><?= htmlspecialchars((string)($block['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
  <div><?= nl2br(htmlspecialchars((string)($block['text'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
</div>
