<?php
// First page after login. The Kanban board comes here in Week 11.
require_once __DIR__ . '/includes/auth.php';
require_login();

// Projects this user belongs to
$stmt = $pdo->prepare(
    'SELECT p.project_id, p.title, p.invite_code
     FROM projects p
     JOIN project_members pm ON pm.project_id = p.project_id
     WHERE pm.user_id = ?
     ORDER BY p.created_at DESC'
);
$stmt->execute([$_SESSION['user_id']]);
$projects = $stmt->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<main class="container">
    <p class="eyebrow">Software Engineering · Fall 2026</p>
    <h1>Hello, <?= e($_SESSION['name']) ?></h1>
    <p class="muted">You are logged in as <b><?= e(role_label($_SESSION['role'])) ?></b>.</p>

    <section class="card">
        <h2>My projects</h2>
        <?php if (!$projects): ?>
            <p class="muted">You are not in a project yet. Creating and joining projects is coming in Week 11.</p>
        <?php else: ?>
            <ul class="project-list">
                <?php foreach ($projects as $p): ?>
                    <li><b><?= e($p['title']) ?></b> <span class="muted">Invite code <?= e($p['invite_code']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
