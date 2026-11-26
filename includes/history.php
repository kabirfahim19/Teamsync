<?php
// Task history (FR-29): who added a task and who moved it to which column, and when.
// The task page lists it, so a leader can see who put a card in Done and when.
// Times come from PHP in Korean time, like everything else, never from the database clock.

// Writes one line of history. $from is null when the task was just added.
function add_task_event(PDO $pdo, int $taskId, int $userId, ?string $from, string $to): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO task_events (task_id, user_id, from_status, to_status, created_at) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$taskId, $userId, $from, $to, date('Y-m-d H:i:s')]);
}

// The history of one task, oldest first, with the name of who did it.
function get_task_events(PDO $pdo, int $taskId): array
{
    $stmt = $pdo->prepare(
        'SELECT e.event_id, e.user_id, e.from_status, e.to_status, e.created_at, u.name AS user_name
         FROM task_events e
         LEFT JOIN users u ON u.user_id = e.user_id
         WHERE e.task_id = ?
         ORDER BY e.created_at, e.event_id'
    );
    $stmt->execute([$taskId]);
    return $stmt->fetchAll();
}

// "Alex Kim added the task" or "Minji Park moved it from In Progress to Done"
function task_event_text(array $event): string
{
    $who = $event['user_name'] ?? 'A deleted account';
    if ($event['from_status'] === null) {
        return $who . ' added the task';
    }
    $from = TASK_STATUSES[$event['from_status']] ?? $event['from_status'];
    $to   = TASK_STATUSES[$event['to_status']] ?? $event['to_status'];
    return $who . ' moved it from ' . $from . ' to ' . $to;
}
