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
$hasRunningDeployment = false;
$hasSuccessfulDeployment = false;
foreach ($deployments as $deployment) {
    if ((string)($deployment['status'] ?? '') === 'running') {
        $hasRunningDeployment = true;
    }
    if ((string)($deployment['status'] ?? '') === 'success') {
        $hasSuccessfulDeployment = true;
    }
}
ob_start();
?>
<div class="view-stack">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Deployments</h1>
                <p class="page-subtitle">Kunde: <strong><?php echo htmlspecialchars((string)($customer['name'] ?? ''), ENT_QUOTES); ?></strong></p>
            </div>
            <div class="page-actions">
                <a class="btn btn--secondary btn--sm" href="/admin/customers">Kunden</a>
            </div>
        </header>

        <?php if (isset($success)): ?>
            <div class="alert alert--success"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div>
        <?php endif; ?>
        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="alert alert--error">
                <?php foreach ($errors as $err): ?>
                    <div><?php echo htmlspecialchars($err, ENT_QUOTES); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="hint-card hint-card--info">
            <div><strong>Basis-Pfad:</strong> <code><?php echo htmlspecialchars($baseTarget, ENT_QUOTES); ?></code></div>
            <div><strong>Transfer-Protokoll:</strong> <code><?php echo htmlspecialchars((string)($access['protocol'] ?? 'ftp'), ENT_QUOTES); ?></code></div>
            <div><strong>CMS:</strong> lokaler Projektordner <code>/CMS</code> → <code><?php echo htmlspecialchars($cmsTarget, ENT_QUOTES); ?></code></div>
            <div><strong>Frontend:</strong> lokaler Ordnerpicker → <code><?php echo htmlspecialchars($frontendTarget, ENT_QUOTES); ?></code></div>
        </div>

        <form method="POST" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/deployments/test-connections" class="form-stack">
            <?php echo Csrf::field(); ?>
            <div class="submit-row">
                <button class="btn btn--secondary" type="submit">Server- und DB-Verbindung testen</button>
            </div>
            <div class="field__hint">Prüft getrennt den konfigurierten Serverzugang und die Kundendatenbank, ohne Dateien zu deployen.</div>
        </form>
    </section>

    <section class="surface">
        <h2 class="section-title">Lokaler SFTP-Agent</h2>
        <div class="hint-card hint-card--success">
            <div>Für echtes One-Click-SFTP startet auf deinem Rechner ein lokaler HTTPS-Agent auf <code>https://127.0.0.1:8765</code>. Die Verwaltung liefert nur das Deploy-Payload.</div>
            <code class="code-block">php Verwaltung/agent/generate_cert.php
php Verwaltung/agent/server.php</code>
            <div>Danach dieselbe Browser-Instanz einmal auf <a class="link-action link-action--success" href="https://127.0.0.1:8765/health" target="_blank" rel="noreferrer">https://127.0.0.1:8765/health</a> öffnen und dem lokalen Zertifikat vertrauen.</div>
            <div class="agent-status" id="localAgentStatus">Status: nicht geprüft</div>
        </div>
    </section>

    <section class="surface">
        <div class="hint-card hint-card--warning">
            <strong>Wichtiger Hinweis zum Serverpasswort:</strong> Für lokale Agent-Deployments wird das im Serverzugang hinterlegte Passwort kurz an deinen Browser und von dort an den lokalen Agenten übergeben, damit dein Rechner die SFTP-Verbindung aufbauen kann.
        </div>
    </section>

    <?php if (!$hasSuccessfulDeployment): ?>
        <section class="surface">
            <div class="hint-card hint-card--warning">
                <div><strong>Erstinstallation</strong></div>
                <div>Dieser Kunde hat noch keinen erfolgreichen Deploy. Die Erstinstallation deployed das CMS und führt danach die Provisionierung direkt aus.</div>
                <form method="POST" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/deployments/install" class="form-stack">
                    <?php echo Csrf::field(); ?>
                    <div class="submit-row">
                        <button class="btn btn--warning" type="submit" onclick="return confirm('Erstinstallation jetzt starten? Das deployed das CMS und führt die Provisionierung aus.');">Erstinstallation starten</button>
                    </div>
                    <div class="field__hint">Verwendet den lokalen Projektordner <code>/CMS</code> und startet anschließend automatisch die CMS-Provisionierung.</div>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <section class="surface">
        <header class="page-header page-header--section">
            <div class="page-header__main">
                <h2 class="section-title">Update-Deploys</h2>
                <p class="page-subtitle">Für bestehende Installationen. Es werden nur Dateien deployed, keine CMS-Provisionierung.</p>
            </div>
        </header>
        <div class="deploy-grid">
            <form method="POST" data-local-agent-form="1" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/deployments/agent-payload" class="deploy-card">
                <?php echo Csrf::field(); ?>
                <input type="hidden" name="type" value="cms">
                <h2 class="deploy-card__title">CMS deployen</h2>
                <p class="deploy-card__copy">Nutzt den lokalen Projektordner <code>/CMS</code> auf deinem Rechner und lädt per SFTP hoch.</p>
                <div class="submit-row">
                    <button class="btn btn--success" type="submit">CMS deployen</button>
                </div>
            </form>

            <form method="POST" data-local-agent-form="1" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/deployments/agent-payload" class="deploy-card">
                <?php echo Csrf::field(); ?>
                <input type="hidden" name="type" value="frontend">
                <h2 class="deploy-card__title">Frontend deployen</h2>
                <p class="deploy-card__copy">Wähle den lokalen Frontend-Ordner. Er wird im Browser komprimiert und vom lokalen Agenten per SFTP übertragen.</p>
                <input class="file-input" type="file" name="frontend_files[]" data-frontend-picker="1" webkitdirectory directory multiple required>
                <div class="submit-row">
                    <button class="btn btn--info" type="submit">Frontend deployen</button>
                </div>
            </form>

            <form method="POST" data-local-agent-form="1" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/deployments/agent-payload" class="deploy-card">
                <?php echo Csrf::field(); ?>
                <input type="hidden" name="type" value="combined">
                <h2 class="deploy-card__title">CMS + Frontend deployen</h2>
                <p class="deploy-card__copy">CMS kommt aus deinem lokalen Projektordner <code>/CMS</code>, Frontend aus dem gewählten Ordner.</p>
                <input class="file-input" type="file" name="frontend_files[]" data-frontend-picker="1" webkitdirectory directory multiple required>
                <div class="submit-row">
                    <button class="btn btn--warning" type="submit">Kombiniert deployen</button>
                </div>
            </form>
        </div>
    </section>

    <section class="surface">
        <div class="submit-row">
            <form method="POST" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/deployments/rollback" class="table-inline-form" onsubmit="return confirm('Wirklich auf den letzten Stand zurückrollen?');">
                <?php echo Csrf::field(); ?>
                <button class="btn btn--secondary" type="submit">Rollback</button>
            </form>
            <span class="field__hint">Stellt den letzten bekannten Stand wieder her.</span>
        </div>
    </section>

    <section class="surface">
        <h2 class="section-title">Deployment-Historie</h2>
        <?php if ($hasRunningDeployment): ?>
            <div class="hint-card hint-card--info">
                Ein Deployment läuft gerade. Diese Seite aktualisiert sich automatisch jede Sekunde, bis der Status auf <code>success</code> oder <code>failed</code> kippt.
            </div>
        <?php endif; ?>
        <?php if (empty($deployments)): ?>
            <p class="empty-state">Noch keine Deployments vorhanden.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr id="deployment-<?php echo (int)($d['id'] ?? 0); ?>">
                            <th>ID</th>
                            <th>Typ</th>
                            <th>Status</th>
                            <th>Version</th>
                            <th>Dauer</th>
                            <th>Gestartet</th>
                            <th>Ausgelöst durch</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($deployments as $d): ?>
                        <?php
                        $status = (string)($d['status'] ?? 'pending');
                        $statusClass = match ($status) {
                            'success' => 'healthy',
                            'running' => 'degraded',
                            'failed' => 'down',
                            'rolled_back' => 'timeout',
                            default => 'unknown',
                        };
                        $duration = '—';
                        if (($d['started_at'] ?? null) !== null && ($d['finished_at'] ?? null) !== null) {
                            $start = strtotime((string)$d['started_at']);
                            $finish = strtotime((string)$d['finished_at']);
                            if ($start !== false && $finish !== false) {
                                $duration = ($finish - $start) . 's';
                            }
                        }
                        $version = '—';
                        if ((string)($d['version_from'] ?? '') !== '' && (string)($d['version_to'] ?? '') !== '') {
                            $version = htmlspecialchars((string)$d['version_from'], ENT_QUOTES) . ' → ' . htmlspecialchars((string)$d['version_to'], ENT_QUOTES);
                        }
                        $createdStr = '—';
                        if (($d['created_at'] ?? null) !== null) {
                            $createdTime = strtotime((string)$d['created_at']);
                            if ($createdTime !== false) {
                                $diff = time() - $createdTime;
                                $createdStr = $diff < 3600 ? ('vor ' . ceil($diff / 60) . ' Min') : date('d.m.Y H:i', $createdTime);
                            }
                        }
                        $rollbackBackupPath = trim((string)($d['latest_backup_path'] ?? ''));
                        ?>
                        <tr>
                            <td>
                                <?php if (!empty($d['log'])): ?>
                                    <details class="table-log">
                                        <summary>#<?php echo (int)($d['id'] ?? 0); ?></summary>
                                        <pre class="code-block"><?php echo htmlspecialchars((string)($d['log'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></pre>
                                    </details>
                                <?php else: ?>
                                    #<?php echo (int)($d['id'] ?? 0); ?>
                                <?php endif; ?>
                            </td>
                            <td class="mono"><?php echo htmlspecialchars((string)($d['type'] ?? 'cms'), ENT_QUOTES); ?></td>
                            <td><span class="status-pill status-pill--<?php echo $statusClass; ?>"><?php echo htmlspecialchars($status, ENT_QUOTES); ?></span></td>
                            <td class="mono"><?php echo $version; ?></td>
                            <td class="mono"><?php echo $duration; ?></td>
                            <td class="text-muted"><?php echo $createdStr; ?></td>
                            <td><?php echo htmlspecialchars((string)($d['triggered_by'] ?? 'manual'), ENT_QUOTES); ?></td>
                        </tr>
                        <?php if ($status === 'rolled_back'): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="hint-card hint-card--warning">
                                        <strong>Automatischer Rollback nicht möglich – manueller Eingriff erforderlich.</strong>
                                        <?php if ($rollbackBackupPath !== ''): ?>
                                            <div>Backup-Pfad: <code><?php echo htmlspecialchars($rollbackBackupPath, ENT_QUOTES); ?></code></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
$content = ob_get_clean();
$extraScripts = '<script src="/assets/deploy-frontend-upload.js"></script>';
if ($hasRunningDeployment) {
    $extraScripts .= <<<HTML
<script>
(function () {
    window.setTimeout(function () {
        window.location.reload();
    }, 1000);
})();
</script>
HTML;
}
require __DIR__ . '/../layout.php';
