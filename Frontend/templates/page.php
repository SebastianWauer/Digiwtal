<?php
$pageTitle = (string)($pageTitle ?? 'Seite');
$pageSubtitle = trim((string)($pageSubtitle ?? ''));
$blocksList = is_array($blocks ?? null) ? $blocks : [];
$contactFormStates = is_array($contactFormStates ?? null) ? $contactFormStates : [];
$contactTurnstileSiteKey = (isset($contactTurnstileSiteKey) && is_string($contactTurnstileSiteKey)) ? trim($contactTurnstileSiteKey) : '';
$publicSettings = is_array($publicSettings ?? null) ? $publicSettings : [];
$currentSlug = trim((string)($slug ?? ''), '/');
$headingRendered = false;

$hasHero = false;
foreach ($blocksList as $candidateBlock) {
    if (!is_array($candidateBlock)) {
        continue;
    }
    if ((string)($candidateBlock['type'] ?? '') === 'hero') {
        $hasHero = true;
        break;
    }
}
?>
<?php
if (!$hasHero):
?>
<section class="page-headline-wrap">
    <h1 class="page-title"><?php echo e($pageTitle); ?></h1>
    <?php if ($pageSubtitle !== ''): ?>
    <p class="page-subtitle"><?php echo e($pageSubtitle); ?></p>
    <?php endif; ?>
</section>
<?php
endif;

foreach ($blocksList as $blockIndex => $block):
    if (!is_array($block)) {
        continue;
    }
    $block['_render_index'] = (int)$blockIndex;
    $type = (string)($block['type'] ?? '');
    $data = $block['data'] ?? [];
    if (!is_array($data)) {
        $data = [];
    }

    // Newer API payloads expose block fields at the top level instead of nested under "data".
    $flatData = $block;
    unset($flatData['type'], $flatData['data']);
    if (is_array($flatData)) {
        $data = array_merge($flatData, $data);
    }
    
    $template = match ($type) {
        'text'    => 'themes/default/blocks/text.php',
        'hero'    => 'themes/default/blocks/hero.php',
        'image'   => 'themes/default/blocks/image.php',
        'columns' => 'themes/default/blocks/columns.php',
        'cta'     => 'themes/default/blocks/cta.php',
        'faq'     => 'themes/default/blocks/faq.php',
        'gallery' => 'themes/default/blocks/gallery.php',
        'video'   => 'themes/default/blocks/video.php',
        'contact_form' => 'themes/default/blocks/contact_form.php',
        'imprint' => 'themes/default/blocks/imprint.php',
        'events' => 'themes/default/blocks/events.php',
        default   => 'templates/blocks/unknown.php',
    };
    
    render($template, compact('block', 'data', 'contactFormStates', 'currentSlug', 'contactTurnstileSiteKey', 'publicSettings'));

    if (!$headingRendered && $type === 'hero') {
        $headingRendered = true;
        ?>
        <section class="page-headline-wrap">
            <h1 class="page-title"><?php echo e($pageTitle); ?></h1>
            <?php if ($pageSubtitle !== ''): ?>
            <p class="page-subtitle"><?php echo e($pageSubtitle); ?></p>
            <?php endif; ?>
        </section>
        <?php
    }
endforeach;
?>
