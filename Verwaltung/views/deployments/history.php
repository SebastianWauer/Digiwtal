<?php
$title = 'Deployments';
$cmsTarget = isset($access['server_path']) ? (string)$access['server_path'] : '/CMS';
$frontendTarget = isset($access['html_path']) ? (string)$access['html_path'] : '/Frontend';
$baseTarget = '/';
if (str_ends_with($cmsTarget, '/CMS')) {
    $baseTarget = substr($cmsTarget, 0, -4);
} elseif (str_ends_with($frontendTarget, '/Frontend')) {
    $baseTarget = substr($frontendTarget, 0, -9);
}
if ($baseTarget === '') {
    $baseTarget = '/';
}
ob_start();
?>
<div style="max-width: 1000px; margin: 40px auto; padding: 20px;">
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="margin-bottom: 20px;">
            <h1 style="margin: 0 0 10px 0; font-size: 28px;">Deployments</h1>
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

        <div style="background: #f8fafc; color: #334155; padding: 14px 16px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 20px; font-size: 13px; line-height: 1.5;">
            <div style="font-weight: 600; margin-bottom: 6px;">Deploy-Zielstruktur</div>
            <div><strong>Basis-Pfad:</strong> <code><?php echo htmlspecialchars($baseTarget, ENT_QUOTES); ?></code></div>
            <div><strong>Transfer-Protokoll:</strong> <code><?php echo htmlspecialchars((string)($access['protocol'] ?? 'ftp'), ENT_QUOTES); ?></code></div>
            <div><strong>CMS:</strong> lokaler Projektordner <code>/CMS</code> → <code><?php echo htmlspecialchars($cmsTarget, ENT_QUOTES); ?></code></div>
            <div><strong>Frontend:</strong> lokaler Ordnerpicker → <code><?php echo htmlspecialchars($frontendTarget, ENT_QUOTES); ?></code></div>
            <div style="margin-top: 6px;">
                <strong>Deploy-Ziel:</strong> `CMS` geht in den konfigurierten CMS-Zielpfad (`server_path`), `Frontend` in den konfigurierten Frontend-Zielpfad (`html_path`), `CMS + Frontend` deployt beides getrennt.
            </div>
        </div>

        <form method="POST" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/deployments/test-connections"
              style="margin-bottom: 20px;">
            <?php echo Csrf::field(); ?>
            <button type="submit" style="padding: 10px 20px; background: #475569; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                Server- und DB-Verbindung testen
            </button>
            <p style="margin: 6px 0 0 0; color: #64748b; font-size: 12px;">
                Prüft getrennt den konfigurierten Serverzugang und die Kundendatenbank, ohne Dateien zu deployen.
            </p>
        </form>

        <div style="background: #ecfeff; color: #155e75; padding: 16px; border: 1px solid #a5f3fc; border-radius: 6px; margin-bottom: 20px; font-size: 13px; line-height: 1.6;">
            <div style="font-weight: 600; margin-bottom: 6px;">Lokaler SFTP-Agent</div>
            <div>Für echtes One-Click-SFTP startet auf deinem Rechner ein lokaler HTTPS-Agent auf <code>https://127.0.0.1:8765</code>. Die Verwaltung liefert nur das Deploy-Payload.</div>
            <div style="margin-top: 6px;">Start lokal im Projektverzeichnis:</div>
            <code style="display: block; margin-top: 6px; padding: 10px; background: #082f49; color: #e0f2fe; border-radius: 4px;">php Verwaltung/agent/generate_cert.php
php Verwaltung/agent/server.php</code>
            <div style="margin-top: 8px; color: #0f766e;">
                Danach dieselbe Browser-Instanz einmal auf <a href="https://127.0.0.1:8765/health" target="_blank" rel="noreferrer" style="color: #0f766e; font-weight: 600;">https://127.0.0.1:8765/health</a> öffnen und dem lokalen Zertifikat vertrauen.
            </div>
            <div id="localAgentStatus" style="margin-top: 8px; color: #0f172a;">Status: nicht geprüft</div>
        </div>

        <div style="display: grid; gap: 16px; margin-bottom: 20px;">
            <form method="POST" data-local-agent-form="1" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/deployments/agent-payload"
                  style="background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 16px;">
                <?php echo Csrf::field(); ?>
                <input type="hidden" name="type" value="cms">
                <div style="font-weight: 600; margin-bottom: 6px;">CMS deployen</div>
                <p style="margin: 0 0 12px 0; color: #64748b; font-size: 12px;">Nutzt den lokalen Projektordner <code>/CMS</code> auf deinem Rechner und lädt per SFTP hoch.</p>
                <button type="submit" style="padding: 10px 20px; background: #0f766e; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    CMS deployen
                </button>
            </form>

            <form method="POST" data-local-agent-form="1" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/deployments/agent-payload"
                  style="background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 16px;">
                <?php echo Csrf::field(); ?>
                <input type="hidden" name="type" value="frontend">
                <div style="font-weight: 600; margin-bottom: 6px;">Frontend deployen</div>
                <p style="margin: 0 0 12px 0; color: #64748b; font-size: 12px;">Wähle den lokalen Frontend-Ordner. Er wird im Browser komprimiert und vom lokalen Agenten per SFTP übertragen.</p>
                <input type="file" name="frontend_files[]" data-frontend-picker="1" webkitdirectory directory multiple required style="margin-bottom: 12px;">
                <button type="submit" style="padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    Frontend deployen
                </button>
            </form>

            <form method="POST" data-local-agent-form="1" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/deployments/agent-payload"
                  style="background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 16px;">
                <?php echo Csrf::field(); ?>
                <input type="hidden" name="type" value="combined">
                <div style="font-weight: 600; margin-bottom: 6px;">CMS + Frontend deployen</div>
                <p style="margin: 0 0 12px 0; color: #64748b; font-size: 12px;">CMS kommt aus deinem lokalen Projektordner <code>/CMS</code>, Frontend aus dem gewählten Ordner.</p>
                <input type="file" name="frontend_files[]" data-frontend-picker="1" webkitdirectory directory multiple required style="margin-bottom: 12px;">
                <button type="submit" style="padding: 10px 20px; background: #7c3aed; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                    CMS + Frontend deployen
                </button>
            </form>
        </div>
        
        <form method="POST" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/deployments/rollback"
              onsubmit="return confirm('Wirklich auf den letzten Stand zurückrollen?');">
            <?php echo Csrf::field(); ?>
            <button type="submit" style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                Rollback
            </button>
        </form>
        <p style="margin: 6px 0 0 0; color: #64748b; font-size: 12px;">Stellt letzten bekannten Stand wieder her</p>
        
        <?php if (empty($deployments)): ?>
            <p style="color: #64748b;">Noch keine Deployments vorhanden.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0;">
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">ID</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Typ</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Status</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Version</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Dauer</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Gestartet</th>
                        <th style="text-align: left; padding: 12px 8px; font-weight: 600;">Ausgelöst durch</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deployments as $d): ?>
                        <?php
                        $status = (string)($d['status'] ?? 'pending');
                        $statusColor = match($status) {
                            'pending' => '#94a3b8',
                            'running' => '#3b82f6',
                            'success' => '#22c55e',
                            'failed' => '#ef4444',
                            'rolled_back' => '#f97316',
                            default => '#94a3b8'
                        };
                        
                        $duration = '—';
                        $startedAt = $d['started_at'] ?? null;
                        $finishedAt = $d['finished_at'] ?? null;
                        if ($startedAt !== null && $finishedAt !== null) {
                            $start = strtotime($startedAt);
                            $finish = strtotime($finishedAt);
                            if ($start !== false && $finish !== false) {
                                $secs = $finish - $start;
                                $duration = $secs . 's';
                            }
                        }
                        
                        $version = '—';
                        $versionFrom = (string)($d['version_from'] ?? '');
                        $versionTo = (string)($d['version_to'] ?? '');
                        if ($versionFrom !== '' && $versionTo !== '') {
                            $version = htmlspecialchars($versionFrom, ENT_QUOTES) . ' → ' . htmlspecialchars($versionTo, ENT_QUOTES);
                        }
                        
                        $createdAt = $d['created_at'] ?? null;
                        $createdStr = '—';
                        if ($createdAt !== null) {
                            $createdTime = strtotime($createdAt);
                            if ($createdTime !== false) {
                                $diff = time() - $createdTime;
                                if ($diff < 3600) {
                                    $createdStr = 'vor ' . ceil($diff / 60) . ' Min';
                                } else {
                                    $createdStr = date('d.m.Y H:i', $createdTime);
                                }
                            }
                        }
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 12px 8px; font-weight: 500;">
                                <?php if (!empty($d['log'])): ?>
                                    <details>
                                        <summary style="cursor: pointer; color: #2563eb;">#<?php echo (int)($d['id'] ?? 0); ?></summary>
                                        <pre style="background: #1e293b; color: #e2e8f0; padding: 12px; border-radius: 4px; font-size: 11px; white-space: pre-wrap; margin-top: 8px; max-height: 300px; overflow-y: auto;"><?php echo htmlspecialchars((string)($d['log'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></pre>
                                    </details>
                                <?php else: ?>
                                    #<?php echo (int)($d['id'] ?? 0); ?>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 8px; font-size: 12px;">
                                <?php echo htmlspecialchars((string)($d['type'] ?? 'cms'), ENT_QUOTES); ?>
                            </td>
                            <td style="padding: 12px 8px;">
                                <span style="padding: 4px 8px; background: <?php echo $statusColor; ?>20; color: <?php echo $statusColor; ?>; border-radius: 4px; font-size: 12px; font-weight: 500;">
                                    <?php echo htmlspecialchars($status, ENT_QUOTES); ?>
                                </span>
                            </td>
                            <td style="padding: 12px 8px; font-size: 12px;">
                                <?php echo $version; ?>
                            </td>
                            <td style="padding: 12px 8px; font-size: 12px;">
                                <?php echo $duration; ?>
                            </td>
                            <td style="padding: 12px 8px; color: #64748b; font-size: 12px;">
                                <?php echo $createdStr; ?>
                            </td>
                            <td style="padding: 12px 8px; font-size: 12px;">
                                <?php echo htmlspecialchars((string)($d['triggered_by'] ?? 'manual'), ENT_QUOTES); ?>
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
$extraScripts = '<script src="/assets/deploy-frontend-upload.js"></script>';
require __DIR__ . '/../layout.php';
