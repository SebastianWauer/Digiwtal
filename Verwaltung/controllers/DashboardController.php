<?php
declare(strict_types=1);

class DashboardController
{
    private CustomerRepository $customerRepo;

    public function __construct(CustomerRepository $customerRepo)
    {
        $this->customerRepo = $customerRepo;
    }

    public function index(): void
    {
        AdminAuth::requireAuth();
        
        $customers = $this->customerRepo->listAllWithHealth();
        
        // Ampel-Logik
        foreach ($customers as &$customer) {
            $isActive = (int)($customer['is_active'] ?? 0) === 1;
            $status = (string)($customer['health_status'] ?? 'unknown');
            
            if (!$isActive) {
                $customer['ampel'] = 'red';
            } elseif ($status === 'healthy') {  // CHANGED from 'online'
                $customer['ampel'] = 'green';
            } elseif ($status === 'degraded') {
                $customer['ampel'] = 'yellow';
            } else {
                $customer['ampel'] = 'red';  // unknown, down, timeout, offline
            }
        }
        unset($customer);
        
        require __DIR__ . '/../views/dashboard/index.php';
    }
}
