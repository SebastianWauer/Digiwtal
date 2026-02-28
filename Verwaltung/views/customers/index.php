<?php
$title = 'Kunden';
ob_start();
?>
<div style="max-width: 1200px; margin: 40px auto; padding: 20px;">
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1 style="margin: 0; font-size: 28px;">Kunden</h1>
            <div style="display: flex; gap: 10px;">
                <a href="/admin/customers/create" style="padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 4px; font-weight: 500;">Neu</a>
                <a href="/admin/dashboard" style="padding: 10px 20px; background: #64748b; color: white; text-decoration: none; border-radius: 4px; font-weight: 500;">Dashboard</a>
                <a href="/admin/logout" style="color: #dc2626; text-decoration: none; padding: 10px;">Logout</a>
            </div>
        </div>
        
        <?php if (isset($success)): ?>
            <div style="background: #d1fae5; color: #065f46; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($success, ENT_QUOTES); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errors) && !empty($errors)): ?>
            <div style="background: #fee; color: #c00; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
                <?php foreach ($errors as $err): ?>
                    <div><?php echo htmlspecialchars($err, ENT_QUOTES); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($customers)): ?>
            <p style="color: #64748b;">Keine Kunden vorhanden.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0;">
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Status</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Name</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Domain</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Aktiv</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Health CMS</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Health Frontend</th>
                        <th style="text-align: right; padding: 12px 8px; font-weight: 600;">Aktionen</th>
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
                        $safeColor = htmlspecialchars($ampelColor, ENT_QUOTES);
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
                                <span style="color: <?php echo $safeColor; ?>; font-size: 24px;"><?php echo $ampelText; ?></span>
                            </td>
                            <td style="padding: 12px 8px; font-weight: 500;">
                                <a href="/admin/customers/<?php echo (int)($c['id'] ?? 0); ?>"
                                   style="color: #1e293b; text-decoration: none; font-weight: 600;">
                                    <?php echo htmlspecialchars((string)($c['name'] ?? ''), ENT_QUOTES); ?>
                                </a>
                            </td>
                            <td style="padding: 12px 8px; color: #64748b; font-size: 14px;">
                                <?php echo htmlspecialchars((string)($c['domain'] ?? ''), ENT_QUOTES); ?>
                            </td>
                            <td style="padding: 12px 8px;">
                                <?php echo ((int)($c['is_active'] ?? 0) === 1) ? '✓' : '✗'; ?>
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
                            <td style="padding: 12px 8px; text-align: right; font-size: 12px;">
                                <a href="/admin/customers/<?php echo (int)($c['id'] ?? 0); ?>/edit" style="color: #2563eb; text-decoration: none; margin-right: 8px;">Bearbeiten</a>
                                <a href="/admin/customers/<?php echo (int)($c['id'] ?? 0); ?>/modules" style="color: #7c3aed; text-decoration: none; margin-right: 8px;">Module</a>
                                <a href="/admin/customers/<?php echo (int)($c['id'] ?? 0); ?>/vault" style="color: #059669; text-decoration: none; margin-right: 8px;">Tresor</a>
                                <a href="/admin/customers/<?php echo (int)($c['id'] ?? 0); ?>/access" style="color: #0891b2; text-decoration: none; margin-right: 8px;">Zugang</a>
                                <a href="/admin/customers/<?php echo (int)($c['id'] ?? 0); ?>/deployments" style="color: #d97706; text-decoration: none; margin-right: 8px;">Deploy</a>
                                <form method="POST" action="/admin/customers/<?php echo (int)($c['id'] ?? 0); ?>/toggle" style="display: inline;">
                                    <?php echo Csrf::field(); ?>
                                    <button type="submit" style="background: none; border: none; color: #dc2626; cursor: pointer; text-decoration: underline;">
                                        <?php echo ((int)($c['is_active'] ?? 0) === 1) ? 'Deaktivieren' : 'Aktivieren'; ?>
                                    </button>
                                </form>
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
