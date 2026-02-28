<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class MediaFolderRepositoryDb
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listAll(): array
    {
        $st = $this->pdo->query("
            SELECT id, parent_id, name, sort_order, created_at, updated_at
            FROM media_folders
            ORDER BY parent_id IS NULL DESC, parent_id ASC, sort_order ASC, name ASC
        ");
        $rows = $st->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare("
            SELECT id, parent_id, name, sort_order, created_at, updated_at
            FROM media_folders
            WHERE id = :id
            LIMIT 1
        ");
        $st->execute([':id' => $id]);
        $row = $st->fetch();

        return is_array($row) ? $row : null;
    }

    public function findByParentAndName(?int $parentId, string $name): ?array
    {
        $name = trim($name);
        if ($name === '') return null;

        if ($parentId === null) {
            $st = $this->pdo->prepare("
                SELECT id, parent_id, name, sort_order, created_at, updated_at
                FROM media_folders
                WHERE parent_id IS NULL AND name = :n
                LIMIT 1
            ");
            $st->execute([':n' => $name]);
        } else {
            $st = $this->pdo->prepare("
                SELECT id, parent_id, name, sort_order, created_at, updated_at
                FROM media_folders
                WHERE parent_id = :pid AND name = :n
                LIMIT 1
            ");
            $st->execute([':pid' => $parentId, ':n' => $name]);
        }

        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    public function createFolder(int $parentId, string $name, int $sortOrder = 0): int
    {
        $name = trim($name);
        if ($name === '') return 0;

        $st = $this->pdo->prepare("
            INSERT INTO media_folders (parent_id, name, sort_order, created_at, updated_at)
            VALUES (:pid, :n, :s, NOW(), NULL)
        ");
        $st->execute([
            ':pid' => $parentId > 0 ? $parentId : null,
            ':n'   => $name,
            ':s'   => $sortOrder,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateName(int $id, string $name): void
    {
        $name = trim($name);
        if ($id <= 0 || $name === '') return;

        $st = $this->pdo->prepare("
            UPDATE media_folders
            SET name = :n, updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $st->execute([':n' => $name, ':id' => $id]);
    }
}
