<?php
$title = 'Module';
ob_start();
?>
<div style="max-width: 1000px; margin: 40px auto; padding: 20px;">
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1 style="margin: 0; font-size: 28px;">Module</h1>
            <a href="/admin/dashboard" style="padding: 10px 20px; background: #64748b; color: white; text-decoration: none; border-radius: 4px; font-weight: 500;">← Dashboard</a>
        </div>
        
        <?php if (empty($modules)): ?>
            <p style="color: #64748b;">Keine Module vorhanden.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0;">
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Key</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Name</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Beschreibung</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modules as $m): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 12px 8px; font-family: monospace; font-size: 12px;">
                                <?php echo htmlspecialchars((string)($m['key_name'] ?? ''), ENT_QUOTES); ?>
                            </td>
                            <td style="padding: 12px 8px; font-weight: 500;">
                                <?php echo htmlspecialchars((string)($m['display_name'] ?? ''), ENT_QUOTES); ?>
                            </td>
                            <td style="padding: 12px 8px; color: #64748b; font-size: 14px;">
                                <?php echo htmlspecialchars((string)($m['description'] ?? ''), ENT_QUOTES); ?>
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
