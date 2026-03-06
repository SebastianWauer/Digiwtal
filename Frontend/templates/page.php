<h1><?php echo e($pageTitle ?? 'Seite'); ?></h1>
<?php
foreach ($blocks ?? [] as $block):
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
        default   => 'templates/blocks/unknown.php',
    };
    
    render($template, compact('block', 'data'));
endforeach;
?>
