<?php
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$title = 'Geheimnis anzeigen';
ob_start();
?>
<div style="max-width: 800px; margin: 40px auto; padding: 20px;">
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h1>Zugangsdaten</h1>
        
        <div style="margin-bottom: 20px;">
            <p><strong>Label:</strong> <?php echo htmlspecialchars((string)($meta['label'] ?? ''), ENT_QUOTES); ?></p>
            <p><strong>Host:</strong> <?php echo htmlspecialchars((string)($meta['host'] ?? ''), ENT_QUOTES); ?></p>
            <p><strong>Username:</strong> <?php echo htmlspecialchars((string)($meta['username'] ?? ''), ENT_QUOTES); ?></p>
        </div>
        
        <div style="background: #f1f5f9; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
            <p style="font-size: 12px; color: #64748b; margin-bottom: 8px;">Secret:</p>
            <pre style="margin: 0; word-break: break-all; white-space: pre-wrap; font-size: 13px;"><?php echo htmlspecialchars($secret, ENT_QUOTES); ?></pre>
        </div>
        
        <p style="font-size: 12px; color: #dc2626; margin-bottom: 20px;">
            ⚠️ Diese Seite wird nicht gecacht. Bitte kopieren Sie das Secret und schließen Sie diese Seite.
        </p>
        
        <div style="display: flex; gap: 10px;">
            <a href="/admin/customers/<?php echo (int)($meta['customer_id'] ?? 0); ?>/vault" style="padding: 10px 20px; background: #64748b; color: white; text-decoration: none; border-radius: 4px; font-weight: 500;">← Zurück</a>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
