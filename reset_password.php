<?php
// Choose a new password with the link from the reset email (FR-26).
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/password_resets.php';
require_once __DIR__ . '/includes/login_guard.php';

$token  = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$reset  = find_valid_reset($pdo, $token);
$errors = [];

if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif (!hash_equals($password, $confirm)) {
        $errors[] = 'The two passwords are not the same.';
    }

    if (!$errors) {
        reset_password($pdo, $reset, $password);
        clear_failed_logins($pdo, $reset['email']);   // a new password ends any login block (FR-27)
        // Log out any session in this browser, so the user logs in with the new password.
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: index.php?reset=1');
        exit;
    }
}

$pageTitle  = 'Choose a new password';
$authLayout = true;
require __DIR__ . '/includes/header.php';
?>
<main class="auth">
    <section class="auth-intro">
        <div class="brand brand-light">
            <svg width="32" height="32" viewBox="0 0 28 28" aria-hidden="true"><rect x="2" y="4" width="6" height="20" rx="2" fill="#9AA3B5"/><rect x="11" y="4" width="6" height="14" rx="2" fill="#F08A3C"/><rect x="20" y="4" width="6" height="9" rx="2" fill="#2F5BEA"/></svg>
            <span>Teamsync</span>
        </div>
        <h1>Choose a new password.</h1>
        <p>After this, log in with your new password.</p>
    </section>

    <section class="auth-form-wrap">
        <?php if (!$reset): ?>
            <div class="auth-form">
                <h2>Link not valid</h2>
                <p class="alert alert-error" role="alert">This link has expired or was already used. Ask for a new one.</p>
                <a class="btn btn-primary" href="forgot_password.php">Send a new link</a>
                <p class="muted center"><a href="index.php">Back to log in</a></p>
            </div>
        <?php else: ?>
            <form class="auth-form" method="post" action="reset_password.php" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <h2>New password</h2>
                <p class="muted">For <?= e($reset['email']) ?></p>

                <?php if ($errors): ?>
                    <div class="alert alert-error" role="alert">
                        <?php foreach ($errors as $err): ?>
                            <p><?= e($err) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <label for="password">New password <span class="muted">(at least 8 characters)</span></label>
                <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">

                <label for="password_confirm">Type it again</label>
                <input id="password_confirm" name="password_confirm" type="password" required minlength="8" autocomplete="new-password">

                <button type="submit" class="btn btn-primary">Save new password</button>
            </form>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
