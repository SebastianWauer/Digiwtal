<?php
$title = 'Serverzugang';
$storedServerPath = (string)($access['server_path'] ?? '');
$storedHtmlPath = (string)($access['html_path'] ?? '');
$derivedBasePath = '/';
if ($storedServerPath !== '' && str_ends_with($storedServerPath, '/CMS')) {
    $derivedBasePath = substr($storedServerPath, 0, -4);
} elseif ($storedHtmlPath !== '' && str_ends_with($storedHtmlPath, '/Frontend')) {
    $derivedBasePath = substr($storedHtmlPath, 0, -9);
}
if ($derivedBasePath === '') {
    $derivedBasePath = '/';
}
ob_start();
?>
<div style="max-width: 800px; margin: 40px auto; padding: 20px;">
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="margin-bottom: 20px;">
            <h1 style="margin: 0 0 10px 0; font-size: 28px;">Serverzugang</h1>
            <p style="margin: 0; color: #64748b; font-size: 14px;">
                Kunde: <strong><?php echo htmlspecialchars((string)($customer['name'] ?? ''), ENT_QUOTES); ?></strong>
            </p>
            <a href="/admin/customers" style="color: #64748b; text-decoration: none; font-size: 14px;">← Zurück zu Kunden</a>
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
        
        <div style="background: #eff6ff; color: #1e40af; padding: 12px; border-radius: 4px; margin-bottom: 20px; font-size: 13px;">
            ℹ️ Tokens und Passwörter werden AES-256-GCM verschlüsselt gespeichert. Bestehende Werte bleiben erhalten, wenn das Feld leer gelassen wird.
        </div>
        
        <form method="POST" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/access">
            <?php echo Csrf::field(); ?>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Label</label>
                <input type="text" name="label" maxlength="100" value="<?php echo htmlspecialchars((string)($old['label'] ?? $access['label'] ?? ''), ENT_QUOTES); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">SFTP-Host *</label>
                <input type="text" name="host" placeholder="z.B. access-123456.webspace-host.com" value="<?php echo htmlspecialchars((string)($old['host'] ?? $access['host'] ?? ''), ENT_QUOTES); ?>" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                <div style="margin-top: 6px; color: #64748b; font-size: 12px;">
                    Nur für Datei-Upload per SFTP/FTP. Nicht die öffentliche CMS- oder Frontend-Domain eintragen.
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Port</label>
                    <input type="number" name="port" min="1" max="65535" value="<?php echo (int)($old['port'] ?? $access['port'] ?? 21); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Protokoll</label>
                    <select name="protocol" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <?php
                        $currentProtocol = (string)($old['protocol'] ?? $access['protocol'] ?? 'ftp');
                        foreach (['ftp', 'sftp', 'ssh'] as $proto): ?>
                            <option value="<?php echo $proto; ?>" <?php echo $proto === $currentProtocol ? 'selected' : ''; ?>><?php echo strtoupper($proto); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div style="margin-top: 6px; color: #64748b; font-size: 12px;">
                        Standard für den aktuellen Deploy-Flow ist FTP. SFTP/SSH erfordert aktiviertes <code>ssh2</code> im Web-PHP.
                    </div>
                </div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Username</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars((string)($old['username'] ?? $access['username'] ?? ''), ENT_QUOTES); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Basis-Pfad</label>
                <input type="text" name="base_path" value="<?php echo htmlspecialchars((string)($old['basePath'] ?? $derivedBasePath), ENT_QUOTES); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                <div style="margin-top: 6px; color: #64748b; font-size: 12px;">
                    Daraus werden automatisch <code><?php echo htmlspecialchars((string)(($old['basePath'] ?? $derivedBasePath) === '/' ? '/CMS' : rtrim((string)($old['basePath'] ?? $derivedBasePath), '/') . '/CMS'), ENT_QUOTES); ?></code> und
                    <code><?php echo htmlspecialchars((string)(($old['basePath'] ?? $derivedBasePath) === '/' ? '/Frontend' : rtrim((string)($old['basePath'] ?? $derivedBasePath), '/') . '/Frontend'), ENT_QUOTES); ?></code>.
                </div>
            </div>
            
            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;">
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Health Token</label>
                <input type="password" name="health_token" placeholder="Leer lassen = nicht ändern" autocomplete="off" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Health CMS URL</label>
                <input type="url" name="health_cms_url" placeholder="https://cms.example.com" value="<?php echo htmlspecialchars((string)($old['healthCmsUrl'] ?? $access['health_cms_url'] ?? ''), ENT_QUOTES); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                <div style="margin-top: 6px; color: #64748b; font-size: 12px;">
                    Öffentliche CMS-URL. Der Health-Check ruft dort <code>/api/health?token=...</code> auf.
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Health Frontend URL</label>
                <input type="url" name="health_frontend_url" placeholder="https://www.example.com" value="<?php echo htmlspecialchars((string)($old['healthFrontendUrl'] ?? $access['health_frontend_url'] ?? ''), ENT_QUOTES); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                <div style="margin-top: 6px; color: #64748b; font-size: 12px;">
                    Öffentliche Frontend-URL. Wird zusätzlich per HTTP geprüft.
                </div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Deploy Token</label>
                <input type="password" name="deploy_token" placeholder="Leer lassen = nicht ändern" autocomplete="off" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Server-Passwort</label>
                <input type="password" name="password" placeholder="Leer lassen = nicht ändern" autocomplete="off" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>

            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;">

            <h2 style="margin: 0 0 14px 0; font-size: 18px;">CMS-Provisioning</h2>
            <p style="margin: 0 0 16px 0; color: #64748b; font-size: 13px;">
                Diese Daten werden für den Erstdeploy verwendet: `.env` schreiben und das CMS direkt installierbar machen.
            </p>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">DB-Host</label>
                    <input type="text" name="db_host" placeholder="z.B. localhost" value="<?php echo htmlspecialchars((string)($old['dbHost'] ?? $access['db_host'] ?? ''), ENT_QUOTES); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">DB-Port</label>
                    <input type="number" name="db_port" min="1" max="65535" value="<?php echo (int)($old['dbPort'] ?? $access['db_port'] ?? 3306); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">DB-Name</label>
                    <input type="text" name="db_name" value="<?php echo htmlspecialchars((string)($old['dbName'] ?? $access['db_name'] ?? ''), ENT_QUOTES); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">DB-Benutzer</label>
                    <input type="text" name="db_user" value="<?php echo htmlspecialchars((string)($old['dbUser'] ?? $access['db_user'] ?? ''), ENT_QUOTES); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">DB-Passwort</label>
                <input type="password" name="db_password" placeholder="Leer lassen = nicht ändern" autocomplete="off" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Initiale CMS-Admin-E-Mail</label>
                <input type="email" name="cms_admin_email" value="<?php echo htmlspecialchars((string)($old['cmsAdminEmail'] ?? $access['cms_admin_email'] ?? ''), ENT_QUOTES); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Initiales CMS-Admin-Passwort</label>
                <input type="password" name="cms_admin_password" placeholder="Leer lassen = nicht ändern" autocomplete="off" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Site-Name</label>
                <input type="text" name="site_name" value="<?php echo htmlspecialchars((string)($old['siteName'] ?? $access['site_name'] ?? ($customer['name'] ?? '')), ENT_QUOTES); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Canonical-Base-URL</label>
                <input type="url" name="canonical_base" placeholder="https://example.com" value="<?php echo htmlspecialchars((string)($old['canonicalBase'] ?? $access['canonical_base'] ?? ''), ENT_QUOTES); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>
            
            <button type="submit" style="width: 100%; padding: 12px; background: #2563eb; color: white; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; font-weight: 500;">Speichern</button>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
