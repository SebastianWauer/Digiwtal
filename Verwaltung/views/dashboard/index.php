<?php
$title = 'Dashboard';
ob_start();
?>
<div style="max-width: 1200px; margin: 40px auto; padding: 20px;">
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1 style="margin: 0; font-size: 28px;">Kunden-Übersicht</h1>
            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="/admin/audit"
                style="padding: 8px 16px; background: #475569; color: white;
                        text-decoration: none; border-radius: 4px; font-size: 14px;">
                    📋 Audit-Log
                </a>
                <?php if (($_SESSION['admin_role'] ?? '') === 'superadmin'): ?>
                    <a href="/admin/admin-users"
                    style="padding: 8px 16px; background: #7c3aed; color: white;
                            text-decoration: none; border-radius: 4px; font-size: 14px;">
                        👤 Admins
                    </a>
                <?php endif; ?>
                <a href="/admin/customers" 
                style="padding: 8px 16px; background: #2563eb; color: white; 
                        text-decoration: none; border-radius: 4px; font-size: 14px;">
                    Kunden verwalten
                </a>
                <a href="/admin/logout" 
                style="color: #dc2626; text-decoration: none; font-weight: 500;">
                    Logout
                </a>
            </div>
        </div>
        
        <?php if (empty($customers)): ?>
            <p style="color: #64748b;">Keine Kunden vorhanden.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0;">
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Status</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Name</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Domain</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Health CMS</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Health Frontend</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Response</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">CMS Version</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">PHP Version</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Letzter Check</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                        <?php
                        $ampel = (string)($c['ampel'] ?? 'red');
                        $ampelColor = match($ampel) {
                            'green' => '#22c55e',
                            'yellow' => '#eab308',
                            'red' => '#ef4444',
                            default => '#94a3b8'
                        };
                        $ampelText = match($ampel) {
                            'green' => '●',
                            'yellow' => '●',
                            'red' => '●',
                            default => '○'
                        };
                        $cmsHealth = (string)($c['health_cms_status'] ?? $c['health_status'] ?? 'unknown');
                        $frontendHealth = (string)($c['health_frontend_status'] ?? 'n/a');
                        $cmsColor = match($cmsHealth) {
                            'healthy' => '#22c55e',
                            'degraded' => '#eab308',
                            'down', 'timeout' => '#ef4444',
                            default => '#94a3b8'
                        };
                        $frontendColor = match($frontendHealth) {
                            'healthy' => '#22c55e',
                            'degraded' => '#eab308',
                            'down', 'timeout' => '#ef4444',
                            default => '#94a3b8'
                        };
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 12px 8px;">
                                <span style="color: <?php echo htmlspecialchars($ampelColor, ENT_QUOTES); ?>; font-size: 24px;"><?php echo $ampelText; ?></span>
                            </td>
                            <td style="padding: 12px 8px; font-weight: 500;">
                                <?php echo htmlspecialchars((string)($c['name'] ?? ''), ENT_QUOTES); ?>
                            </td>
                            <td style="padding: 12px 8px; color: #64748b; font-size: 14px;">
                                <?php echo htmlspecialchars((string)($c['domain'] ?? ''), ENT_QUOTES); ?>
                            </td>
                            <td style="padding: 12px 8px;">
                                <button type="button"
                                    data-health-detail="<?php echo htmlspecialchars((string)($c['health_cms_detail'] ?? ''), ENT_QUOTES); ?>"
                                    style="padding: 4px 8px; background: <?php echo htmlspecialchars($cmsColor, ENT_QUOTES); ?>20; color: <?php echo htmlspecialchars($cmsColor, ENT_QUOTES); ?>; border-radius: 4px; font-size: 12px; font-weight: 500; border: none; cursor: pointer;">
                                    <?php echo htmlspecialchars($cmsHealth, ENT_QUOTES); ?>
                                </button>
                            </td>
                            <td style="padding: 12px 8px;">
                                <button type="button"
                                    data-health-detail="<?php echo htmlspecialchars((string)($c['health_frontend_detail'] ?? ''), ENT_QUOTES); ?>"
                                    style="padding: 4px 8px; background: <?php echo htmlspecialchars($frontendColor, ENT_QUOTES); ?>20; color: <?php echo htmlspecialchars($frontendColor, ENT_QUOTES); ?>; border-radius: 4px; font-size: 12px; font-weight: 500; border: none; cursor: pointer;">
                                    <?php echo htmlspecialchars($frontendHealth, ENT_QUOTES); ?>
                                </button>
                            </td>
                            <td style="padding: 12px 8px; font-size: 12px;">
                                <?php echo (int)($c['response_ms'] ?? 0); ?>ms
                            </td>
                            <td style="padding: 12px 8px; font-size: 12px;">
                                <?php echo htmlspecialchars((string)($c['cms_version'] ?? '-'), ENT_QUOTES); ?>
                            </td>
                            <td style="padding: 12px 8px; font-size: 12px;">
                                <?php echo htmlspecialchars((string)($c['php_version'] ?? 'n/a'), ENT_QUOTES); ?>
                            </td>
                            <td style="padding: 12px 8px; color: #64748b; font-size: 14px;">
                                <?php
                                $lastCheck = (string)($c['last_check_at'] ?? '');
                                if ($lastCheck !== '') {
                                    $checkTime = strtotime($lastCheck);
                                    $diff = time() - $checkTime;
                                    if ($diff < 3600) {
                                        echo 'vor ' . ceil($diff / 60) . ' Min';
                                    } else {
                                        echo date('d.m.Y H:i', $checkTime);
                                    }
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
ob_start();
?>
<script>
document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-health-detail]');
    if (!button) return;
    var detail = button.getAttribute('data-health-detail') || 'Keine Details vorhanden.';
    window.alert(detail);
});
</script>
<?php
$extraScripts = ob_get_clean();
require __DIR__ . '/../layout.php';
