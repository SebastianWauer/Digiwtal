<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="DIGIWTAL">
    <link rel="manifest" href="/manifest.json">
    <title><?php echo htmlspecialchars($title ?? 'Admin', ENT_QUOTES); ?> – DIGIWTAL</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f1f5f9; }

        /* Login/2FA Container (schmal) */
        .container { max-width: 400px; margin: 100px auto; padding: 20px; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { margin-bottom: 20px; font-size: 24px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; }
        input[type="email"], input[type="password"], input[type="text"] {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;
        }
        button { width: 100%; padding: 12px; background: #2563eb; color: white; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        .error { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; }

        /* Mobile Top-Bar (nur auf kleinen Screens) */
        .mobile-topbar {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 56px;
            background: #1e293b;
            color: white;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .mobile-topbar .brand { font-weight: 700; font-size: 18px; letter-spacing: -0.5px; }
        .mobile-topbar .hamburger {
            background: none; border: none; color: white; width: 40px; height: 40px;
            cursor: pointer; font-size: 22px; display: flex; align-items: center; justify-content: center;
        }

        /* Mobile Nav-Drawer */
        .mobile-nav {
            display: none;
            position: fixed;
            top: 56px; left: 0; right: 0; bottom: 0;
            background: #1e293b;
            z-index: 999;
            flex-direction: column;
            padding: 16px;
            gap: 4px;
            overflow-y: auto;
        }
        .mobile-nav.open { display: flex; }
        .mobile-nav a {
            color: #cbd5e1; text-decoration: none; padding: 14px 16px;
            border-radius: 6px; font-size: 16px; font-weight: 500;
        }
        .mobile-nav a:hover, .mobile-nav a.active { background: #334155; color: white; }
        .mobile-nav .nav-divider { border-top: 1px solid #334155; margin: 8px 0; }
        .mobile-nav .nav-logout { color: #f87171 !important; }

        /* Body-Padding auf Mobile (wegen fixem Header) */
        @media (max-width: 768px) {
            .mobile-topbar { display: flex; }
            body { padding-top: 56px; }

            /* Inline-Style Wrapper responsiv machen */
            div[style*="max-width: 1200px"],
            div[style*="max-width: 1000px"],
            div[style*="max-width: 800px"] {
                margin: 16px !important;
                padding: 12px !important;
            }
            div[style*="padding: 30px"] {
                padding: 16px !important;
            }

            /* Tabellen scrollbar machen */
            table { display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; }

            /* Grid-Layouts stacken */
            div[style*="grid-template-columns"] {
                display: block !important;
            }
            div[style*="grid-template-columns"] > div {
                margin-bottom: 12px;
            }

            /* Flex-Zeilen in Spalten umwandeln */
            div[style*="display: flex"][style*="justify-content: space-between"] {
                flex-direction: column !important;
                gap: 12px !important;
            }
        }

        @media (max-width: 480px) {
            h1[style*="font-size: 28px"] { font-size: 22px !important; }
            th, td { padding: 8px 6px !important; font-size: 12px !important; }
        }
    </style>
</head>
<body>

    <!-- Mobile Top-Bar (nur auf kleinen Screens sichtbar) -->
    <div class="mobile-topbar">
        <span class="brand">DIGIWTAL</span>
        <button class="hamburger" id="hamburgerBtn" aria-label="Navigation öffnen">☰</button>
    </div>

    <!-- Mobile Nav-Drawer -->
    <nav class="mobile-nav" id="mobileNav">
        <a href="/admin/dashboard">📊 Dashboard</a>
        <a href="/admin/customers">👥 Kunden</a>
        <a href="/admin/modules">🧩 Module</a>
        <?php if (($_SESSION['admin_role'] ?? '') === 'superadmin'): ?>
            <a href="/admin/admin-users">👤 Admins</a>
        <?php endif; ?>
        <a href="/admin/audit">📋 Audit-Log</a>
        <div class="nav-divider"></div>
        <button id="pushToggleBtn" onclick="togglePushNotifications()"
            style="background:none;border:none;color:#cbd5e1;padding:14px 16px;border-radius:6px;font-size:16px;font-weight:500;text-align:left;width:100%;cursor:pointer;">
            🔔 Push aktivieren
        </button>
        <div class="nav-divider"></div>
        <a href="/admin/logout" class="nav-logout">← Logout</a>
    </nav>

    <?php echo $content; ?>

    <script>
    (function() {
        var btn = document.getElementById('hamburgerBtn');
        var nav = document.getElementById('mobileNav');
        if (!btn || !nav) return;
        btn.addEventListener('click', function() {
            nav.classList.toggle('open');
            btn.textContent = nav.classList.contains('open') ? '✕' : '☰';
        });
        // Schließen bei Link-Klick
        nav.querySelectorAll('a').forEach(function(a) {
            a.addEventListener('click', function() {
                nav.classList.remove('open');
                btn.textContent = '☰';
            });
        });

        // Service Worker registrieren
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js', { scope: '/' })
                    .catch(function(err) {
                        // Schlägt fehl wenn nicht HTTPS – ignorieren
                        console.log('SW registration failed:', err);
                    });
            });
        }

        // Push Notifications
        var pushBtn = document.getElementById('pushToggleBtn');

        function updatePushButton(subscribed) {
            if (!pushBtn) return;
            pushBtn.textContent = subscribed ? '🔕 Push deaktivieren' : '🔔 Push aktivieren';
            pushBtn.style.color = subscribed ? '#4ade80' : '#cbd5e1';
        }

        function togglePushNotifications() {
            if (!('PushManager' in window)) {
                alert('Push-Notifications werden in diesem Browser nicht unterstützt.');
                return;
            }
            if (!navigator.serviceWorker) return;

            navigator.serviceWorker.ready.then(function(reg) {
                reg.pushManager.getSubscription().then(function(sub) {
                    if (sub) {
                        sub.unsubscribe().then(function() {
                            fetch('/admin/push/unsubscribe', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ endpoint: sub.endpoint })
                            });
                            updatePushButton(false);
                        });
                    } else {
                        fetch('/admin/push/vapid-public')
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                if (!data.publicKey) return;
                                var key = Uint8Array.from(atob(data.publicKey.replace(/-/g,'+').replace(/_/g,'/')), function(c) { return c.charCodeAt(0); });
                                return reg.pushManager.subscribe({
                                    userVisibleOnly: true,
                                    applicationServerKey: key
                                });
                            })
                            .then(function(sub) {
                                if (!sub) return;
                                return fetch('/admin/push/subscribe', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify(sub)
                                });
                            })
                            .then(function() { updatePushButton(true); })
                            .catch(function(err) { console.log('Push subscribe failed:', err); });
                    }
                });
            });
        }

        // Initialer Status beim Laden
        if ('PushManager' in window && navigator.serviceWorker) {
            navigator.serviceWorker.ready.then(function(reg) {
                reg.pushManager.getSubscription().then(function(sub) {
                    updatePushButton(!!sub);
                });
            });
        }
    })();
    </script>
    <?php if (!empty($extraScripts ?? '')): ?>
        <?php echo $extraScripts; ?>
    <?php endif; ?>
</body>
</html>
