<?php
// Move a task to another column (To Do / In Progress / Done).
// Called by the buttons on board.php. Later the drag-and-drop JavaScript can call it too:
// send the header "Accept: application/json" to get a JSON answer instead of a redirect.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/projects.php';
require_once __DIR__ . '/../includes/tasks.php';

$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

function reply(int $code, string $message, bool $wantsJson, array $extra = []): void
{
    http_response_code($code);
    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $code === 200, 'message' => $message] + $extra);
    } else {
        echo $message;
    }
    exit;
}

if (!is_logged_in()) {
    reply(401, 'Please log in first.', $wantsJson);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reply(405, 'Use POST.', $wantsJson);
}
check_csrf();

$taskId = (int) ($_POST['task_id'] ?? 0);
$status = $_POST['status'] ?? '';
$userId = (int) $_SESSION['user_id'];

if (!array_key_exists($status, TASK_STATUSES)) {
    reply(400, 'Unknown status.', $wantsJson);
}

$stmt = $pdo->prepare(
    'SELECT t.task_id, t.project_id, t.assignee_id, p.leader_id
     FROM tasks t JOIN projects p ON p.project_id = t.project_id
     WHERE t.task_id = ?'
);
$stmt->execute([$taskId]);
$task = $stmt->fetch();

if (!$task) {
    reply(404, 'Task not found.', $wantsJson);
}
if (!is_project_member($pdo, (int) $task['project_id'], $userId)) {
    reply(403, 'You are not a member of this project.', $wantsJson);
}
if (!can_move_task($task, $userId, (int) $task['leader_id'])) {
    reply(403, 'Only the person assigned to this task or the team leader can move it.', $wantsJson);
}

$stmt = $pdo->prepare('UPDATE tasks SET status = ? WHERE task_id = ?');
$stmt->execute([$status, $taskId]);

if ($wantsJson) {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status = 'done') AS done FROM tasks WHERE project_id = ?");
    $stmt->execute([$task['project_id']]);
    $count = $stmt->fetch();
    $progress = $count['total'] ? (int) round($count['done'] / $count['total'] * 100) : 0;
    reply(200, 'Task moved.', true, ['status' => $status, 'progress' => $progress]);
}

header('Location: ../board.php?project=' . (int) $task['project_id']);
exit;
