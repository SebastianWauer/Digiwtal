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
        'text' => 'templates/blocks/text.php',
        'hero' => 'templates/blocks/hero.php',
        default => 'templates/blocks/unknown.php',
    };
    
    render($template, compact('block', 'data'));
endforeach;
?>
