<?php
// Functions for tasks on the Kanban board. Used by board.php and api/update_task.php.

const TASK_STATUSES = [
    'todo'        => 'To Do',
    'in_progress' => 'In Progress',
    'done'        => 'Done',
];

const TASK_PRIORITIES = [
    'high'   => 'High',
    'medium' => 'Medium',
    'low'    => 'Low',
];

// All tasks of a project, with the assignee's name, grouped by status.
// Returns ['todo' => [...], 'in_progress' => [...], 'done' => [...]].
function get_tasks_by_status(PDO $pdo, int $projectId): array
{
    $stmt = $pdo->prepare(
        "SELECT t.task_id, t.title, t.status, t.priority, t.due_date, t.assignee_id, u.name AS assignee_name
         FROM tasks t
         LEFT JOIN users u ON u.user_id = t.assignee_id
         WHERE t.project_id = ?
         ORDER BY CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
                  t.due_date IS NULL, t.due_date, t.task_id"
    );
    $stmt->execute([$projectId]);

    $columns = array_fill_keys(array_keys(TASK_STATUSES), []);
    foreach ($stmt->fetchAll() as $task) {
        $columns[$task['status']][] = $task;
    }
    return $columns;
}

// Only the team leader or the person the task is assigned to may move it.
function can_move_task(array $task, int $userId, int $leaderId): bool
{
    return $userId === $leaderId || $userId === (int) $task['assignee_id'];
}

// Is the task late? (deadline before today and not finished)
function is_overdue(array $task): bool
{
    return $task['status'] !== 'done'
        && !empty($task['due_date'])
        && $task['due_date'] < date('Y-m-d');
}

// "Kabir Md Fahim" -> "KM"
function initials(?string $name): string
{
    $parts = preg_split('/\s+/', trim($name ?? ''));
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $letters ?: '?';
}

function get_task(PDO $pdo, int $taskId): ?array
{
    $stmt = $pdo->prepare('SELECT task_id, project_id, assignee_id, title, status, priority, due_date FROM tasks WHERE task_id = ?');
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    return $task ?: null;
}

// Check the add/edit form. $memberIds = user ids allowed as assignee.
// Returns [$cleanData, $errors]. $errors is empty when everything is OK.
function validate_task_input(array $input, array $memberIds): array
{
    $errors = [];

    $title = trim($input['title'] ?? '');
    if ($title === '') {
        $errors[] = 'Enter a task title.';
    } elseif (mb_strlen($title) > 100) {
        $errors[] = 'The title can be at most 100 characters.';
    }

    $assignee   = $input['assignee_id'] ?? '';
    $assigneeId = $assignee === '' ? null : (int) $assignee;
    if ($assigneeId !== null && !in_array($assigneeId, array_map('intval', $memberIds), true)) {
        $errors[] = 'Choose a member of this project.';
    }

    $priority = $input['priority'] ?? 'medium';
    if (!array_key_exists($priority, TASK_PRIORITIES)) {
        $errors[] = 'Choose a priority.';
    }

    $due = trim($input['due_date'] ?? '');
    if ($due !== '') {
        $date = DateTime::createFromFormat('!Y-m-d', $due);
        if (!$date || $date->format('Y-m-d') !== $due) {
            $errors[] = 'Enter a valid due date.';
        }
    }

    $data = [
        'title'       => $title,
        'assignee_id' => $assigneeId,
        'priority'    => $priority,
        'due_date'    => $due === '' ? null : $due,
    ];
    return [$data, $errors];
}

// New tasks always start in the To Do column.
function create_task(PDO $pdo, int $projectId, array $data): int
{
    $stmt = $pdo->prepare(
        "INSERT INTO tasks (project_id, assignee_id, title, status, priority, due_date) VALUES (?, ?, ?, 'todo', ?, ?)"
    );
    $stmt->execute([$projectId, $data['assignee_id'], $data['title'], $data['priority'], $data['due_date']]);
    return (int) $pdo->lastInsertId();
}

function update_task(PDO $pdo, int $taskId, array $data): void
{
    $stmt = $pdo->prepare('UPDATE tasks SET title = ?, assignee_id = ?, priority = ?, due_date = ? WHERE task_id = ?');
    $stmt->execute([$data['title'], $data['assignee_id'], $data['priority'], $data['due_date'], $taskId]);
}

function delete_task(PDO $pdo, int $taskId): void
{
    $stmt = $pdo->prepare('DELETE FROM tasks WHERE task_id = ?');
    $stmt->execute([$taskId]);
}

// The user's unfinished tasks that are overdue or due within $days days, in projects they belong to.
// Overdue first (oldest deadline first), then by deadline.
function get_due_soon(PDO $pdo, int $userId, int $days = 2): array
{
    $limit = (new DateTime('today'))->modify("+$days days")->format('Y-m-d');
    $stmt = $pdo->prepare(
        "SELECT t.task_id, t.title, t.due_date, t.status, t.priority, p.project_id, p.title AS project_title
         FROM tasks t
         JOIN projects p ON p.project_id = t.project_id
         JOIN project_members pm ON pm.project_id = t.project_id AND pm.user_id = t.assignee_id
         WHERE t.assignee_id = ? AND t.status <> 'done'
           AND t.due_date IS NOT NULL AND t.due_date <= ?
         ORDER BY t.due_date, CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END, t.task_id"
    );
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

// "Overdue · Nov 9", "Due today", "Due tomorrow" or "Due Nov 14".
function due_label(string $dueDate): string
{
    $today = new DateTime('today');
    $due   = new DateTime($dueDate);
    $days  = (int) $today->diff($due)->format('%r%a');

    if ($days < 0) {
        return 'Overdue · ' . $due->format('M j');
    }
    if ($days === 0) {
        return 'Due today';
    }
    if ($days === 1) {
        return 'Due tomorrow';
    }
    return 'Due ' . $due->format('M j');
}

// The buttons shown on a card: where the task can go from its current column.
function task_moves(string $status): array
{
    $moves = [
        'todo'        => [['in_progress', 'Start →']],
        'in_progress' => [['todo', '← Back'], ['done', 'Done →']],
        'done'        => [['in_progress', '← Reopen']],
    ];
    return $moves[$status] ?? [];
}
