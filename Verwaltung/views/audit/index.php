<?php
$title = 'Audit-Log';
ob_start();
?>
<div style="max-width: 1200px; margin: 40px auto; padding: 20px;">
    <div style="background: white; padding: 24px 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
            <h1 style="margin: 0; font-size: 24px;">Audit-Log</h1>
            <a href="/admin/dashboard" style="padding: 8px 14px; background: #64748b; color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">← Dashboard</a>
        </div>

        <form method="GET" style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
            <input type="text" name="action" value="<?php echo htmlspecialchars((string)$filterAction, ENT_QUOTES); ?>"
                   placeholder="Aktion (z.B. deploy)" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; flex: 1; min-width: 160px;">
            <select name="entity" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                <option value="">Alle Entitäten</option>
                <?php foreach (['customer', 'deployment', 'admin_user', 'vault', 'module', 'server_access', 'auth'] as $ent): ?>
                    <option value="<?php echo $ent; ?>" <?php echo $filterEntity === $ent ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ent, ENT_QUOTES); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" style="padding: 8px 16px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">Filtern</button>
            <a href="/admin/audit" style="padding: 8px 16px; background: #e2e8f0; color: #374151; text-decoration: none; border-radius: 4px; font-size: 14px;">Reset</a>
        </form>

        <p style="color: #64748b; font-size: 13px; margin-bottom: 16px;">
            <?php echo number_format($total); ?> Einträge
            <?php if ($totalPages > 1): ?> – Seite <?php echo $page; ?> von <?php echo $totalPages; ?><?php endif; ?>
        </p>

        <?php if (empty($entries)): ?>
            <p style="color: #64748b;">Keine Einträge gefunden.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0;">
                        <th style="text-align: left; padding: 8px 6px; font-weight: 600;">Zeitpunkt</th>
                        <th style="text-align: left; padding: 8px 6px; font-weight: 600;">Admin</th>
                        <th style="text-align: left; padding: 8px 6px; font-weight: 600;">Aktion</th>
                        <th style="text-align: left; padding: 8px 6px; font-weight: 600;">Entität</th>
                        <th style="text-align: left; padding: 8px 6px; font-weight: 600;">Detail</th>
                        <th style="text-align: left; padding: 8px 6px; font-weight: 600;">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $e): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 8px 6px; color: #64748b; white-space: nowrap;"><?php echo htmlspecialchars((string)($e['created_at'] ?? ''), ENT_QUOTES); ?></td>
                            <td style="padding: 8px 6px;"><?php echo htmlspecialchars((string)($e['admin_email'] ?? '—'), ENT_QUOTES); ?></td>
                            <td style="padding: 8px 6px;">
                                <span style="font-family: monospace; font-size: 12px; background: #f1f5f9; padding: 2px 6px; border-radius: 3px;">
                                    <?php echo htmlspecialchars((string)($e['action'] ?? ''), ENT_QUOTES); ?>
                                </span>
                            </td>
                            <td style="padding: 8px 6px; color: #64748b;">
                                <?php echo htmlspecialchars((string)($e['entity'] ?? ''), ENT_QUOTES); ?>
                                <?php if ($e['entity_id']): ?><span style="color: #94a3b8;">#<?php echo (int)$e['entity_id']; ?></span><?php endif; ?>
                            </td>
                            <td style="padding: 8px 6px; color: #64748b; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars((string)($e['detail'] ?? ''), ENT_QUOTES); ?>">
                                <?php echo htmlspecialchars(substr((string)($e['detail'] ?? ''), 0, 80), ENT_QUOTES); ?>
                            </td>
                            <td style="padding: 8px 6px; font-family: monospace; font-size: 11px; color: #94a3b8;"><?php echo htmlspecialchars((string)($e['ip'] ?? ''), ENT_QUOTES); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
                <div style="display: flex; gap: 6px; margin-top: 20px; flex-wrap: wrap;">
                    <?php for ($p = max(1, $page - 3); $p <= min($totalPages, $page + 3); $p++): ?>
                        <a href="?page=<?php echo $p; ?>&action=<?php echo urlencode((string)$filterAction); ?>&entity=<?php echo urlencode((string)$filterEntity); ?>"
                           style="padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px;
                                  background: <?php echo $p === $page ? '#2563eb' : '#e2e8f0'; ?>;
                                  color: <?php echo $p === $page ? 'white' : '#374151'; ?>;">
                            <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
