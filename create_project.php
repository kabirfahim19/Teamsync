<?php
// Create a project (Team Leader only). Issue #1
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/projects.php';
require_role(['leader']);

$title = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $title = trim($_POST['title'] ?? '');

    if ($title === '' || mb_strlen($title) > 100) {
        $error = 'Enter a project title (up to 100 characters).';
    } else {
        try {
            $project = create_project($pdo, $title, (int) $_SESSION['user_id']);
            header('Location: dashboard.php?created=' . $project['project_id']);
            exit;
        } catch (Throwable $e) {
            $error = 'Could not create the project. Please try again.';
        }
    }
}

$pageTitle = 'Create project';
require __DIR__ . '/includes/header.php';
?>
<main class="container narrow">
    <p class="eyebrow"><a href="dashboard.php">← Back to dashboard</a></p>
    <h1>Create a project</h1>
    <p class="muted">You will get an invite code to share with your team.</p>

    <form class="card stack-form" method="post" action="create_project.php" novalidate>
        <?= csrf_field() ?>

        <?php if ($error): ?>
            <p class="alert alert-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <label for="title">Project title</label>
        <input id="title" name="title" type="text" value="<?= e($title) ?>" required maxlength="100" placeholder="e.g. SE Project">

        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-inline">Create project</button>
            <a href="dashboard.php">Cancel</a>
        </div>
    </form>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
