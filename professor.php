<?php
// Professor dashboard: every team's progress on one page, read-only. Issue #10 (US-08, FR-22 to FR-24)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/reports.php';
require_role(['professor']);

// One row per project. Overdue = not done and due date before today.
$stmt = $pdo->prepare(
    "SELECT p.project_id, p.title, u.name AS leader_name,
            (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.project_id) AS members,
            (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.project_id) AS total,
            (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.project_id AND t.status = 'done') AS done,
            (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.project_id AND t.status <> 'done'
                AND t.due_date IS NOT NULL AND t.due_date < ?) AS overdue
     FROM projects p
     JOIN users u ON u.user_id = p.leader_id"
);
$stmt->execute([date('Y-m-d')]);

$teams = [];
foreach ($stmt->fetchAll() as $row) {
    $row['members']  = (int) $row['members'];
    $row['total']    = (int) $row['total'];
    $row['done']     = (int) $row['done'];
    $row['overdue']  = (int) $row['overdue'];
    $row['progress'] = percent($row['done'], $row['total']);
    $teams[] = $row;
}

// Teams furthest behind first, so the professor sees them at the top.
usort($teams, fn($a, $b) => [$a['progress'], -$a['overdue'], $a['title']] <=> [$b['progress'], -$b['overdue'], $b['title']]);

$teamCount    = count($teams);
$avgProgress  = $teamCount ? (int) round(array_sum(array_column($teams, 'progress')) / $teamCount) : 0;
$totalOverdue = array_sum(array_column($teams, 'overdue'));
$totalTasks   = array_sum(array_column($teams, 'total'));

$pageTitle = 'All teams';
require __DIR__ . '/includes/header.php';
?>
<main class="container wide">
    <div class="page-head">
        <div>
            <p class="eyebrow">Software Engineering · Fall 2026</p>
            <h1>All teams</h1>
            <p class="muted">Every team's progress on one page. Teams furthest behind are listed first.</p>
        </div>
        <span class="readonly-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            Read-only access
        </span>
    </div>

    <div class="stats">
        <div class="stat"><span class="stat-label">Teams</span><span class="stat-value"><?= $teamCount ?></span></div>
        <div class="stat"><span class="stat-label">Average progress</span><span class="stat-value stat-blue"><?= $avgProgress ?>%</span></div>
        <div class="stat"><span class="stat-label">Overdue tasks</span><span class="stat-value <?= $totalOverdue ? 'stat-warn' : '' ?>"><?= $totalOverdue ?></span></div>
        <div class="stat"><span class="stat-label">Tasks in all teams</span><span class="stat-value"><?= $totalTasks ?></span></div>
    </div>

    <section class="card">
        <h2>Team progress</h2>
        <?php if (!$teams): ?>
            <p class="muted">No team has created a project yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Project</th>
                            <th scope="col">Leader</th>
                            <th scope="col" class="num">Members</th>
                            <th scope="col">Progress</th>
                            <th scope="col" class="num">Tasks done</th>
                            <th scope="col">Overdue</th>
                            <th scope="col"><span class="visually-hidden">Links</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teams as $t): ?>
                            <tr>
                                <td><b><?= e($t['title']) ?></b></td>
                                <td><?= e($t['leader_name']) ?></td>
                                <td class="num"><?= $t['members'] ?></td>
                                <td>
                                    <span class="mini-progress">
                                        <span class="progress"><span class="progress-fill" style="width: <?= $t['progress'] ?>%"></span></span>
                                        <b><?= $t['progress'] ?>%</b>
                                    </span>
                                </td>
                                <td class="num"><?= $t['done'] ?> / <?= $t['total'] ?></td>
                                <td>
                                    <?php if ($t['overdue']): ?>
                                        <span class="overdue"><?= $t['overdue'] ?> overdue</span>
                                    <?php else: ?>
                                        <span class="muted">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="row-links">
                                    <a href="board.php?project=<?= (int) $t['project_id'] ?>">Board</a>
                                    <a href="report.php?project=<?= (int) $t['project_id'] ?>">Report</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
