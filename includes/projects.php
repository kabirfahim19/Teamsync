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
