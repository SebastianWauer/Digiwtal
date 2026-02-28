<?php
declare(strict_types=1);

class WebhookTokenRepository
{
    public function __construct(private PDO $pdo) {}

    public function listByCustomer(int $customerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT wt.id, wt.customer_id, wt.deploy_type, wt.label, wt.last_used_at, wt.created_at,
                    (
                        SELECT d.id
                        FROM deployments d
                        WHERE d.customer_id = wt.customer_id
                          AND d.triggered_by = CONCAT("webhook_token:", wt.id)
                        ORDER BY d.created_at DESC
                        LIMIT 1
                    ) AS last_deployment_id,
                    (
                        SELECT d.status
                        FROM deployments d
                        WHERE d.customer_id = wt.customer_id
                          AND d.triggered_by = CONCAT("webhook_token:", wt.id)
                        ORDER BY d.created_at DESC
                        LIMIT 1
                    ) AS last_deployment_status,
                    (
                        SELECT d.created_at
                        FROM deployments d
                        WHERE d.customer_id = wt.customer_id
                          AND d.triggered_by = CONCAT("webhook_token:", wt.id)
                        ORDER BY d.created_at DESC
                        LIMIT 1
                    ) AS last_deployment_created_at
             FROM webhook_tokens wt
             WHERE wt.customer_id = ?
             ORDER BY wt.id DESC'
        );
        $stmt->execute([$customerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    public function listAllEncrypted(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, customer_id, token_enc, token_nonce, token_tag, deploy_type, label
             FROM webhook_tokens ORDER BY id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    public function create(
        int $customerId,
        string $tokenEnc,
        string $tokenNonce,
        string $tokenTag,
        string $deployType,
        string $label
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO webhook_tokens
                (customer_id, token_enc, token_nonce, token_tag, deploy_type, label)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$customerId, $tokenEnc, $tokenNonce, $tokenTag, $deployType, $label]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateLastUsed(int $id): void
    {
        $this->pdo->prepare('UPDATE webhook_tokens SET last_used_at = NOW() WHERE id = ?')
            ->execute([$id]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM webhook_tokens WHERE id = ?')->execute([$id]);
    }
}
