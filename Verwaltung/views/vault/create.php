<?php
$title = 'Geheimnis erstellen';
ob_start();
?>
<div style="max-width: 800px; margin: 40px auto; padding: 20px;">
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h1>Neue Zugangsdaten</h1>
        <p style="color: #64748b; font-size: 14px; margin-bottom: 20px;">
            Kunde: <strong><?php echo htmlspecialchars((string)($customer['name'] ?? ''), ENT_QUOTES); ?></strong>
        </p>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $err): ?>
                    <div><?php echo htmlspecialchars($err, ENT_QUOTES); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="/admin/customers/<?php echo (int)($customerId ?? 0); ?>/vault">
            <?php echo Csrf::field(); ?>
            
            <div class="form-group">
                <label for="label">Label (z.B. "IONOS Webspace")</label>
                <input type="text" id="label" name="label" maxlength="100" value="<?php echo htmlspecialchars((string)($old['label'] ?? ''), ENT_QUOTES); ?>">
            </div>
            
            <div class="form-group">
                <label for="host">Host</label>
                <input type="text" id="host" name="host" maxlength="255" value="<?php echo htmlspecialchars((string)($old['host'] ?? ''), ENT_QUOTES); ?>" placeholder="z.B. ftp.example.com">
            </div>
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" maxlength="255" value="<?php echo htmlspecialchars((string)($old['username'] ?? ''), ENT_QUOTES); ?>">
            </div>
            
            <div class="form-group">
                <label for="secret">Secret / Passwort *</label>
                <input type="password" id="secret" name="secret" required autofocus>
            </div>
            
            <button type="submit">Speichern</button>
        </form>
        
        <div style="margin-top: 15px;">
            <a href="/admin/customers/<?php echo (int)($customerId ?? 0); ?>/vault" style="color: #64748b; text-decoration: none;">← Zurück</a>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
