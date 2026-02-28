<figure class="block block-image">
  <img src="<?= htmlspecialchars((string)($block['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
       alt="<?= htmlspecialchars((string)($block['alt'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
  <?php if (!empty($block['caption'])): ?>
    <figcaption><?= htmlspecialchars((string)$block['caption'], ENT_QUOTES, 'UTF-8') ?></figcaption>
  <?php endif; ?>
</figure>
