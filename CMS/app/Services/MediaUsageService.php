<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\MediaUsageRepositoryDb;
use App\Repositories\MediaRepositoryDb;
use PDO;

final class MediaUsageService
{
    public function __construct(
        private PDO $pdo,
        private MediaUsageRepositoryDb $usageRepo,
        private MediaRepositoryDb $mediaRepo
    ) {}

    /**
     * Synchronisiert Media-Usages für eine Seite anhand content_json.
     * Minimalinvasiv: erkennt nur kanonische Media-URLs aus dem MediaManager:
     *   /media/file?id=123
     *   /media/thumb?id=123
     *
     * @param int $pageId
     * @param string $contentJson
     */
    public function syncPageUsages(int $pageId, string $contentJson): void
    {
        if ($pageId <= 0) return;

        $data = json_decode($contentJson, true);
        if ($data === null && trim($contentJson) !== '' && strtolower(trim($contentJson)) !== 'null') {
            // invalid JSON -> nichts syncen (nicht kaputtmachen)
            return;
        }

        // Welche Media-IDs waren vorher für diese Seite verknüpft? (damit wir usage_count für "alte" IDs korrekt auf 0 setzen können)
        $beforeIds = $this->listDistinctMediaIdsForEntity('page', $pageId);

        // Neue Usages extrahieren
        $rows = [];
        $this->scanForMediaUrls($data, '', $rows);

        // dedupe
        $uniq = [];
        foreach ($rows as $r) {
            $mid = (int)($r['media_id'] ?? 0);
            $fk  = (string)($r['field_key'] ?? '');
            if ($mid <= 0 || $fk === '') continue;
            $uniq[$mid . '|' . $fk] = ['media_id' => $mid, 'field_key' => $fk];
        }
        $rows = array_values($uniq);

        $this->pdo->beginTransaction();
        try {
            $this->usageRepo->deleteForEntity('page', $pageId);
            $this->usageRepo->insertIgnoreBulk('page', $pageId, $rows);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        // usage_count Cache aktualisieren (betroffene media_ids: vorher + nachher)
        $afterIds = [];
        foreach ($rows as $r) $afterIds[] = (int)$r['media_id'];

        $all = array_values(array_unique(array_filter(array_merge($beforeIds, $afterIds), fn($x) => (int)$x > 0)));
        if (!$all) return;

        $counts = $this->usageRepo->countForMediaBulk($all);

        foreach ($all as $mid) {
            $c = (int)($counts[$mid] ?? 0);
            $this->mediaRepo->setUsageCount((int)$mid, $c);
        }
    }
    
    /**
     * Synchronisiert Media-Usages für globale Site-Settings (site_settings Tabelle).
     * Erkennt alle Keys, die auf "_media_id" enden und einen numerischen Wert > 0 haben.
     *
     * @param array<string,mixed> $settings
     */
    public function syncSiteSettingsUsages(array $settings): void
    {
        // globales Singleton-Entity (damit deleteForEntity greift)
        $entityType = 'site_settings';
        $entityId   = 1;

        // Vorher verknüpfte Media-IDs merken (für usage_count Recalc)
        $beforeIds = $this->listDistinctMediaIdsForEntity($entityType, $entityId);

        $rows = [];
        foreach ($settings as $k => $v) {
            $key = trim((string)$k);
            if ($key === '') continue;
            if (!str_ends_with($key, '_media_id')) continue;

            $id = (int)$v;
            if ($id <= 0) continue;

            $rows[] = ['media_id' => $id, 'field_key' => $key];
        }

        // dedupe
        $uniq = [];
        foreach ($rows as $r) {
            $mid = (int)($r['media_id'] ?? 0);
            $fk  = (string)($r['field_key'] ?? '');
            if ($mid <= 0 || $fk === '') continue;
            $uniq[$mid . '|' . $fk] = ['media_id' => $mid, 'field_key' => $fk];
        }
        $rows = array_values($uniq);

        $this->pdo->beginTransaction();
        try {
            $this->usageRepo->deleteForEntity($entityType, $entityId);
            $this->usageRepo->insertIgnoreBulk($entityType, $entityId, $rows);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $afterIds = [];
        foreach ($rows as $r) $afterIds[] = (int)$r['media_id'];

        $all = array_values(array_unique(array_filter(array_merge($beforeIds, $afterIds), fn($x) => (int)$x > 0)));
        if (!$all) return;

        $counts = $this->usageRepo->countForMediaBulk($all);
        foreach ($all as $mid) {
            $c = (int)($counts[$mid] ?? 0);
            $this->mediaRepo->setUsageCount((int)$mid, $c);
        }
    }

    /**
     * Rekursiver Scan (Arrays/Strings) nach /media/file?id=123 bzw /media/thumb?id=123
     *
     * @param mixed $node
     * @param string $path
     * @param array<int,array{media_id:int,field_key:string}> $out
     */
    private function scanForMediaUrls(mixed $node, string $path, array &$out): void
    {
        if (is_string($node)) {
            $mid = $this->extractMediaIdFromUrl($node);
            if ($mid > 0) {
                $out[] = ['media_id' => $mid, 'field_key' => $path !== '' ? $path : 'content'];
            }
            return;
        }

        if (is_array($node)) {
            foreach ($node as $k => $v) {
                $key = is_int($k) ? '[' . $k . ']' : (string)$k;
                $next = ($path === '') ? $key : ($path . '.' . $key);
                $this->scanForMediaUrls($v, $next, $out);
            }
        }
    }

    private function extractMediaIdFromUrl(string $s): int
    {
        // canonical:
        // /media/file?id=123
        // /media/thumb?id=123
        if (preg_match('~\/media\/(file|thumb)\?[^#]*\bid\s*=\s*([0-9]+)~i', $s, $m)) {
            return (int)$m[2];
        }
        return 0;
    }

    /**
     * @return array<int,int>
     */
    private function listDistinctMediaIdsForEntity(string $entityType, int $entityId): array
    {
        $entityType = trim($entityType);
        if ($entityType === '' || $entityId <= 0) return [];

        $st = $this->pdo->prepare("
            SELECT DISTINCT media_id
            FROM media_usages
            WHERE entity_type = :t AND entity_id = :id
        ");
        $st->execute([':t' => $entityType, ':id' => $entityId]);
        $rows = $st->fetchAll();

        $out = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (!is_array($r)) continue;
                $mid = (int)($r['media_id'] ?? 0);
                if ($mid > 0) $out[] = $mid;
            }
        }
        return array_values(array_unique($out));
    }
}
