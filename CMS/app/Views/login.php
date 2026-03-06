<?php
/** @var ?array $flash */
?><!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CMS – Login</title>
  <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>

<main class="container">
  <header class="header">
    <h1 class="title">Admin Login</h1>
    <p class="subtitle">Bitte Benutzername und Passwort eingeben.</p>
  </header>

  <?php if (is_array($flash)): ?>
    <div class="flash flash--<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
      <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <form method="post" action="/login">
    <?= admin_csrf_field() ?>
    <ul class="list">
      <li class="row">
        <div class="content">
          <div class="mainline">
            <span class="label">Zugang</span>
            <span class="url">CMS Admin</span>
          </div>

          <div class="controls">
            <label class="chk">
              <span>Benutzer</span>
              <input type="text" name="username" required autocomplete="username">
            </label>

            <label class="chk">
              <span>Passwort</span>
              <input type="password" name="password" required autocomplete="current-password">
            </label>
          </div>

          <div class="actions">
            <button type="submit" class="btn">Login</button>
            <a href="/password-reset" class="btn btn--ghost">Passwort vergessen?</a>
          </div>

          <p class="hint">
            Hinweis: Beim ersten Start kann das System automatisch einen initialen Admin aus <code>config/admin_seed.php</code> anlegen.
          </p>
        </div>
      </li>
    </ul>
  </form>
</main>

</body>
</html>
