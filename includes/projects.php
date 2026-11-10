<?php
// Functions for projects. Used by create_project.php (and later by the join page).

// Make a new invite code like "TS-7K2Q" that no other project uses.
// Letters that look alike (O/0, I/1) are left out so codes are easy to read aloud.
function generate_invite_code(PDO $pdo): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $check = $pdo->prepare('SELECT 1 FROM projects WHERE invite_code = ?');

    for ($try = 0; $try < 20; $try++) {
        $code = 'TS-';
        for ($i = 0; $i < 4; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $check->execute([$code]);
        if (!$check->fetch()) {
            return $code;   // nobody has this code yet
        }
    }
    throw new RuntimeException('Could not make a unique invite code. Try again.');
}

// Clean up what the user typed: " ts kkk7 " or "tskkk7" both become "TS-KKK7".
// Returns null if it cannot be a valid invite code.
function normalize_invite_code(string $input): ?string
{
    $code = strtoupper(preg_replace('/[\s-]+/', '', $input));
    if (!preg_match('/^TS[A-Z0-9]{4}$/', $code)) {
        return null;
    }
    return 'TS-' . substr($code, 2);
}

// Find a project by its invite code. Returns the project row or null.
function find_project_by_code(PDO $pdo, string $code): ?array
{
    $stmt = $pdo->prepare('SELECT project_id, title, invite_code, leader_id FROM projects WHERE invite_code = ?');
    $stmt->execute([$code]);
    $project = $stmt->fetch();
    return $project ?: null;
}

function get_project(PDO $pdo, int $projectId): ?array
{
    $stmt = $pdo->prepare('SELECT project_id, title, invite_code, leader_id FROM projects WHERE project_id = ?');
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
    return $project ?: null;
}

// Everyone in a project (for the "Assign to" list), leader first.
function get_project_members(PDO $pdo, int $projectId): array
{
    $stmt = $pdo->prepare(
        'SELECT u.user_id, u.name
         FROM project_members pm
         JOIN users u ON u.user_id = pm.user_id
         JOIN projects p ON p.project_id = pm.project_id
         WHERE pm.project_id = ?
         ORDER BY u.user_id = p.leader_id DESC, u.name'
    );
    $stmt->execute([$projectId]);
    return $stmt->fetchAll();
}

function is_project_member(PDO $pdo, int $projectId, int $userId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?');
    $stmt->execute([$projectId, $userId]);
    return (bool) $stmt->fetch();
}

// Add a user to a project. Returns false if they were already a member.
function join_project(PDO $pdo, int $projectId, int $userId): bool
{
    if (is_project_member($pdo, $projectId, $userId)) {
        return false;
    }
    $stmt = $pdo->prepare('INSERT INTO project_members (project_id, user_id) VALUES (?, ?)');
    $stmt->execute([$projectId, $userId]);
    return true;
}

// Create a project and add the leader as its first member.
// Both inserts happen in one transaction: either both are saved or neither is.
// Returns ['project_id' => ..., 'invite_code' => ...].
function create_project(PDO $pdo, string $title, int $leaderId): array
{
    $pdo->beginTransaction();
    try {
        $code = generate_invite_code($pdo);

        $stmt = $pdo->prepare('INSERT INTO projects (title, invite_code, leader_id) VALUES (?, ?, ?)');
        $stmt->execute([$title, $code, $leaderId]);
        $projectId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('INSERT INTO project_members (project_id, user_id) VALUES (?, ?)');
        $stmt->execute([$projectId, $leaderId]);

        $pdo->commit();
        return ['project_id' => $projectId, 'invite_code' => $code];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
