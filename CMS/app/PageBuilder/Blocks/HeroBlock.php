<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

final class HeroBlock extends AbstractBlockType
{
    public function type(): string { return 'hero'; }
    public function label(): string { return 'Hero'; }

    public function defaults(): array
    {
        return [
            'headline' => '',
            'subtitle' => '',
            'button_text' => '',
            'button_url' => '',
            'image_url' => '',
        ];
    }

    public function fields(): array
    {
        return [
            'headline' => ['type' => 'string', 'max' => 200, 'label' => 'Headline', 'control' => 'input'],
            'subtitle' => ['type' => 'string', 'max' => 500, 'label' => 'Subline',  'control' => 'textarea', 'rows' => 3],
            'button_text' => ['type' => 'string', 'max' => 80, 'label' => 'Button-Text', 'control' => 'input'],
            'button_url'  => ['type' => 'string', 'max' => 2000, 'label' => 'Button-URL', 'control' => 'input'],
            'image_url'   => ['type' => 'string', 'max' => 2000, 'label' => 'Bild (URL)', 'control' => 'input'],
        ];
    }
}
