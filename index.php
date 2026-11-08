<?php
// Log in page
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Prepared statement: the email is sent separately from the SQL (no SQL injection)
    $stmt = $pdo->prepare('SELECT user_id, name, password_hash, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);   // new session id after login
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['role']    = $user['role'];
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Wrong email or password.';
}

$pageTitle  = 'Log in';
$authLayout = true;
require __DIR__ . '/includes/header.php';
?>
<main class="auth">
    <section class="auth-intro">
        <div class="brand brand-light">
            <svg width="32" height="32" viewBox="0 0 28 28" aria-hidden="true"><rect x="2" y="4" width="6" height="20" rx="2" fill="#9AA3B5"/><rect x="11" y="4" width="6" height="14" rx="2" fill="#F08A3C"/><rect x="20" y="4" width="6" height="9" rx="2" fill="#2F5BEA"/></svg>
            <span>Teamsync</span>
        </div>
        <h1>Plan, assign and track your team project in one place.</h1>
        <p>A shared task board for student teams, with reminders and fair contribution reports.</p>
    </section>

    <section class="auth-form-wrap">
        <form class="auth-form" method="post" action="index.php" novalidate>
            <?= csrf_field() ?>
            <h2>Log in</h2>
            <p class="muted">Welcome back. Use the email you signed up with.</p>

            <?php if (isset($_GET['registered'])): ?>
                <p class="alert alert-success" role="status">Account created. You can log in now.</p>
            <?php endif; ?>
            <?php if ($error): ?>
                <p class="alert alert-error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="<?= e($email) ?>" required autocomplete="email">

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">

            <button type="submit" class="btn btn-primary">Log in</button>
            <p class="muted center">New to Teamsync? <a href="register.php">Create an account</a></p>
        </form>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
