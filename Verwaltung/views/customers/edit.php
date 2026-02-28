<?php
declare(strict_types=1);

$title = 'Kunde bearbeiten';
ob_start();
?>
<div style="max-width: 800px; margin: 40px auto; padding: 20px;">
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <h1 style="margin: 0 0 6px 0; font-size: 28px;">Kunde bearbeiten</h1>
                <a href="/admin/customers" style="color: #64748b; text-decoration: none; font-size: 14px;">← Zurück zu Kunden</a>
            </div>
            <form method="POST" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/toggle">
                <?php echo Csrf::field(); ?>
                <button type="submit" style="padding: 8px 16px; background: <?php echo ((int)($customer['is_active'] ?? 0) === 1) ? '#dc2626' : '#16a34a'; ?>; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
                    <?php echo ((int)($customer['is_active'] ?? 0) === 1) ? 'Deaktivieren' : 'Aktivieren'; ?>
                </button>
            </form>
        </div>

        <?php if (!empty($errors)): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
                <?php foreach ($errors as $err): ?>
                    <div><?php echo htmlspecialchars((string)$err, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>">
            <?php echo Csrf::field(); ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px;">Name *</label>
                    <input type="text" name="name"
                           value="<?php echo htmlspecialchars((string)($old['name'] ?? $customer['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                           required
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px;">Domain</label>
                    <input type="text" name="domain"
                           value="<?php echo htmlspecialchars((string)($old['domain'] ?? $customer['domain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="kunde.de"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px;">E-Mail</label>
                    <input type="email" name="email"
                           value="<?php echo htmlspecialchars((string)($old['email'] ?? $customer['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="kunde@beispiel.de"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px;">Abo-Status</label>
                    <select name="abo_status"
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
                        <?php
                        $currentAbo = (string)($old['abo_status'] ?? $customer['abo_status'] ?? 'active');
                        foreach (['active' => 'Aktiv', 'cancelled' => 'Gekündigt', 'suspended' => 'Gesperrt'] as $val => $lbl):
                        ?>
                            <option value="<?php echo $val; ?>" <?php echo $val === $currentAbo ? 'selected' : ''; ?>>
                                <?php echo $lbl; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px;">Notizen</label>
                <textarea name="notes" rows="3"
                          placeholder="Interne Notizen zum Kunden..."
                          style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box; resize: vertical;"><?php echo htmlspecialchars((string)($old['notes'] ?? $customer['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <button type="submit"
                    style="width: 100%; padding: 12px; background: #2563eb; color: white; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; font-weight: 500;">
                Speichern
            </button>
        </form>

    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';