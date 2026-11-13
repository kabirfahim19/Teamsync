<?php
// Sign up page
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$roles  = ['leader' => 'Team Leader', 'member' => 'Team Member', 'professor' => 'Professor'];

// Only someone who knows the professor code (set in config/db.php) can sign up as Professor.
// With no code set, professor sign-up is turned off.
$professorCode = isset($PROFESSOR_CODE) ? (string) $PROFESSOR_CODE : '';

$errors = [];
$name   = '';
$email  = '';
$role   = 'member';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'member';

    // Check the form
    if ($name === '' || mb_strlen($name) > 50) {
        $errors[] = 'Enter your name (up to 50 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!array_key_exists($role, $roles)) {
        $errors[] = 'Choose a role.';
    } elseif ($role === 'professor') {
        $sentCode = trim($_POST['professor_code'] ?? '');
        if ($professorCode === '') {
            $errors[] = 'Professor sign-up is turned off on this site. Ask the team leader to set it up.';
        } elseif (!hash_equals($professorCode, $sentCode)) {
            $errors[] = 'The professor code is wrong. Ask the team leader for the code.';
        }
    }

    // Is the email already used?
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    // Save the new user. The password is stored only as a hash.
    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        header('Location: index.php?registered=1');
        exit;
    }
}

$pageTitle  = 'Create account';
$authLayout = true;
require __DIR__ . '/includes/header.php';
?>
<main class="auth">
    <section class="auth-intro">
        <div class="brand brand-light">
            <svg width="32" height="32" viewBox="0 0 28 28" aria-hidden="true"><rect x="2" y="4" width="6" height="20" rx="2" fill="#9AA3B5"/><rect x="11" y="4" width="6" height="14" rx="2" fill="#F08A3C"/><rect x="20" y="4" width="6" height="9" rx="2" fill="#2F5BEA"/></svg>
            <span>Teamsync</span>
        </div>
        <h1>Create your account and join your team.</h1>
        <p>Team leaders create a project. Members join it with an invite code.</p>
    </section>

    <section class="auth-form-wrap">
        <form class="auth-form" method="post" action="register.php" novalidate>
            <?= csrf_field() ?>
            <h2>Create account</h2>

            <?php if ($errors): ?>
                <div class="alert alert-error" role="alert">
                    <?php foreach ($errors as $err): ?>
                        <p><?= e($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <label for="name">Full name</label>
            <input id="name" name="name" type="text" value="<?= e($name) ?>" required maxlength="50" autocomplete="name">

            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="<?= e($email) ?>" required maxlength="100" autocomplete="email">

            <label for="password">Password <span class="muted">(at least 8 characters)</span></label>
            <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">

            <label for="role">I am a</label>
            <select id="role" name="role">
                <?php foreach ($roles as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $role === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="field" id="professor-code-row">
                <label for="professor_code">Professor code <span class="muted">(only for professors)</span></label>
                <input id="professor_code" name="professor_code" type="password" autocomplete="off">
            </div>

            <button type="submit" class="btn btn-primary">Create account</button>
            <p class="muted center">Already have an account? <a href="index.php">Log in</a></p>
        </form>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
