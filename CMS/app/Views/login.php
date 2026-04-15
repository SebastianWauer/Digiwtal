<?php
/** @var ?array $flash */
$logoLight = function_exists('site_cms_logo_url') ? site_cms_logo_url('light') : null;
$logoDark = function_exists('site_cms_logo_url') ? site_cms_logo_url('dark') : null;
$logoUrl = $logoDark ?: $logoLight;

if ($logoUrl === null) {
    try {
        $stmt = db()->prepare("SELECT `value` FROM site_settings WHERE `key` = 'logo_media_id' LIMIT 1");
        $stmt->execute();
        $fallbackLogoId = (int)$stmt->fetchColumn();
        if ($fallbackLogoId > 0) {
            $logoUrl = '/media/file?id=' . $fallbackLogoId;
        }
    } catch (Throwable $e) {
        $logoUrl = null;
    }
}
?><!doctype html>
<html lang="de" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CMS &ndash; Login</title>
  <?php if ($fav = site_favicon_url()): ?>
    <link rel="icon" href="<?= h($fav) ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= h(admin_asset_css('admin-layout.css')) ?>">
  <link rel="stylesheet" href="<?= h(admin_asset_css('admin-components.css')) ?>">
  <link rel="stylesheet" href="<?= h(admin_asset_css('admin-login.css')) ?>">
</head>
<body class="login-auth">

<main class="login-shell">
  <div class="login-brand" aria-label="CMS">
    <div class="login-brand__logo">
      <?php if ($logoUrl): ?>
        <img src="<?= h($logoUrl) ?>" alt="CMS Logo">
      <?php else: ?>
        <span>CMS</span>
      <?php endif; ?>
    </div>
    <div class="login-brand__meta">
      <span>CMS</span>
      <strong>Adminbereich</strong>
    </div>
  </div>

  <section class="login-card" aria-labelledby="loginTitle">
    <h1 class="login-title" id="loginTitle">Admin Login</h1>
    <p class="login-copy">Melde dich mit deinem CMS-Zugang an.</p>

    <?php if (is_array($flash)): ?>
      <?php
        $flashType = (string)($flash['type'] ?? 'error');
        $flashType = in_array($flashType, ['ok', 'error'], true) ? $flashType : 'error';
      ?>
      <div class="login-alert login-alert--<?= h($flashType) ?>">
        <?= h((string)($flash['msg'] ?? '')) ?>
      </div>
    <?php endif; ?>

    <form method="post" action="/login" class="login-form">
      <?= admin_csrf_field() ?>

      <div class="login-field">
        <label for="username">Benutzername oder E-Mail</label>
        <input
          class="login-input"
          type="text"
          id="username"
          name="username"
          required
          autocomplete="username"
          autofocus
        >
      </div>

      <div class="login-field">
        <label for="password">Passwort</label>
        <input
          class="login-input"
          type="password"
          id="password"
          name="password"
          required
          autocomplete="current-password"
        >
      </div>

      <div class="login-actions">
        <button type="submit" class="btn login-submit">Login</button>
        <a href="/password-reset" class="btn btn--ghost login-reset">Passwort vergessen?</a>
      </div>
    </form>
  </section>
</main>

</body>
</html>
