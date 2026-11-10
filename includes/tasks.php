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
