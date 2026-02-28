<?php
declare(strict_types=1);

class CustomerDetailController
{
    public function __construct(
        private CustomerRepository $customerRepo,
        private PDO $pdo
    ) {}

    public function show(int $customerId): void
    {
        AdminAuth::requireAuth();

        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, status, checked_at, response_ms, cms_version, php_version, frontend_version
             FROM health_checks
             WHERE customer_id = ?
             ORDER BY checked_at DESC
             LIMIT 50'
        );
        $stmt->execute([$customerId]);
        $healthHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($healthHistory)) $healthHistory = [];

        $latestCheck = !empty($healthHistory) ? $healthHistory[0] : null;

        require __DIR__ . '/../views/customers/show.php';
    }
}
