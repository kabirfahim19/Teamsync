<?php
// Contribution report: how many tasks each member has done, is doing and has left. Used by report.php

// Returns:
// [
//   'members'    => [['user_id', 'name', 'done', 'in_progress', 'todo', 'total'], ...]  (most done first)
//   'unassigned' => ['done', 'in_progress', 'todo', 'total']
//   'totals'     => ['done', 'in_progress', 'todo', 'total']
// ]
function get_contribution(PDO $pdo, int $projectId): array
{
    $stmt = $pdo->prepare(
        "SELECT u.user_id, u.name,
                SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END)        AS done,
                SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN t.status = 'todo' THEN 1 ELSE 0 END)        AS todo
         FROM project_members pm
         JOIN users u ON u.user_id = pm.user_id
         LEFT JOIN tasks t ON t.assignee_id = u.user_id AND t.project_id = pm.project_id
         WHERE pm.project_id = ?
         GROUP BY u.user_id, u.name"
    );
    $stmt->execute([$projectId]);

    $members = [];
    foreach ($stmt->fetchAll() as $row) {
        $members[] = count_row($row) + ['user_id' => (int) $row['user_id'], 'name' => $row['name']];
    }
    // Most finished tasks first, then by name
    usort($members, fn($a, $b) => [$b['done'], $a['name']] <=> [$a['done'], $b['name']]);

    $stmt = $pdo->prepare(
        "SELECT SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END)        AS done,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END)        AS todo
         FROM tasks
         WHERE project_id = ? AND assignee_id IS NULL"
    );
    $stmt->execute([$projectId]);
    $unassigned = count_row($stmt->fetch() ?: []);

    $totals = ['done' => 0, 'in_progress' => 0, 'todo' => 0, 'total' => 0];
    foreach (array_merge($members, [$unassigned]) as $row) {
        foreach ($totals as $key => $value) {
            $totals[$key] += $row[$key];
        }
    }

    return ['members' => $members, 'unassigned' => $unassigned, 'totals' => $totals];
}

// SUM() can come back as a string or NULL: turn the three counts into whole numbers and add a total.
function count_row(array $row): array
{
    $done       = (int) ($row['done'] ?? 0);
    $inProgress = (int) ($row['in_progress'] ?? 0);
    $todo       = (int) ($row['todo'] ?? 0);
    return ['done' => $done, 'in_progress' => $inProgress, 'todo' => $todo, 'total' => $done + $inProgress + $todo];
}

// A part as a whole percentage of a total (0 when the total is 0).
function percent(int $part, int $total): int
{
    return $total > 0 ? (int) round($part / $total * 100) : 0;
}
