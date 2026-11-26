<?php
// Functions for tasks on the Kanban board. Used by board.php and api/update_task.php.

require_once __DIR__ . '/history.php';
require_once __DIR__ . '/handins.php';

const TASK_STATUSES = [
    'todo'        => 'To Do',
    'in_progress' => 'In Progress',
    'review'      => 'Review',
    'done'        => 'Done',
];

// Handed in: waiting for the leader's approval (Review) or approved (Done). Such a task is not late
// any more, is not in "Due soon" and gets no reminder email (FR-30).
const TASK_HANDED_IN = ['review', 'done'];

const TASK_PRIORITIES = [
    'high'   => 'High',
    'medium' => 'Medium',
    'low'    => 'Low',
];

// All tasks of a project, with the assignee's name and the number of comments, grouped by status.
// Returns ['todo' => [...], 'in_progress' => [...], 'review' => [...], 'done' => [...]].
function get_tasks_by_status(PDO $pdo, int $projectId): array
{
    $stmt = $pdo->prepare(
        "SELECT t.task_id, t.title, t.status, t.priority, t.due_date, t.assignee_id, u.name AS assignee_name,
                (SELECT COUNT(*) FROM task_comments c WHERE c.task_id = t.task_id) AS comment_count
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

// Where a task may go from its column (FR-30). The leader may move it anywhere. A member moves their own
// task between To Do, In Progress and Review; only the leader moves a task into Done or out of it,
// so a task counts as done only when the leader has approved it.
function task_drop_targets(string $status, bool $isLeader): array
{
    if (!isset(TASK_STATUSES[$status])) {
        return [];
    }
    $allowed = $isLeader ? array_keys(TASK_STATUSES) : ($status === 'done' ? [] : ['todo', 'in_progress', 'review']);
    return array_values(array_diff($allowed, [$status]));
}

// May this user move this task to that column?
function can_move_task_to(array $task, int $userId, int $leaderId, string $to): bool
{
    return can_move_task($task, $userId, $leaderId)
        && in_array($to, task_drop_targets($task['status'], $userId === $leaderId), true);
}

// Moves a task to another column and writes the move into the task's history (FR-29).
// A hand-in to Review (FR-31) brings ['link' => ..., 'note' => ...]: it is posted as a comment and the link is saved.
// Everything is saved together or not at all.
function move_task(PDO $pdo, int $taskId, string $from, string $to, int $userId, ?array $handIn = null): void
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE tasks SET status = ? WHERE task_id = ?');
        $stmt->execute([$to, $taskId]);
        add_task_event($pdo, $taskId, $userId, $from, $to);
        if ($handIn !== null) {
            add_comment($pdo, $taskId, $userId, handin_comment($handIn['link'], $handIn['note']));
            add_handin($pdo, $taskId, $userId, $handIn['link']);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

// Is the task late? (deadline before today and not handed in)
function is_overdue(array $task): bool
{
    return !in_array($task['status'], TASK_HANDED_IN, true)
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

// One task with its assignee's name, for the task page.
function get_task_details(PDO $pdo, int $taskId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT t.task_id, t.project_id, t.assignee_id, t.title, t.status, t.priority, t.due_date, u.name AS assignee_name
         FROM tasks t
         LEFT JOIN users u ON u.user_id = t.assignee_id
         WHERE t.task_id = ?'
    );
    $stmt->execute([$taskId]);
    return $stmt->fetch() ?: null;
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
    // A new due date or a new assignee needs a new email reminder (FR-19).
    $old = get_task($pdo, $taskId);
    $resetReminder = !$old
        || $old['due_date'] !== $data['due_date']
        || (int) $old['assignee_id'] !== (int) $data['assignee_id'];

    $sql = 'UPDATE tasks SET title = ?, assignee_id = ?, priority = ?, due_date = ?'
         . ($resetReminder ? ', reminder_sent_at = NULL' : '')
         . ' WHERE task_id = ?';
    $stmt = $pdo->prepare($sql);
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
         WHERE t.assignee_id = ? AND t.status NOT IN ('review', 'done')
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
// A member hands a finished task in to Review; the leader approves it to Done or sends it back (FR-30).
function task_moves(string $status, bool $isLeader): array
{
    $moves = $isLeader
        ? [
            'todo'        => [['in_progress', 'Start →']],
            'in_progress' => [['todo', '← Back'], ['done', 'Done →']],
            'review'      => [['in_progress', '← Send back'], ['done', 'Approve →']],
            'done'        => [['in_progress', '← Reopen']],
        ]
        : [
            'todo'        => [['in_progress', 'Start →']],
            'in_progress' => [['todo', '← Back'], ['review', 'Review →']],
            'review'      => [['in_progress', '← Back']],
            'done'        => [],
        ];
    return $moves[$status] ?? [];
}

// Tasks in Review in the projects this user leads: the leader's list of tasks to approve (FR-30),
// each with the link to the work from its newest hand-in (FR-31), or null.
function get_waiting_for_review(PDO $pdo, int $leaderId): array
{
    $stmt = $pdo->prepare(
        "SELECT t.task_id, t.title, u.name AS assignee_name, p.project_id, p.title AS project_title,
                (SELECT h.link FROM task_handins h WHERE h.task_id = t.task_id
                 ORDER BY h.created_at DESC, h.handin_id DESC LIMIT 1) AS handin_link
         FROM tasks t
         JOIN projects p ON p.project_id = t.project_id
         LEFT JOIN users u ON u.user_id = t.assignee_id
         WHERE p.leader_id = ? AND t.status = 'review'
         ORDER BY p.title, t.task_id"
    );
    $stmt->execute([$leaderId]);
    return $stmt->fetchAll();
}
