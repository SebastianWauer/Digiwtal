<?php
declare(strict_types=1);

class AdminUserController
{
    public function __construct(
        private AdminUserRepository $userRepo,
        private AuditLogger $audit
    ) {}

    public function index(): void
    {
        AdminAuth::requireAuth();
        $this->requireSuperadmin();

        $users = $this->userRepo->listAll();
        require __DIR__ . '/../views/admin_users/index.php';
    }

    public function create(): void
    {
        AdminAuth::requireAuth();
        $this->requireSuperadmin();

        $errors = $_SESSION['flash_errors'] ?? null;
        unset($_SESSION['flash_errors']);
        require __DIR__ . '/../views/admin_users/create.php';
    }

    public function store(): void
    {
        AdminAuth::requireAuth();
        $this->requireSuperadmin();

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['Ungültige Anfrage.'];
            header('Location: /admin/admin-users/create');
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'operator';
        $errors = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ungültige E-Mail-Adresse.';
        }
        if (strlen($password) < 12) {
            $errors[] = 'Passwort muss mindestens 12 Zeichen haben.';
        }
        if (!in_array($role, ['superadmin', 'operator'], true)) {
            $role = 'operator';
        }
        if ($this->userRepo->findByEmail($email) !== null) {
            $errors[] = 'Diese E-Mail-Adresse ist bereits registriert.';
        }

        if (!empty($errors)) {
            $_SESSION['flash_errors'] = $errors;
            header('Location: /admin/admin-users/create');
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $newId = $this->userRepo->create($email, $hash, $role);

        $this->audit->log('admin_user.create', 'admin_user', $newId, "email: {$email}, role: {$role}");
        $_SESSION['flash_success'] = "Admin-Benutzer {$email} angelegt.";
        header('Location: /admin/admin-users');
        exit;
    }

    public function delete(int $userId): void
    {
        AdminAuth::requireAuth();
        $this->requireSuperadmin();

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            header('Location: /admin/admin-users');
            exit;
        }

        $currentId = (int)($_SESSION['admin_id'] ?? 0);
        if ($userId === $currentId) {
            $_SESSION['flash_errors'] = ['Du kannst dein eigenes Konto nicht löschen.'];
            header('Location: /admin/admin-users');
            exit;
        }

        $this->userRepo->delete($userId);
        $this->audit->log('admin_user.delete', 'admin_user', $userId);
        $_SESSION['flash_success'] = 'Admin-Benutzer gelöscht.';
        header('Location: /admin/admin-users');
        exit;
    }

    private function requireSuperadmin(): void
    {
        if (($_SESSION['admin_role'] ?? 'operator') !== 'superadmin') {
            http_response_code(403);
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403</title></head>'
                . '<body style="font-family:system-ui;text-align:center;padding:60px">'
                . '<h1 style="color:#dc2626">403 - Kein Zugriff</h1>'
                . '<p>Nur Superadmins haben Zugriff auf diesen Bereich.</p>'
                . '<a href="/admin/dashboard">← Dashboard</a></body></html>';
            exit;
        }
    }
}
