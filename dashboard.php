<?php
// First page after login. The Kanban board comes here in Week 11.
require_once __DIR__ . '/includes/auth.php';
require_login();

$isLeader = $_SESSION['role'] === 'leader';

// Projects this user belongs to
$stmt = $pdo->prepare(
    'SELECT p.project_id, p.title, p.invite_code, p.leader_id
     FROM projects p
     JOIN project_members pm ON pm.project_id = p.project_id
     WHERE pm.user_id = ?
     ORDER BY p.created_at DESC, p.project_id DESC'
);
$stmt->execute([$_SESSION['user_id']]);
$projects = $stmt->fetchAll();

// Just created a project? Find it in this user's own list (so nobody can see another team's code).
$created = null;
if (isset($_GET['created'])) {
    foreach ($projects as $p) {
        if ((int) $p['project_id'] === (int) $_GET['created']) {
            $created = $p;
        }
    }
}

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<main class="container">
    <div class="page-head">
        <div>
            <p class="eyebrow">Software Engineering · Fall 2026</p>
            <h1>Hello, <?= e($_SESSION['name']) ?></h1>
            <p class="muted">You are logged in as <b><?= e(role_label($_SESSION['role'])) ?></b>.</p>
        </div>
        <?php if ($isLeader): ?>
            <a class="btn btn-primary btn-inline" href="create_project.php">+ Create project</a>
        <?php endif; ?>
    </div>

    <?php if ($created): ?>
        <div class="alert alert-success" role="status">
            <p><b><?= e($created['title']) ?></b> was created. Share this invite code with your team:</p>
            <p class="invite-code"><?= e($created['invite_code']) ?></p>
        </div>
    <?php endif; ?>

    <section class="card">
        <h2>My projects</h2>
        <?php if (!$projects): ?>
            <?php if ($isLeader): ?>
                <p class="muted">You have no projects yet. Click <b>Create project</b> to start one.</p>
            <?php else: ?>
                <p class="muted">You are not in a project yet. Ask your team leader for the invite code.</p>
            <?php endif; ?>
        <?php else: ?>
            <ul class="project-list">
                <?php foreach ($projects as $p): ?>
                    <li>
                        <b><?= e($p['title']) ?></b>
                        <?php if ((int) $p['leader_id'] === (int) $_SESSION['user_id']): ?>
                            <span class="tag">Leader</span>
                        <?php endif; ?>
                        <span class="muted">Invite code <span class="code"><?= e($p['invite_code']) ?></span></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
