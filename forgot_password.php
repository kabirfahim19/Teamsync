<?php
// "Forgot your password?" page: sends a reset link by email (FR-26).
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/password_resets.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$mailOn = mail_settings() !== null;
$sent   = false;
$error  = '';
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mailOn) {
    check_csrf();
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        $user = find_user_by_email($pdo, $email);
        // Send only to a real account that did not just get a link. The page says the same either way.
        if ($user && !is_undeliverable($user['email']) && !reset_requested_recently($pdo, (int) $user['user_id'])) {
            $token = create_reset_token($pdo, (int) $user['user_id']);
            [$subject, $html, $text] = reset_email($user['name'], site_url() . '/reset_password.php?token=' . $token);
            send_email($user['email'], $user['name'], $subject, $html, $text);
        }
        $sent = true;
    }
}

$pageTitle  = 'Forgot password';
$authLayout = true;
require __DIR__ . '/includes/header.php';
?>
<main class="auth">
    <section class="auth-intro">
        <div class="brand brand-light">
            <svg width="32" height="32" viewBox="0 0 28 28" aria-hidden="true"><rect x="2" y="4" width="6" height="20" rx="2" fill="#9AA3B5"/><rect x="11" y="4" width="6" height="14" rx="2" fill="#F08A3C"/><rect x="20" y="4" width="6" height="9" rx="2" fill="#2F5BEA"/></svg>
            <span>Teamsync</span>
        </div>
        <h1>Forgot your password? It happens.</h1>
        <p>We email you a link to choose a new one. The link works for one hour.</p>
    </section>

    <section class="auth-form-wrap">
        <form class="auth-form" method="post" action="forgot_password.php" novalidate>
            <?= csrf_field() ?>
            <h2>Reset password</h2>

            <?php if (!$mailOn): ?>
                <p class="alert alert-error" role="alert">Password reset by email is turned off on this site. Ask your team leader for help.</p>
            <?php elseif ($sent): ?>
                <p class="alert alert-success" role="status">If an account uses <?= e($email) ?>, we sent it a link. Check your inbox and the spam folder. The link works for one hour.</p>
            <?php else: ?>
                <p class="muted">Enter the email you signed up with.</p>
                <?php if ($error): ?>
                    <p class="alert alert-error" role="alert"><?= e($error) ?></p>
                <?php endif; ?>

                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="<?= e($email) ?>" required maxlength="100" autocomplete="email">

                <button type="submit" class="btn btn-primary">Send reset link</button>
            <?php endif; ?>

            <p class="muted center"><a href="index.php">Back to log in</a></p>
        </form>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
