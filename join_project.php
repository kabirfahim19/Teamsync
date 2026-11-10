<?php
// Join a project with an invite code (Team Leader or Team Member). Issue #2
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/projects.php';
require_role(['leader', 'member']);

$codeInput = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $codeInput = trim($_POST['invite_code'] ?? '');
    $code = normalize_invite_code($codeInput);
    $project = $code ? find_project_by_code($pdo, $code) : null;

    if (!$project) {
        $error = 'Invite code not found. Check the code with your team leader.';
    } elseif (!join_project($pdo, (int) $project['project_id'], (int) $_SESSION['user_id'])) {
        $error = 'You are already in this project.';
    } else {
        header('Location: dashboard.php?joined=' . $project['project_id']);
        exit;
    }
}

$pageTitle = 'Join project';
require __DIR__ . '/includes/header.php';
?>
<main class="container narrow">
    <p class="eyebrow"><a href="dashboard.php">← Back to dashboard</a></p>
    <h1>Join a project</h1>
    <p class="muted">Enter the invite code your team leader gave you.</p>

    <form class="card stack-form" method="post" action="join_project.php" novalidate>
        <?= csrf_field() ?>

        <?php if ($error): ?>
            <p class="alert alert-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <label for="invite_code">Invite code</label>
        <input id="invite_code" name="invite_code" type="text" value="<?= e($codeInput) ?>" required maxlength="10" placeholder="TS-KKK7" autocomplete="off" autocapitalize="characters" class="code-input">

        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-inline">Join project</button>
            <a href="dashboard.php">Cancel</a>
        </div>
    </form>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
