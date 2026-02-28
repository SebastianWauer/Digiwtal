<?php
$title = 'Admin-Benutzer';
ob_start();
?>
<div style="max-width: 900px; margin: 40px auto; padding: 20px;">
    <div style="background: white; padding: 24px 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
            <h1 style="margin: 0; font-size: 24px;">Admin-Benutzer</h1>
            <div style="display: flex; gap: 8px;">
                <a href="/admin/admin-users/create" style="padding: 8px 16px; background: #2563eb; color: white; text-decoration: none; border-radius: 4px; font-size: 13px; font-weight: 500;">+ Einladen</a>
                <a href="/admin/dashboard" style="padding: 8px 14px; background: #64748b; color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">← Dashboard</a>
            </div>
        </div>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div style="background: #d1fae5; color: #065f46; padding: 12px; border-radius: 4px; margin-bottom: 16px;">
                <?php echo htmlspecialchars((string)$_SESSION['flash_success'], ENT_QUOTES); unset($_SESSION['flash_success']); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_errors'])): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 4px; margin-bottom: 16px;">
                <?php foreach ($_SESSION['flash_errors'] as $e): ?>
                    <div><?php echo htmlspecialchars((string)$e, ENT_QUOTES); ?></div>
                <?php endforeach; unset($_SESSION['flash_errors']); ?>
            </div>
        <?php endif; ?>

        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr style="border-bottom: 2px solid #e2e8f0;">
                    <th style="text-align: left; padding: 10px 8px; font-weight: 600;">E-Mail</th>
                    <th style="text-align: left; padding: 10px 8px; font-weight: 600;">Rolle</th>
                    <th style="text-align: left; padding: 10px 8px; font-weight: 600;">Letzter Login</th>
                    <th style="text-align: left; padding: 10px 8px; font-weight: 600;">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php
                    $isSelf = (int)($u['id'] ?? 0) === (int)($_SESSION['admin_id'] ?? -1);
                    $roleColor = ($u['role'] ?? '') === 'superadmin' ? '#7c3aed' : '#0891b2';
                    ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 10px 8px; font-weight: 500;">
                            <?php echo htmlspecialchars((string)($u['email'] ?? ''), ENT_QUOTES); ?>
                            <?php if ($isSelf): ?><span style="font-size: 11px; color: #94a3b8; margin-left: 6px;">(du)</span><?php endif; ?>
                        </td>
                        <td style="padding: 10px 8px;">
                            <span style="font-size: 12px; padding: 3px 10px; border-radius: 12px; background: <?php echo $roleColor; ?>20; color: <?php echo $roleColor; ?>; font-weight: 600; text-transform: uppercase;">
                                <?php echo htmlspecialchars((string)($u['role'] ?? 'operator'), ENT_QUOTES); ?>
                            </span>
                        </td>
                        <td style="padding: 10px 8px; color: #64748b; font-size: 13px;">
                            <?php echo htmlspecialchars((string)($u['last_login_at'] ?? '—'), ENT_QUOTES); ?>
                        </td>
                        <td style="padding: 10px 8px;">
                            <?php if (!$isSelf): ?>
                                <form method="POST" action="/admin/admin-users/<?php echo (int)$u['id']; ?>/delete" onsubmit="return confirm('Admin-Benutzer wirklich löschen?')" style="display: inline;">
                                    <?php echo Csrf::field(); ?>
                                    <button type="submit" style="background: none; border: none; color: #dc2626; cursor: pointer; font-size: 13px; padding: 0;">Löschen</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #94a3b8; font-size: 13px;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
