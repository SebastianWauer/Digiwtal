<?php
$title = 'Dashboard';
ob_start();
?>
<div class="view-stack">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Kunden-Übersicht</h1>
                <p class="page-subtitle">Alle aktiven Kunden, Health-Signale und Runtime-Daten auf einen Blick.</p>
            </div>
            <div class="page-actions">
                <a class="btn btn--secondary btn--sm" href="/admin/audit">Audit-Log</a>
                <?php if (($_SESSION['admin_role'] ?? '') === 'superadmin'): ?>
                    <a class="btn btn--info btn--sm" href="/admin/admin-users">Admins</a>
                <?php endif; ?>
                <a class="btn btn--primary btn--sm" href="/admin/customers">Kunden verwalten</a>
            </div>
        </header>

        <?php if (empty($customers)): ?>
            <p class="empty-state">Keine Kunden vorhanden.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Name</th>
                            <th>Domain</th>
                            <th>Health CMS</th>
                            <th>Health Frontend</th>
                            <th>Response</th>
                            <th>CMS Version</th>
                            <th>PHP Version</th>
                            <th>Letzter Check</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($customers as $c): ?>
                        <?php
                        $ampel = (string)($c['ampel'] ?? 'red');
                        $ampelClass = match ($ampel) {
                            'green' => 'status-dot--green',
                            'yellow' => 'status-dot--yellow',
                            'red' => 'status-dot--red',
                            default => 'status-dot--muted',
                        };
                        $cmsHealth = (string)($c['health_cms_status'] ?? $c['health_status'] ?? 'unknown');
                        $frontendHealth = (string)($c['health_frontend_status'] ?? 'n/a');
                        $cmsClass = match ($cmsHealth) {
                            'healthy' => 'healthy',
                            'degraded' => 'degraded',
                            'down' => 'down',
                            'timeout' => 'timeout',
                            default => 'unknown',
                        };
                        $frontendClass = match ($frontendHealth) {
                            'healthy' => 'healthy',
                            'degraded' => 'degraded',
                            'down' => 'down',
                            'timeout' => 'timeout',
                            'n/a' => 'na',
                            default => 'unknown',
                        };
                        ?>
                        <tr>
                            <td><span class="status-dot <?php echo $ampelClass; ?>"></span></td>
                            <td><?php echo htmlspecialchars((string)($c['name'] ?? ''), ENT_QUOTES); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars((string)($c['domain'] ?? ''), ENT_QUOTES); ?></td>
                            <td>
                                <button type="button" class="badge-button badge-button--<?php echo $cmsClass; ?>" data-health-detail="<?php echo htmlspecialchars((string)($c['health_cms_detail'] ?? ''), ENT_QUOTES); ?>">
                                    <?php echo htmlspecialchars($cmsHealth, ENT_QUOTES); ?>
                                </button>
                            </td>
                            <td>
                                <button type="button" class="badge-button badge-button--<?php echo $frontendClass; ?>" data-health-detail="<?php echo htmlspecialchars((string)($c['health_frontend_detail'] ?? ''), ENT_QUOTES); ?>">
                                    <?php echo htmlspecialchars($frontendHealth, ENT_QUOTES); ?>
                                </button>
                            </td>
                            <td class="mono"><?php echo (int)($c['response_ms'] ?? 0); ?>ms</td>
                            <td class="mono"><?php echo htmlspecialchars((string)($c['cms_version'] ?? '-'), ENT_QUOTES); ?></td>
                            <td class="mono"><?php echo htmlspecialchars((string)($c['php_version'] ?? 'n/a'), ENT_QUOTES); ?></td>
                            <td class="text-muted">
                                <?php
                                $lastCheck = (string)($c['last_check_at'] ?? '');
                                if ($lastCheck !== '') {
                                    $checkTime = strtotime($lastCheck);
                                    $diff = time() - $checkTime;
                                    echo $diff < 3600 ? ('vor ' . ceil($diff / 60) . ' Min') : date('d.m.Y H:i', $checkTime);
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
