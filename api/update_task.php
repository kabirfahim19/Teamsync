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
if ($_SESSION['role'] === 'professor') {
    reply(403, 'Professors have read-only access.', $wantsJson);
}
check_csrf();

$taskId = (int) ($_POST['task_id'] ?? 0);
$status = $_POST['status'] ?? '';
$userId = (int) $_SESSION['user_id'];

if (!array_key_exists($status, TASK_STATUSES)) {
    reply(400, 'Unknown status.', $wantsJson);
}

$stmt = $pdo->prepare(
    'SELECT t.task_id, t.project_id, t.assignee_id, t.status, p.leader_id
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
// Only the leader moves a task into Done or out of it; a member hands a finished task in to Review (FR-30).
if ($task['status'] !== $status && !can_move_task_to($task, $userId, (int) $task['leader_id'], $status)) {
    reply(403, 'Only the team leader can move a task into Done or out of it. Move your finished task to Review.', $wantsJson);
}

if ($task['status'] !== $status) {
    move_task($pdo, $taskId, $task['status'], $status, $userId);   // also writes the task's history
}

if ($wantsJson) {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status = 'done') AS done FROM tasks WHERE project_id = ?");
    $stmt->execute([$task['project_id']]);
    $count = $stmt->fetch();
    $total    = (int) $count['total'];
    $done     = (int) $count['done'];
    $progress = $total ? (int) round($done / $total * 100) : 0;
    // The card's new buttons and the columns it may be dragged to, for this user (the browser draws them).
    $isLeader = $userId === (int) $task['leader_id'];
    reply(200, 'Task moved.', true, [
        'status'   => $status,
        'labels'   => TASK_STATUSES,
        'moves'    => task_moves($status, $isLeader),
        'targets'  => task_drop_targets($status, $isLeader),
        'done'     => $done,
        'total'    => $total,
        'progress' => $progress,
    ]);
}

header('Location: ../board.php?project=' . (int) $task['project_id']);
exit;
