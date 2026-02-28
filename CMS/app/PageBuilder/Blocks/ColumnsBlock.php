<?php
declare(strict_types=1);

namespace App\PageBuilder\Blocks;

final class ColumnsBlock extends AbstractBlockType
{
    public function type(): string { return 'columns'; }
    public function label(): string { return 'Spalten'; }

    public function defaults(): array
    {
        return [
            'col_count'   => '2',
            'col_1_title' => '',
            'col_1_text'  => '',
            'col_2_title' => '',
            'col_2_text'  => '',
            'col_3_title' => '',
            'col_3_text'  => '',
        ];
    }

    public function fields(): array
    {
        return [
            'col_count'   => ['type' => 'string', 'max' => 1,    'label' => 'Spaltenanzahl',  'control' => 'select', 'enum' => ['2', '3']],
            'col_1_title' => ['type' => 'string', 'max' => 200,  'label' => 'Spalte 1 – Titel', 'control' => 'input'],
            'col_1_text'  => ['type' => 'string', 'max' => 1000, 'label' => 'Spalte 1 – Text',  'control' => 'textarea', 'rows' => 4],
            'col_2_title' => ['type' => 'string', 'max' => 200,  'label' => 'Spalte 2 – Titel', 'control' => 'input'],
            'col_2_text'  => ['type' => 'string', 'max' => 1000, 'label' => 'Spalte 2 – Text',  'control' => 'textarea', 'rows' => 4],
            'col_3_title' => ['type' => 'string', 'max' => 200,  'label' => 'Spalte 3 – Titel', 'control' => 'input'],
            'col_3_text'  => ['type' => 'string', 'max' => 1000, 'label' => 'Spalte 3 – Text',  'control' => 'textarea', 'rows' => 4],
        ];
    }

    public function validate(array $data): array
    {
        $clean = parent::validate($data);

        if (!in_array($clean['col_count'] ?? '', ['2', '3'], true)) {
            $clean['col_count'] = '2';
        }

        // Dritte Spalte leer setzen wenn 2-spaltig
        if ($clean['col_count'] === '2') {
            $clean['col_3_title'] = '';
            $clean['col_3_text']  = '';
        }

        return $clean;
    }
}
