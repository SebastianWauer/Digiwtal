<?php
$title = '2FA Verification';
ob_start();
?>
<div class="container">
    <div class="card">
        <h1>Two-Factor Authentication</h1>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
        <?php endif; ?>
        <form method="POST" action="/admin/verify-2fa">
            <?php echo Csrf::field(); ?>
            <div class="form-group">
                <label for="code">6-Digit Code</label>
                <input type="text" id="code" name="code" pattern="[0-9]{6}" maxlength="6" required autofocus autocomplete="off">
            </div>
            <button type="submit">Verify</button>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
