<?php
declare(strict_types=1);

class WebhookController
{
    public function __construct(
        private WebhookTokenRepository $webhookRepo,
        private DeploymentRepository $deploymentRepo,
        private DeployService $deployService,
        private CustomerRepository $customerRepo,
        private AuditLogger $audit
    ) {}

    public function trigger(): void
    {
        $rawToken = trim((string)($_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? ''));
        if ($rawToken === '') {
            $this->json(['error' => 'Missing X-Webhook-Token header'], 401);
            return;
        }

        $body = json_decode((string)file_get_contents('php://input'), true);
        $requestedCustomerId = isset($body['customer_id']) ? (int)$body['customer_id'] : null;

        $allTokens = $this->webhookRepo->listAllEncrypted();
        $matched = null;

        foreach ($allTokens as $row) {
            $customerId = (int)($row['customer_id'] ?? 0);
            if ($requestedCustomerId !== null && $requestedCustomerId !== $customerId) {
                continue;
            }

            try {
                $aad = 'webhook:' . $customerId;
                $plaintext = VaultCrypto::decrypt(
                    (string)$row['token_enc'],
                    (string)$row['token_nonce'],
                    (string)$row['token_tag'],
                    $aad
                );
                if (hash_equals($plaintext, $rawToken)) {
                    $matched = $row;
                    break;
                }
            } catch (Throwable) {
                continue;
            }
        }

        if ($matched === null) {
            $this->json(['error' => 'Invalid token'], 403);
            return;
        }

        $customerId = (int)$matched['customer_id'];
        $deployType = (string)($matched['deploy_type'] ?? 'cms');
        $customer = $this->customerRepo->findById($customerId);

        if ($customer === null || (int)($customer['is_active'] ?? 0) !== 1) {
            $this->json(['error' => 'Customer not found or inactive'], 404);
            return;
        }

        foreach ($this->deploymentRepo->listRunning() as $dep) {
            if ((int)($dep['customer_id'] ?? 0) === $customerId) {
                $this->json(['error' => 'Deployment already running'], 409);
                return;
            }
        }

        $deploymentId = $this->deploymentRepo->create(
            $customerId,
            $deployType,
            'webhook_token:' . (int)$matched['id']
        );

        try {
            $success = $this->deployService->run($deploymentId, $customerId, $deployType);
            $this->webhookRepo->updateLastUsed((int)$matched['id']);

            if (!$success) {
                $this->json(['error' => 'Deploy failed', 'deployment_id' => $deploymentId], 500);
                return;
            }

            $this->audit->log(
                'webhook.deploy',
                'deployment',
                $deploymentId,
                "customer_id: {$customerId}, type: {$deployType}, token_label: " . (string)($matched['label'] ?? '')
            );

            $this->json([
                'ok' => true,
                'deployment_id' => $deploymentId,
                'customer_id' => $customerId,
                'type' => $deployType,
            ], 200);
        } catch (Throwable $e) {
            error_log('[WEBHOOK] Deploy failed: ' . $e->getMessage());
            $this->json(['error' => 'Deploy failed', 'deployment_id' => $deploymentId], 500);
        }
    }

    private function json(array $payload, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
    }
}
