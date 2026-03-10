<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class EventCategoryRepositoryDb
{
    public function __construct(private PDO $pdo) {}

    public function listActive(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, name, slug, sort_order
            FROM event_categories
            WHERE is_deleted = 0
            ORDER BY sort_order ASC, name ASC
        ");
        $rows = $stmt ? $stmt->fetchAll() : [];
        return is_array($rows) ? $rows : [];
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name, slug, sort_order
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
        $stmt = $this->pdo->prepare("
            SELECT id, name, slug, sort_order
            FROM event_categories
            WHERE id = :id
              AND is_deleted = 0
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function updateName(int $id, string $name): void
    {
        $baseSlug = self::slugify($name);
        $slug = $baseSlug;
        $i = 2;
        while ($this->slugExistsForOther($slug, $id)) {
            $slug = $baseSlug . '-' . $i;
            $i++;
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

    public function create(string $name, string $slug, int $sortOrder = 100): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO event_categories (name, slug, sort_order, is_deleted)
            VALUES (:name, :slug, :sort_order, 0)
        ");
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':sort_order' => $sortOrder,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public static function slugify(string $name): string
    {
        $slug = mb_strtolower(trim($name), 'UTF-8');
        $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'kategorie';
    }
}
