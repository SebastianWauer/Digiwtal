<?php
/** @var array $block */
$e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<section class="block block-hero"<?= !empty($block['image_url'])
    ? ' style="background-image:url(' . $e((string)$block['image_url']) . ')"'
    : '' ?>>
  <?php if (!empty($block['headline'])): ?>
    <h1><?= $e((string)$block['headline']) ?></h1>
  <?php endif; ?>
  <?php if (!empty($block['subtitle'])): ?>
    <p><?= $e((string)$block['subtitle']) ?></p>
  <?php endif; ?>
  <?php if (!empty($block['button_url']) && !empty($block['button_text'])): ?>
    <a href="<?= $e((string)$block['button_url']) ?>" class="hero-btn">
      <?= $e((string)$block['button_text']) ?>
    </a>
  <?php endif; ?>
</section>