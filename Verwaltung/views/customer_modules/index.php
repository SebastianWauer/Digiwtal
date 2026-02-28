<?php
$title = 'Kunden-Module';
ob_start();
?>
<div style="max-width: 1000px; margin: 40px auto; padding: 20px;">
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <h1 style="margin: 0 0 10px 0; font-size: 28px;">Module</h1>
                <p style="margin: 0; color: #64748b; font-size: 14px;">
                    Kunde: <strong><?php echo htmlspecialchars((string)($customer['name'] ?? ''), ENT_QUOTES); ?></strong>
                </p>
            </div>
            <a href="/admin/customers" style="padding: 10px 20px; background: #64748b; color: white; text-decoration: none; border-radius: 4px; font-weight: 500;">← Kunden</a>
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
        
        <?php if (empty($modules)): ?>
            <p style="color: #64748b;">Keine Module verfügbar.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0;">
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Modul</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Beschreibung</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Aktiviert</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Ablaufdatum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modules as $m): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 12px 8px; font-weight: 500;">
                                <?php echo htmlspecialchars((string)($m['display_name'] ?? ''), ENT_QUOTES); ?>
                            </td>
                            <td style="padding: 12px 8px; color: #64748b; font-size: 12px;">
                                <?php echo htmlspecialchars((string)($m['description'] ?? ''), ENT_QUOTES); ?>
                            </td>
                            <td style="padding: 12px 8px;">
                                <form method="POST" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/modules/<?php echo (int)($m['id'] ?? 0); ?>" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                    <?php echo Csrf::field(); ?>
                                    
                                    <label style="cursor: pointer; display: flex; align-items: center;">
                                        <input type="checkbox" name="is_enabled" <?php echo ((int)($m['is_enabled'] ?? 0) === 1) ? 'checked' : ''; ?> style="margin-right: 4px;">
                                        <span style="font-size: 14px;">Aktiv</span>
                                    </label>
                                    
                                    <input type="date" name="expires_at" value="<?php echo htmlspecialchars(substr((string)($m['expires_at'] ?? ''), 0, 10), ENT_QUOTES); ?>" style="padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                                    
                                    <button type="submit" style="padding: 4px 12px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">Speichern</button>
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
