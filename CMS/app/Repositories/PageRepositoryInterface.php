<?php
declare(strict_types=1);

namespace App\Repositories;

interface PageRepositoryInterface
{
    public function listActive(): array;
    public function listDeleted(): array;
    public function countDeleted(): int;
    public function listAll(): array;
    public function listActiveForPicker(): array;
    public function findById(int $id): ?array;
    public function findActiveBySlug(string $slug): ?array;
    public function findPublicBySlug(string $slug): ?array;
    public function findPublicHome(): ?array;
    public function listPublicNav(string $area): array;
    public function listNav(string $area): array;
    public function insert(
        string $slug,
        string $title,
        string $frontendTitle,
        string $subtitle,
        string $status,
        string $contentJson,
        bool $isHome,
        bool $navVisible,
        string $navLabel,
        string $navArea,
        int $navOrder
    ): int;
    public function update(
        int $id,
        string $slug,
        string $title,
        string $frontendTitle,
        string $subtitle,
        string $status,
        string $contentJson,
        bool $isHome,
        bool $navVisible,
        string $navLabel,
        string $navArea,
        int $navOrder
    ): void;
    public function setHome(int $id): void;
    public function softDelete(int $id): void;
    public function restore(int $id): void;
    public function slugExists(string $slug, ?int $ignoreId = null): bool;
    public function purgeDeleted(): int;
}
