<?php
declare(strict_types=1);

/**
 * Escape HTML special characters
 */
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Render a template file with given variables
 */
function render(string $file, array $vars = []): void {
    if (str_contains($file, '..')) {
        throw new RuntimeException('Invalid template path');
    }
    $path = __DIR__ . '/../' . $file;
    if (!is_file($path)) {
        throw new RuntimeException("Template not found: {$file}");
    }
    extract($vars, EXTR_SKIP);
    require $path;
}
