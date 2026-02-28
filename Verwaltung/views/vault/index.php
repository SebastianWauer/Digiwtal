<?php
$title = 'Server-Tresor';
ob_start();
?>
<div style="max-width: 1000px; margin: 40px auto; padding: 20px;">
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <h1 style="margin: 0 0 10px 0; font-size: 28px;">Server-Tresor</h1>
                <p style="margin: 0; color: #64748b; font-size: 14px;">
                    Kunde: <strong><?php echo htmlspecialchars((string)($customer['name'] ?? ''), ENT_QUOTES); ?></strong>
                </p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/vault/create" style="padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 4px; font-weight: 500;">Neu</a>
                <a href="/admin/customers" style="padding: 10px 20px; background: #64748b; color: white; text-decoration: none; border-radius: 4px; font-weight: 500;">← Kunden</a>
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
        
        <?php if (empty($entries)): ?>
            <p style="color: #64748b;">Keine Zugangsdaten vorhanden.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0;">
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Label</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Host</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Username</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Aktualisiert</th>
                        <th style="text-align: right; padding: 12px 8px; font-weight: 600;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $e): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 12px 8px; font-weight: 500;">
                                <?php echo htmlspecialchars((string)($e['label'] ?? ''), ENT_QUOTES); ?>
                            </td>
                            <td style="padding: 12px 8px; color: #64748b; font-size: 14px;">
                                <?php echo htmlspecialchars((string)($e['host'] ?? ''), ENT_QUOTES); ?>
                            </td>
                            <td style="padding: 12px 8px; color: #64748b; font-size: 14px;">
                                <?php echo htmlspecialchars((string)($e['username'] ?? ''), ENT_QUOTES); ?>
                            </td>
                            <td style="padding: 12px 8px; color: #64748b; font-size: 14px;">
                                <?php echo htmlspecialchars((string)($e['updated_at'] ?? ''), ENT_QUOTES); ?>
                            </td>
                            <td style="padding: 12px 8px; text-align: right;">
                                <form method="POST" action="/admin/vault/<?php echo (int)($e['id'] ?? 0); ?>/reveal" style="display: inline;">
                                    <?php echo Csrf::field(); ?>
                                    <button type="submit" style="background: none; border: none; color: #2563eb; cursor: pointer; text-decoration: underline; margin-right: 10px;">Anzeigen</button>
                                </form>
                                <form method="POST" action="/admin/vault/<?php echo (int)($e['id'] ?? 0); ?>/rotate" style="display: inline; margin-right: 10px;">
                                    <?php echo Csrf::field(); ?>
                                    <input type="password" name="secret" placeholder="Neues Secret" required style="width: 120px; padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                                    <button type="submit" style="padding: 4px 8px; background: #7c3aed; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">Rotieren</button>
                                </form>
                                <form method="POST" action="/admin/vault/<?php echo (int)($e['id'] ?? 0); ?>/delete" style="display: inline;" onsubmit="return confirm('Wirklich löschen?');">
                                    <?php echo Csrf::field(); ?>
                                    <button type="submit" style="background: none; border: none; color: #dc2626; cursor: pointer; text-decoration: underline;">Löschen</button>
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
require __DIR__ . '/../layout.php';
