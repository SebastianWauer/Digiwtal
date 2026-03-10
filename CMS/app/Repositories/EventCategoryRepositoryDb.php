<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class EventCategoryRepositoryDb
{
    private ?bool $hasColorHexColumn = null;

    public function __construct(private PDO $pdo) {}

    public function listActive(): array
    {
        $colorSelect = $this->hasColorColumn() ? 'color_hex' : 'NULL AS color_hex';
        $stmt = $this->pdo->query("
            SELECT id, name, slug, {$colorSelect}, sort_order
            FROM event_categories
            WHERE is_deleted = 0
            ORDER BY sort_order ASC, name ASC
        ");
        $rows = $stmt ? $stmt->fetchAll() : [];
        return is_array($rows) ? $rows : [];
    }

    public function findBySlug(string $slug): ?array
    {
        $colorSelect = $this->hasColorColumn() ? 'color_hex' : 'NULL AS color_hex';
        $stmt = $this->pdo->prepare("
            SELECT id, name, slug, {$colorSelect}, sort_order
            FROM event_categories
            WHERE slug = :slug
            LIMIT 1
        ");
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findById(int $id): ?array
    {
        $colorSelect = $this->hasColorColumn() ? 'color_hex' : 'NULL AS color_hex';
        $stmt = $this->pdo->prepare("
            SELECT id, name, slug, {$colorSelect}, sort_order
            FROM event_categories
            WHERE id = :id
              AND is_deleted = 0
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function update(int $id, string $name, ?string $colorHex = null): void
    {
        $baseSlug = self::slugify($name);
        $slug = $baseSlug;
        $i = 2;
        while ($this->slugExistsForOther($slug, $id)) {
            $slug = $baseSlug . '-' . $i;
            $i++;
        }

        if ($this->hasColorColumn()) {
            $stmt = $this->pdo->prepare("
                UPDATE event_categories
                SET name = :name,
                    slug = :slug,
                    color_hex = :color_hex
                WHERE id = :id
                  AND is_deleted = 0
                LIMIT 1
            ");
            $stmt->execute([
                ':id' => $id,
                ':name' => $name,
                ':slug' => $slug,
                ':color_hex' => $this->normalizeColorHex($colorHex),
            ]);
            return;
        }

        $stmt = $this->pdo->prepare("
            UPDATE event_categories
            SET name = :name,
                slug = :slug
            WHERE id = :id
              AND is_deleted = 0
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':slug' => $slug,
        ]);
    }

    private function slugExistsForOther(string $slug, int $exceptId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM event_categories
            WHERE slug = :slug
              AND id <> :id
            LIMIT 1
        ");
        $stmt->execute([
            ':slug' => $slug,
            ':id' => $exceptId,
        ]);
        return (bool)$stmt->fetchColumn();
    }

    public function create(string $name, string $slug, int $sortOrder = 100, ?string $colorHex = null): int
    {
        if ($this->hasColorColumn()) {
            $stmt = $this->pdo->prepare("
                INSERT INTO event_categories (name, slug, color_hex, sort_order, is_deleted)
                VALUES (:name, :slug, :color_hex, :sort_order, 0)
            ");
            $stmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':color_hex' => $this->normalizeColorHex($colorHex),
                ':sort_order' => $sortOrder,
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO event_categories (name, slug, sort_order, is_deleted)
                VALUES (:name, :slug, :sort_order, 0)
            ");
            $stmt->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':sort_order' => $sortOrder,
            ]);
        }
        return (int)$this->pdo->lastInsertId();
    }

    private function hasColorColumn(): bool
    {
        if ($this->hasColorHexColumn !== null) {
            return $this->hasColorHexColumn;
        }
        $stmt = $this->pdo->query("SHOW COLUMNS FROM `event_categories` LIKE 'color_hex'");
        $row = $stmt ? $stmt->fetch() : false;
        $exists = is_array($row);
        if (!$exists) {
            try {
                $this->pdo->exec("ALTER TABLE `event_categories` ADD COLUMN `color_hex` VARCHAR(7) NULL DEFAULT NULL AFTER `slug`");
            } catch (\Throwable) {
                // Keep graceful fallback without hard failure when schema cannot be altered.
            }
            $stmt2 = $this->pdo->query("SHOW COLUMNS FROM `event_categories` LIKE 'color_hex'");
            $row2 = $stmt2 ? $stmt2->fetch() : false;
            $exists = is_array($row2);
        }
        $this->hasColorHexColumn = $exists;
        return $this->hasColorHexColumn;
    }

    private function normalizeColorHex(?string $value): ?string
    {
        $raw = strtoupper(trim((string)$value));
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^#[0-9A-F]{6}$/', $raw) === 1) {
            return $raw;
        }
        return null;
    }

    public static function slugify(string $name): string
    {
        $slug = mb_strtolower(trim($name), 'UTF-8');
        $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'kategorie';
    }
}
