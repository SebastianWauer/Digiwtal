<?php
/** @var array $block */
$title = htmlspecialchars((string)($block['title'] ?? ''), ENT_QUOTES, 'UTF-8');
$allowedTags = '<p><br><strong><em><u><s><ul><ol><li><h2><h3><h4><blockquote><a>';
$text = strip_tags((string)($block['text'] ?? ''), $allowedTags);
?>
<div class="block block-text">
  <?php if ($title !== ''): ?>
    <h2><?= $title ?></h2>
  <?php endif; ?>
  <div class="block-text__content"><?= $text ?></div>
</div>
