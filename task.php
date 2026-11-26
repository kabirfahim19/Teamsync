<?php
// One task and its comments (FR-28): task.php?id=ID
// Members of the project read and write comments; the author or the team leader can delete one.
// Professors can open every task and read its comments, read-only.
// Under the comments: the task's history, who added it and who moved it where (FR-29).
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/projects.php';
require_once __DIR__ . '/includes/tasks.php';
require_once __DIR__ . '/includes/comments.php';
require_login();

$taskId      = (int) ($_GET['id'] ?? 0);
$userId      = (int) $_SESSION['user_id'];
$isProfessor = $_SESSION['role'] === 'professor';

$task = get_task_details($pdo, $taskId);
if (!$task) {
    http_response_code(404);
    exit('Task not found.');
}

$projectId = (int) $task['project_id'];
$project   = get_project($pdo, $projectId);
$isMember  = is_project_member($pdo, $projectId, $userId);
if (!$isMember && !$isProfessor) {
    http_response_code(403);
    exit('You are not a member of this project.');
}

$leaderId   = (int) $project['leader_id'];
$isLeader   = $userId === $leaderId;
$canComment = $isMember && !$isProfessor;
$pageUrl    = 'task.php?id=' . $taskId;
$errors     = [];
$draft      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canComment) {
        http_response_code(403);
        exit('Professors have read-only access.');
    }
    check_csrf();

    if (isset($_POST['delete_comment'])) {
        $comment = get_comment($pdo, (int) $_POST['delete_comment']);
        if (!$comment || (int) $comment['task_id'] !== $taskId) {
            http_response_code(404);
            exit('Comment not found.');
        }
        if (!can_delete_comment($comment, $userId, $leaderId)) {
            http_response_code(403);
            exit('Only the author or the team leader can delete this comment.');
        }
        delete_comment($pdo, (int) $comment['comment_id']);
        header('Location: ' . $pageUrl . '&msg=deleted#comments');
        exit;
    }

    [$draft, $errors] = validate_comment((string) ($_POST['body'] ?? ''));
    if (!$errors) {
        $commentId = add_comment($pdo, $taskId, $userId, $draft);
        header('Location: ' . $pageUrl . '#comment-' . $commentId);
        exit;
    }
}

$comments = get_comments($pdo, $taskId);
$events   = get_task_events($pdo, $taskId);   // history (FR-29)
$message  = ($_GET['msg'] ?? '') === 'deleted' ? 'Comment deleted.' : null;

$priority = TASK_PRIORITIES[$task['priority']] ?? $task['priority'];
if (!$task['due_date']) {
    $dueText = 'No due date';
} elseif (is_overdue($task)) {
    $dueText = 'Overdue · ' . date('M j', strtotime($task['due_date']));
} elseif (in_array($task['status'], TASK_HANDED_IN, true)) {
    $dueText = date('M j', strtotime($task['due_date']));   // handed in: no longer late
} else {
    $dueText = due_label($task['due_date']);
}

$pageTitle = $task['title'];
require __DIR__ . '/includes/header.php';
?>
<main class="container narrow">
    <p class="eyebrow"><a href="board.php?project=<?= $projectId ?>">← Back to <?= e($project['title']) ?> board</a></p>

    <div class="task-head">
        <div class="task-top">
            <span class="badge badge-<?= e($task['priority']) ?>"><?= e($priority) ?> priority</span>
            <span class="muted small">Task #<?= $taskId ?></span>
            <?php if ($isProfessor): ?>
                <span class="readonly-badge small-badge">Read-only view</span>
            <?php endif; ?>
        </div>
        <h1><?= e($task['title']) ?></h1>

        <dl class="task-facts">
            <div>
                <dt>Status</dt>
                <dd><span class="dot dot-<?= e($task['status']) ?>" aria-hidden="true"></span><?= e(TASK_STATUSES[$task['status']]) ?></dd>
            </div>
            <div>
                <dt>Assigned to</dt>
                <dd>
                    <span class="avatar avatar-<?= (int) $task['assignee_id'] % 4 ?>" aria-hidden="true"><?= e(initials($task['assignee_name'])) ?></span>
                    <?= e($task['assignee_name'] ?? 'Unassigned') ?>
                </dd>
            </div>
            <div>
                <dt>Due</dt>
                <dd class="<?= is_overdue($task) ? 'overdue' : '' ?>"><?= e($dueText) ?></dd>
            </div>
        </dl>

        <?php if ($isLeader): ?>
            <p class="task-head-actions"><a class="btn-move" href="task_form.php?task=<?= $taskId ?>">Edit task</a></p>
        <?php endif; ?>
    </div>

    <section class="card comments" id="comments" aria-labelledby="comments-title">
        <h2 id="comments-title">Comments <span class="count"><?= count($comments) ?></span></h2>

        <?php if ($message): ?>
            <p class="alert alert-success" role="status"><?= e($message) ?></p>
        <?php endif; ?>

        <?php if (!$comments): ?>
            <p class="muted comment-empty">No comments yet.<?= $canComment ? ' Ask a question or give an update for your team.' : '' ?></p>
        <?php else: ?>
            <ol class="comment-list">
                <?php foreach ($comments as $c): ?>
                    <li class="comment" id="comment-<?= (int) $c['comment_id'] ?>">
                        <span class="avatar avatar-<?= (int) $c['user_id'] % 4 ?>" aria-hidden="true"><?= e(initials($c['author_name'])) ?></span>
                        <div>
                            <p class="comment-head">
                                <b><?= e($c['author_name']) ?></b>
                                <?php if ((int) $c['user_id'] === $leaderId): ?><span class="tag tag-inline">Leader</span><?php endif; ?>
                                <time class="muted small" datetime="<?= e(date('c', strtotime($c['created_at']))) ?>"><?= e(comment_time($c['created_at'])) ?></time>
                            </p>
                            <p class="comment-text"><?= e($c['body']) ?></p>
                            <?php if ($canComment && can_delete_comment($c, $userId, $leaderId)): ?>
                                <form method="post" action="<?= e($pageUrl) ?>" data-confirm="Delete this comment?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="delete_comment" value="<?= (int) $c['comment_id'] ?>">
                                    <button type="submit" class="link-button" aria-label="Delete the comment by <?= e($c['author_name']) ?> from <?= e(comment_time($c['created_at'])) ?>">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <?php if ($canComment): ?>
            <form class="stack-form comment-form" method="post" action="<?= e($pageUrl) ?>#comments" novalidate>
                <?= csrf_field() ?>
                <?php if ($errors): ?>
                    <div class="alert alert-error" role="alert">
                        <?php foreach ($errors as $err): ?>
                            <p><?= e($err) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <label for="body">Add a comment</label>
                <textarea id="body" name="body" rows="4" maxlength="<?= COMMENT_MAX_LENGTH ?>" required placeholder="Ask a question, share a link or give an update"><?= e($draft) ?></textarea>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-inline">Post comment</button>
                    <span class="muted small">Up to <?= COMMENT_MAX_LENGTH ?> characters. Your team sees it on this task.</span>
                </div>
            </form>
        <?php elseif ($isProfessor): ?>
            <p class="muted small comment-readonly">Professors can read the comments but not write them.</p>
        <?php endif; ?>
    </section>

    <section class="card history" aria-labelledby="history-title">
        <h2 id="history-title">History</h2>
        <?php if (!$events): ?>
            <p class="muted small">No history yet. Moves made before the history was added are not shown.</p>
        <?php else: ?>
            <ol class="history-list">
                <?php foreach ($events as $ev): ?>
                    <li>
                        <span class="dot dot-<?= e($ev['to_status']) ?>" aria-hidden="true"></span>
                        <span><?= e(task_event_text($ev)) ?></span>
                        <time class="muted small" datetime="<?= e(date('c', strtotime($ev['created_at']))) ?>"><?= e(comment_time($ev['created_at'])) ?></time>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
