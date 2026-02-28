<?php
$title = 'Admin einladen';
ob_start();
?>
<div style="max-width: 600px; margin: 40px auto; padding: 20px;">
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h1 style="margin-bottom: 6px; font-size: 22px;">Admin-Benutzer einladen</h1>
        <p style="color: #64748b; font-size: 14px; margin-bottom: 24px;">
            Neuen Admin anlegen. Das Passwort muss direkt übermittelt werden - kein E-Mail-Versand.
        </p>

        <?php if (!empty($_SESSION['flash_errors'])): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 4px; margin-bottom: 16px;">
                <?php foreach ($_SESSION['flash_errors'] as $e): ?>
                    <div><?php echo htmlspecialchars((string)$e, ENT_QUOTES); ?></div>
                <?php endforeach; unset($_SESSION['flash_errors']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/admin/admin-users">
            <?php echo Csrf::field(); ?>
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px;">E-Mail *</label>
                <input type="email" name="email" required autofocus
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px;">Passwort * <span style="font-weight: 400; color: #64748b;">(min. 12 Zeichen)</span></label>
                <input type="password" name="password" required minlength="12"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
            </div>
            <div style="margin-bottom: 24px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px;">Rolle</label>
                <select name="role" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    <option value="operator">Operator - kann alles ausser Admin-Verwaltung</option>
                    <option value="superadmin">Superadmin - voller Zugriff</option>
                </select>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" style="padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; font-weight: 500;">
                    Admin anlegen
                </button>
                <a href="/admin/admin-users" style="padding: 10px 16px; background: #e2e8f0; color: #374151; text-decoration: none; border-radius: 4px; font-size: 14px;">
                    Abbrechen
                </a>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
