<?php
$imgUrl = htmlspecialchars((string)($block['url'] ?? ''), ENT_QUOTES, 'UTF-8');
$imgAlt = htmlspecialchars((string)($block['alt'] ?? ''), ENT_QUOTES, 'UTF-8');
$focusStyle = '';
if (isset($block['url_focus_x']) || isset($block['url_focus_y'])) {
  $fx = isset($block['url_focus_x']) ? (float)$block['url_focus_x'] : 0.0;
  $fy = isset($block['url_focus_y']) ? (float)$block['url_focus_y'] : 0.0;
  if ($fx < -1.0) $fx = -1.0;
  if ($fx > 1.0)  $fx = 1.0;
  if ($fy < -1.0) $fy = -1.0;
  if ($fy > 1.0)  $fy = 1.0;
  $px = (int)round((($fx + 1.0) / 2.0) * 100);
  $py = (int)round((($fy + 1.0) / 2.0) * 100);
  $focusStyle = ' style="object-position:' . $px . '% ' . $py . '%"';
}
?>
<figure class="block block-image">
  <img src="<?= $imgUrl ?>"
       alt="<?= $imgAlt ?>"<?= $focusStyle ?>>
  <?php if (!empty($block['caption'])): ?>
    <figcaption><?= htmlspecialchars((string)$block['caption'], ENT_QUOTES, 'UTF-8') ?></figcaption>
  <?php endif; ?>
</figure>
