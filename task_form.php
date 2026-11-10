<?php
// Add a task (task_form.php?project=ID) or edit/delete one (task_form.php?task=ID).
// Only the team leader of the project can use this page. Issue #4
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/projects.php';
require_once __DIR__ . '/includes/tasks.php';
require_login();

$userId = (int) $_SESSION['user_id'];
$taskId = (int) ($_GET['task'] ?? 0);
$task   = null;

if ($taskId) {
    $task = get_task($pdo, $taskId);
    if (!$task) {
        http_response_code(404);
        exit('Task not found.');
    }
    $projectId = (int) $task['project_id'];
} else {
    $projectId = (int) ($_GET['project'] ?? 0);
}

$project = get_project($pdo, $projectId);
if (!$project || (int) $project['leader_id'] !== $userId) {
    http_response_code(403);
    exit('Only the team leader of this project can add, edit or delete tasks.');
}

$members   = get_project_members($pdo, $projectId);
$memberIds = array_column($members, 'user_id');
$boardUrl  = 'board.php?project=' . $projectId;
$errors    = [];

// Values shown in the form
$values = $task
    ? ['title' => $task['title'], 'assignee_id' => $task['assignee_id'], 'priority' => $task['priority'], 'due_date' => $task['due_date']]
    : ['title' => '', 'assignee_id' => null, 'priority' => 'medium', 'due_date' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    if ($task && isset($_POST['delete'])) {
        delete_task($pdo, $taskId);
        header('Location: ' . $boardUrl . '&msg=deleted');
        exit;
    }

    [$data, $errors] = validate_task_input($_POST, $memberIds);
    $values = $data;

    if (!$errors) {
        if ($task) {
            update_task($pdo, $taskId, $data);
            header('Location: ' . $boardUrl . '&msg=updated');
        } else {
            create_task($pdo, $projectId, $data);
            header('Location: ' . $boardUrl . '&msg=added');
        }
        exit;
    }
}

$pageTitle = $task ? 'Edit task' : 'Add task';
require __DIR__ . '/includes/header.php';
?>
<main class="container narrow">
    <p class="eyebrow"><a href="<?= e($boardUrl) ?>">← Back to <?= e($project['title']) ?> board</a></p>
    <h1><?= $task ? 'Edit task' : 'Add a task' ?></h1>
    <p class="muted"><?= $task ? 'Change the task and save.' : 'New tasks start in the To Do column.' ?></p>

    <form class="card stack-form" method="post" action="task_form.php?<?= $task ? 'task=' . $taskId : 'project=' . $projectId ?>" novalidate>
        <?= csrf_field() ?>

        <?php if ($errors): ?>
            <div class="alert alert-error" role="alert">
                <?php foreach ($errors as $err): ?>
                    <p><?= e($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <label for="title">Title</label>
        <input id="title" name="title" type="text" value="<?= e($values['title']) ?>" required maxlength="100" placeholder="e.g. Draw the class diagram">

        <label for="assignee_id">Assign to</label>
        <select id="assignee_id" name="assignee_id">
            <option value="">Unassigned</option>
            <?php foreach ($members as $m): ?>
                <option value="<?= (int) $m['user_id'] ?>" <?= (int) $values['assignee_id'] === (int) $m['user_id'] ? 'selected' : '' ?>>
                    <?= e($m['name']) ?><?= (int) $m['user_id'] === (int) $project['leader_id'] ? ' (Leader)' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div class="form-row">
            <div class="stack-form">
                <label for="priority">Priority</label>
                <select id="priority" name="priority">
                    <?php foreach (TASK_PRIORITIES as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $values['priority'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="stack-form">
                <label for="due_date">Due date <span class="muted">(optional)</span></label>
                <input id="due_date" name="due_date" type="date" value="<?= e($values['due_date']) ?>">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-inline"><?= $task ? 'Save changes' : 'Add task' ?></button>
            <a href="<?= e($boardUrl) ?>">Cancel</a>
        </div>
    </form>

    <?php if ($task): ?>
        <form class="danger-zone" method="post" action="task_form.php?task=<?= $taskId ?>" data-confirm="Delete this task? This cannot be undone.">
            <?= csrf_field() ?>
            <input type="hidden" name="delete" value="1">
            <button type="submit" class="btn btn-danger btn-inline">Delete task</button>
        </form>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
