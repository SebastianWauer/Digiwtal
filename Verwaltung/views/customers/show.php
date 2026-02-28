<?php
$title = 'Kunde: ' . htmlspecialchars((string)($customer['name'] ?? ''), ENT_QUOTES);
ob_start();
?>
<div style="max-width: 1100px; margin: 40px auto; padding: 20px;">

    <!-- Header -->
    <div style="background: white; padding: 24px 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
                <h1 style="margin: 0 0 4px 0; font-size: 24px;">
                    <?php echo htmlspecialchars((string)($customer['name'] ?? ''), ENT_QUOTES); ?>
                    <span style="font-size: 14px; color: #64748b; font-weight: 400; margin-left: 8px;">
                        <?php echo htmlspecialchars((string)($customer['domain'] ?? ''), ENT_QUOTES); ?>
                    </span>
                </h1>
                <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 6px;">
                    <?php
                    $aboStatus = (string)($customer['abo_status'] ?? 'inactive');
                    $aboColor = $aboStatus === 'active' ? '#059669' : '#dc2626';
                    $isActive = (int)($customer['is_active'] ?? 0) === 1;
                    ?>
                    <span style="font-size: 12px; padding: 3px 10px; border-radius: 12px; background: <?php echo $aboColor; ?>20; color: <?php echo $aboColor; ?>; font-weight: 600;">
                        <?php echo htmlspecialchars(strtoupper($aboStatus), ENT_QUOTES); ?>
                    </span>
                    <span style="font-size: 12px; color: #64748b;">
                        <?php echo $isActive ? '● Aktiv' : '○ Inaktiv'; ?>
                    </span>
                </div>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="/admin/customers/<?php echo (int)$customer['id']; ?>/edit" style="padding: 8px 14px; background: #2563eb; color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">Bearbeiten</a>
                <a href="/admin/customers/<?php echo (int)$customer['id']; ?>/deployments" style="padding: 8px 14px; background: #d97706; color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">Deploy</a>
                <a href="/admin/customers/<?php echo (int)$customer['id']; ?>/access" style="padding: 8px 14px; background: #0891b2; color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">Zugang</a>
                <a href="/admin/customers/<?php echo (int)$customer['id']; ?>/webhooks" style="padding: 8px 14px; background: #059669; color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">🔗 Webhooks</a>
                <a href="/admin/customers" style="padding: 8px 14px; background: #64748b; color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">← Kunden</a>
            </div>
        </div>
    </div>

    <!-- Aktueller Status -->
    <div style="background: white; padding: 24px 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px;">
        <h2 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: #374151;">Aktueller Status</h2>
        <?php if ($latestCheck): ?>
            <?php
            $st = (string)($latestCheck['status'] ?? 'unknown');
            $stColor = match($st) { 'healthy' => '#22c55e', 'degraded' => '#eab308', default => '#ef4444' };
            ?>
            <div style="display: flex; gap: 24px; flex-wrap: wrap;">
                <div style="text-align: center; min-width: 80px;">
                    <div style="font-size: 36px; color: <?php echo $stColor; ?>;">●</div>
                    <div style="font-size: 12px; font-weight: 600; color: <?php echo $stColor; ?>;"><?php echo htmlspecialchars(strtoupper($st), ENT_QUOTES); ?></div>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; flex: 1;">
                    <div><div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; margin-bottom: 2px;">Response</div><div style="font-weight: 600;"><?php echo (int)($latestCheck['response_ms'] ?? 0); ?>ms</div></div>
                    <div><div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; margin-bottom: 2px;">CMS Version</div><div style="font-weight: 600;"><?php echo htmlspecialchars((string)($latestCheck['cms_version'] ?? '—'), ENT_QUOTES); ?></div></div>
                    <div><div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; margin-bottom: 2px;">PHP</div><div style="font-weight: 600;"><?php echo htmlspecialchars((string)($latestCheck['php_version'] ?? '—'), ENT_QUOTES); ?></div></div>
                    <div><div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; margin-bottom: 2px;">Letzter Check</div><div style="font-weight: 600;"><?php echo htmlspecialchars((string)($latestCheck['checked_at'] ?? '—'), ENT_QUOTES); ?></div></div>
                </div>
            </div>
        <?php else: ?>
            <p style="color: #64748b;">Noch kein Health-Check durchgeführt.</p>
        <?php endif; ?>
    </div>

    <!-- Health-Verlauf -->
    <div style="background: white; padding: 24px 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h2 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: #374151;">
            Health-Verlauf
            <span style="font-size: 12px; font-weight: 400; color: #64748b; margin-left: 8px;">(letzte 50 Checks)</span>
        </h2>
        <?php if (empty($healthHistory)): ?>
            <p style="color: #64748b;">Noch keine Einträge.</p>
        <?php else: ?>
            <!-- Mini-Chart: Status-Timeline -->
            <div style="display: flex; gap: 3px; margin-bottom: 20px; flex-wrap: wrap;">
                <?php foreach (array_reverse($healthHistory) as $hc): ?>
                    <?php
                    $s = (string)($hc['status'] ?? 'unknown');
                    $c = match($s) { 'healthy' => '#22c55e', 'degraded' => '#eab308', default => '#ef4444' };
                    $tip = htmlspecialchars($s . ' – ' . ($hc['checked_at'] ?? ''), ENT_QUOTES);
                    ?>
                    <div title="<?php echo $tip; ?>" style="width: 12px; height: 32px; background: <?php echo $c; ?>; border-radius: 2px; opacity: 0.85;"></div>
                <?php endforeach; ?>
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0;">
                        <th style="text-align: left; padding: 8px 6px; font-weight: 600; color: #374151;">Status</th>
                        <th style="text-align: left; padding: 8px 6px; font-weight: 600; color: #374151;">Zeitpunkt</th>
                        <th style="text-align: right; padding: 8px 6px; font-weight: 600; color: #374151;">Response</th>
                        <th style="text-align: left; padding: 8px 6px; font-weight: 600; color: #374151;">CMS</th>
                        <th style="text-align: left; padding: 8px 6px; font-weight: 600; color: #374151;">PHP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($healthHistory as $hc): ?>
                        <?php
                        $s = (string)($hc['status'] ?? 'unknown');
                        $c = match($s) { 'healthy' => '#22c55e', 'degraded' => '#eab308', default => '#ef4444' };
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 8px 6px;">
                                <span style="color: <?php echo $c; ?>; font-size: 18px; vertical-align: middle;">●</span>
                                <span style="font-size: 12px; color: <?php echo $c; ?>; margin-left: 4px; font-weight: 500;"><?php echo htmlspecialchars(strtoupper($s), ENT_QUOTES); ?></span>
                            </td>
                            <td style="padding: 8px 6px; color: #64748b;"><?php echo htmlspecialchars((string)($hc['checked_at'] ?? ''), ENT_QUOTES); ?></td>
                            <td style="padding: 8px 6px; text-align: right; font-family: monospace;"><?php echo (int)($hc['response_ms'] ?? 0); ?>ms</td>
                            <td style="padding: 8px 6px; color: #64748b;"><?php echo htmlspecialchars((string)($hc['cms_version'] ?? '—'), ENT_QUOTES); ?></td>
                            <td style="padding: 8px 6px; color: #64748b;"><?php echo htmlspecialchars((string)($hc['php_version'] ?? '—'), ENT_QUOTES); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
