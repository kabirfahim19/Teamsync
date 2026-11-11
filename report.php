<?php
// Contribution report of one project: tasks done, in progress and to do for each member.
// Project members and professors can open it. Issue: Contribution report (US-07, FR-20, FR-21)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/projects.php';
require_once __DIR__ . '/includes/tasks.php';
require_once __DIR__ . '/includes/reports.php';
require_login();

$projectId   = (int) ($_GET['project'] ?? 0);
$userId      = (int) $_SESSION['user_id'];
$isProfessor = $_SESSION['role'] === 'professor';
$project     = get_project($pdo, $projectId);

if (!$project || (!$isProfessor && !is_project_member($pdo, $projectId, $userId))) {
    http_response_code(403);
    exit('Only members of this project and professors can see its report.');
}

$report   = get_contribution($pdo, $projectId);
$totals   = $report['totals'];
$leaderId = (int) $project['leader_id'];

// The longest bar = the member (or unassigned row) with the most tasks, so bars can be compared.
$maxTotal = max(1, $report['unassigned']['total'], ...array_column($report['members'], 'total') ?: [0]);

// Rows drawn in the chart and the table: members, then "Unassigned" if any task has no assignee.
$rows = $report['members'];
if ($report['unassigned']['total'] > 0) {
    $rows[] = $report['unassigned'] + ['user_id' => 0, 'name' => 'Unassigned'];
}

$pageTitle = 'Contribution report';
require __DIR__ . '/includes/header.php';
?>
<main class="container wide">
    <p class="eyebrow">
        <?php if ($isProfessor): ?>
            <a href="professor.php">← All teams</a>
        <?php else: ?>
            <a href="board.php?project=<?= $projectId ?>">← Back to <?= e($project['title']) ?> board</a>
        <?php endif; ?>
    </p>
    <h1>Contribution report</h1>
    <p class="muted"><?= e($project['title']) ?> · who has finished, started and still has which tasks</p>

    <div class="stats">
        <div class="stat"><span class="stat-label">Total tasks</span><span class="stat-value"><?= $totals['total'] ?></span></div>
        <div class="stat"><span class="stat-label">Done</span><span class="stat-value stat-blue"><?= $totals['done'] ?> <small>· <?= percent($totals['done'], $totals['total']) ?>%</small></span></div>
        <div class="stat"><span class="stat-label">In progress</span><span class="stat-value"><?= $totals['in_progress'] ?></span></div>
        <div class="stat"><span class="stat-label">To do</span><span class="stat-value"><?= $totals['todo'] ?></span></div>
    </div>

    <?php if ($totals['total'] === 0): ?>
        <section class="card">
            <p class="muted">This project has no tasks yet, so there is nothing to report.</p>
        </section>
    <?php else: ?>

    <section class="card" aria-labelledby="chart-title">
        <div class="chart-head">
            <h2 id="chart-title">Tasks by member</h2>
            <div class="legend" aria-hidden="true">
                <span><i class="swatch swatch-done"></i>Done</span>
                <span><i class="swatch swatch-in_progress"></i>In progress</span>
                <span><i class="swatch swatch-todo"></i>To do</span>
            </div>
        </div>

        <div class="bar-chart">
            <?php foreach ($rows as $r): ?>
                <div class="bar-row">
                    <span class="bar-name">
                        <?= e($r['name']) ?>
                        <?php if ($r['user_id'] === $leaderId): ?><span class="tag">Leader</span><?php endif; ?>
                    </span>
                    <div class="bar-track" role="img" aria-label="<?= e($r['name']) ?>: <?= $r['done'] ?> done, <?= $r['in_progress'] ?> in progress, <?= $r['todo'] ?> to do">
                        <?php foreach (['done', 'in_progress', 'todo'] as $status): ?>
                            <?php if ($r[$status] > 0): ?>
                                <span class="bar-seg seg-<?= $status ?>" style="width: <?= round($r[$status] / $maxTotal * 100, 2) ?>%"></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <span class="bar-text"><b><?= $r['done'] ?> done</b> · <?= $r['in_progress'] ?> in progress · <?= $r['todo'] ?> to do</span>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="muted small">Bar length = number of tasks. The longest bar has <?= $maxTotal ?> <?= $maxTotal === 1 ? 'task' : 'tasks' ?>.</p>
    </section>

    <section class="card">
        <h2>Numbers</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Member</th>
                        <th scope="col" class="num">Done</th>
                        <th scope="col" class="num">In progress</th>
                        <th scope="col" class="num">To do</th>
                        <th scope="col" class="num">Total</th>
                        <th scope="col" class="num">Share of done work</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= e($r['name']) ?></td>
                            <td class="num"><?= $r['done'] ?></td>
                            <td class="num"><?= $r['in_progress'] ?></td>
                            <td class="num"><?= $r['todo'] ?></td>
                            <td class="num"><?= $r['total'] ?></td>
                            <td class="num"><?= percent($r['done'], $totals['done']) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row">All tasks</th>
                        <td class="num"><?= $totals['done'] ?></td>
                        <td class="num"><?= $totals['in_progress'] ?></td>
                        <td class="num"><?= $totals['todo'] ?></td>
                        <td class="num"><?= $totals['total'] ?></td>
                        <td class="num">100%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
