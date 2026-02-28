<?php
/** @var array $block */
$colCount = (int)($block['col_count'] ?? 2);
if ($colCount < 2 || $colCount > 3) $colCount = 2;

$cols = [];
for ($i = 1; $i <= $colCount; $i++) {
    $title = trim((string)($block["col_{$i}_title"] ?? ''));
    $text  = trim((string)($block["col_{$i}_text"]  ?? ''));
    if ($title !== '' || $text !== '') {
        $cols[] = ['title' => $title, 'text' => $text];
    }
}

if (empty($cols)) return;
?>
<div class="block block-columns block-columns-<?= $colCount ?>">
  <div class="block-columns__grid">
  <?php foreach ($cols as $col): ?>
    <div class="block-columns__col">
      <?php if ($col['title'] !== ''): ?>
        <h3><?= htmlspecialchars($col['title'], ENT_QUOTES, 'UTF-8') ?></h3>
      <?php endif; ?>
      <?php if ($col['text'] !== ''): ?>
        <div><?= nl2br(htmlspecialchars($col['text'], ENT_QUOTES, 'UTF-8')) ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>
</div>
