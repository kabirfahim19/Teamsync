<?php
// Kanban board of one project: To Do, In Progress, Done. Issue #3
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/projects.php';
require_once __DIR__ . '/includes/tasks.php';
require_login();

$projectId   = (int) ($_GET['project'] ?? 0);
$userId      = (int) $_SESSION['user_id'];
$isProfessor = $_SESSION['role'] === 'professor';

$stmt = $pdo->prepare('SELECT project_id, title, invite_code, leader_id FROM projects WHERE project_id = ?');
$stmt->execute([$projectId]);
$project = $stmt->fetch();

// Members of this project may see its board; professors may see every board, read-only.
if (!$project || (!$isProfessor && !is_project_member($pdo, $projectId, $userId))) {
    http_response_code(403);
    exit('You are not a member of this project.');
}

$leaderId = (int) $project['leader_id'];
$isLeader = $userId === $leaderId;
$columns  = get_tasks_by_status($pdo, $projectId);

$messages = ['added' => 'Task added to To Do.', 'updated' => 'Task saved.', 'deleted' => 'Task deleted.'];
$message  = $messages[$_GET['msg'] ?? ''] ?? null;

$stmt = $pdo->prepare('SELECT COUNT(*) FROM project_members WHERE project_id = ?');
$stmt->execute([$projectId]);
$memberCount = (int) $stmt->fetchColumn();

$total    = count($columns['todo']) + count($columns['in_progress']) + count($columns['done']);
$done     = count($columns['done']);
$progress = $total ? (int) round($done / $total * 100) : 0;

$pageTitle = $project['title'] . ' board';
require __DIR__ . '/includes/header.php';
?>
<main class="container wide">
    <p class="eyebrow">
        <?php if ($isProfessor): ?>
            <a href="professor.php">← All teams</a>
        <?php else: ?>
            <a href="dashboard.php">← All projects</a>
        <?php endif; ?>
    </p>

    <div class="page-head">
        <div>
            <h1><?= e($project['title']) ?></h1>
            <p class="muted board-meta">
                <span><?= $memberCount ?> <?= $memberCount === 1 ? 'member' : 'members' ?></span>
                <?php if ($isProfessor): ?>
                    <span class="readonly-badge">Read-only view</span>
                <?php else: ?>
                    <span>Invite code <span class="code"><?= e($project['invite_code']) ?></span></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="progress-wrap">
            <div class="progress-label">
                <span class="muted">Progress</span>
                <b><?= $done ?> of <?= $total ?> tasks · <?= $progress ?>%</b>
            </div>
            <div class="progress" role="progressbar" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Project progress">
                <div class="progress-fill" style="width: <?= $progress ?>%"></div>
            </div>
            <div class="head-actions board-actions">
                <a class="btn btn-secondary btn-inline" href="report.php?project=<?= $projectId ?>">Contribution report</a>
                <?php if ($isLeader): ?>
                    <a class="btn btn-primary btn-inline" href="task_form.php?project=<?= $projectId ?>">+ Add task</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <p class="alert alert-success" role="status"><?= e($message) ?></p>
    <?php endif; ?>

    <div class="board">
        <?php foreach (TASK_STATUSES as $status => $label): ?>
            <section class="column" aria-label="<?= e($label) ?>">
                <div class="column-head">
                    <h2><span class="dot dot-<?= e($status) ?>"></span><?= e($label) ?></h2>
                    <span class="count"><?= count($columns[$status]) ?></span>
                </div>

                <?php if (!$columns[$status]): ?>
                    <p class="column-empty">No tasks here.</p>
                <?php endif; ?>

                <?php foreach ($columns[$status] as $task): ?>
                    <article class="task-card">
                        <div class="task-top">
                            <?php if ($status === 'done'): ?>
                                <span class="badge badge-done">Completed</span>
                            <?php else: ?>
                                <span class="badge badge-<?= e($task['priority']) ?>"><?= e(TASK_PRIORITIES[$task['priority']] ?? $task['priority']) ?></span>
                            <?php endif; ?>
                            <span class="muted small">#<?= (int) $task['task_id'] ?></span>
                        </div>

                        <h3><?= e($task['title']) ?></h3>

                        <div class="task-meta">
                            <span class="assignee">
                                <span class="avatar avatar-<?= (int) $task['assignee_id'] % 4 ?>" aria-hidden="true"><?= e(initials($task['assignee_name'])) ?></span>
                                <?= e($task['assignee_name'] ?? 'Unassigned') ?>
                            </span>
                            <?php if ($task['due_date']): ?>
                                <span class="<?= is_overdue($task) ? 'overdue' : '' ?>">
                                    <?= is_overdue($task) ? 'Overdue · ' : 'Due ' ?><?= e(date('M j', strtotime($task['due_date']))) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if (can_move_task($task, $userId, $leaderId)): ?>
                            <div class="task-actions">
                                <?php if ($isLeader): ?>
                                    <a class="btn-move" href="task_form.php?task=<?= (int) $task['task_id'] ?>" aria-label="Edit “<?= e($task['title']) ?>”">Edit</a>
                                <?php endif; ?>
                                <?php foreach (task_moves($status) as [$to, $buttonLabel]): ?>
                                    <form method="post" action="api/update_task.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="task_id" value="<?= (int) $task['task_id'] ?>">
                                        <input type="hidden" name="status" value="<?= e($to) ?>">
                                        <button type="submit" class="btn-move" aria-label="Move “<?= e($task['title']) ?>” to <?= e(TASK_STATUSES[$to]) ?>"><?= e($buttonLabel) ?></button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
