<?php
$title = 'Webhooks: ' . htmlspecialchars((string)($customer['name'] ?? ''), ENT_QUOTES);
ob_start();
?>
<div style="max-width: 900px; margin: 40px auto; padding: 20px;">
    <div style="background: white; padding: 24px 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
                <h1 style="margin: 0 0 4px 0; font-size: 22px;">Webhook-Tokens</h1>
                <p style="margin: 0; color: #64748b; font-size: 14px;">
                    <?php echo htmlspecialchars((string)($customer['name'] ?? ''), ENT_QUOTES); ?>
                </p>
            </div>
            <a href="/admin/customers/<?php echo (int)$customer['id']; ?>"
               style="padding: 8px 14px; background: #64748b; color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">
                ← Kunde
            </a>
        </div>
    </div>

    <?php if ($newToken): ?>
        <div style="background: #fefce8; border: 2px solid #eab308; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h3 style="margin: 0 0 8px 0; color: #92400e;">⚠️ Token einmalig anzeigen - jetzt kopieren!</h3>
            <p style="margin: 0 0 12px 0; color: #78350f; font-size: 14px;">
                Dieser Token wird <strong>nur jetzt einmal</strong> angezeigt und ist danach nicht mehr lesbar.
            </p>
            <code style="display: block; background: #1e293b; color: #4ade80; padding: 14px; border-radius: 4px; font-size: 14px; word-break: break-all; letter-spacing: 0.5px;">
                <?php echo htmlspecialchars((string)$newToken, ENT_QUOTES); ?>
            </code>
            <p style="margin: 10px 0 0 0; color: #64748b; font-size: 13px;">
                Verwendung: <code>curl -X POST https://verwaltung.digiwtal.de/webhook/deploy -H "X-Webhook-Token: &lt;token&gt;"</code>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($success && !$newToken): ?>
        <div style="background: #d1fae5; color: #065f46; padding: 12px; border-radius: 4px; margin-bottom: 16px;">
            <?php echo htmlspecialchars((string)$success, ENT_QUOTES); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 4px; margin-bottom: 16px;">
            <?php foreach ($errors as $e): ?>
                <div><?php echo htmlspecialchars((string)$e, ENT_QUOTES); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div style="background: white; padding: 24px 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px;">
        <h2 style="margin: 0 0 16px 0; font-size: 16px; font-weight: 600;">Neuen Webhook-Token erstellen</h2>
        <form method="POST" action="/admin/customers/<?php echo (int)$customer['id']; ?>/webhooks">
            <?php echo Csrf::field(); ?>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                <div>
                    <label style="display: block; margin-bottom: 4px; font-size: 13px; font-weight: 500;">Label</label>
                    <input type="text" name="label" placeholder="z.B. GitHub Actions" maxlength="100"
                           style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; width: 200px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 4px; font-size: 13px; font-weight: 500;">Deploy-Typ</label>
                    <select name="deploy_type" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <option value="cms">CMS</option>
                        <option value="frontend">Frontend</option>
                        <option value="full">Full (CMS + Frontend)</option>
                    </select>
                </div>
                <button type="submit" style="padding: 9px 18px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;">
                    Token generieren
                </button>
            </div>
        </form>
    </div>

    <div style="background: white; padding: 24px 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h2 style="margin: 0 0 16px 0; font-size: 16px; font-weight: 600;">Aktive Tokens (<?php echo count($tokens); ?>)</h2>
        <?php if (empty($tokens)): ?>
            <p style="color: #64748b;">Noch keine Webhook-Tokens.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0;">
                        <th style="text-align: left; padding: 8px 6px;">Label</th>
                        <th style="text-align: left; padding: 8px 6px;">Typ</th>
                        <th style="text-align: left; padding: 8px 6px;">Zuletzt genutzt</th>
                        <th style="text-align: left; padding: 8px 6px;">Erstellt</th>
                        <th style="text-align: left; padding: 8px 6px;">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tokens as $t): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 8px 6px; font-weight: 500;"><?php echo htmlspecialchars((string)($t['label'] ?? ''), ENT_QUOTES); ?></td>
                            <td style="padding: 8px 6px;">
                                <span style="font-size: 12px; padding: 2px 8px; border-radius: 10px; background: #dbeafe; color: #1d4ed8; font-weight: 600;">
                                    <?php echo htmlspecialchars(strtoupper((string)($t['deploy_type'] ?? '')), ENT_QUOTES); ?>
                                </span>
                            </td>
                            <td style="padding: 8px 6px; color: #64748b;"><?php echo htmlspecialchars((string)($t['last_used_at'] ?? '—'), ENT_QUOTES); ?></td>
                            <td style="padding: 8px 6px; color: #64748b;"><?php echo htmlspecialchars((string)($t['created_at'] ?? ''), ENT_QUOTES); ?></td>
                            <td style="padding: 8px 6px;">
                                <form method="POST" action="/admin/webhooks/<?php echo (int)$t['id']; ?>/delete"
                                      onsubmit="return confirm('Token wirklich löschen?')" style="display: inline;">
                                    <?php echo Csrf::field(); ?>
                                    <button type="submit" style="background: none; border: none; color: #dc2626; cursor: pointer; font-size: 13px;">Löschen</button>
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
require __DIR__ . '/../../layout.php';
