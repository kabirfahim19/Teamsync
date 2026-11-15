<?php
// Comments on a task (FR-28). Used by task.php
//
// Who may do what:
// - Every member of the project (the leader too) can read and write comments.
// - The author of a comment and the team leader can delete it.
// - A professor can read the comments but not write or delete (read-only, like the board).

const COMMENT_MAX_LENGTH = 1000;   // characters

// Check a new comment. Returns [$cleanText, $errors]; $errors is empty when it is OK.
function validate_comment(string $text): array
{
    $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
    if ($text === '') {
        return [$text, ['Write a comment first.']];
    }
    if (mb_strlen($text) > COMMENT_MAX_LENGTH) {
        return [$text, ['A comment can be at most ' . COMMENT_MAX_LENGTH . ' characters.']];
    }
    return [$text, []];
}

// Save a comment and return its id. The time comes from PHP (Korean time), not from the database.
function add_comment(PDO $pdo, int $taskId, int $userId, string $text): int
{
    $stmt = $pdo->prepare('INSERT INTO task_comments (task_id, user_id, body, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$taskId, $userId, $text, date('Y-m-d H:i:s')]);
    return (int) $pdo->lastInsertId();
}

// All comments of a task, oldest first, with the author's name and role.
function get_comments(PDO $pdo, int $taskId): array
{
    $stmt = $pdo->prepare(
        'SELECT c.comment_id, c.task_id, c.user_id, c.body, c.created_at, u.name AS author_name, u.role AS author_role
         FROM task_comments c
         JOIN users u ON u.user_id = c.user_id
         WHERE c.task_id = ?
         ORDER BY c.created_at, c.comment_id'
    );
    $stmt->execute([$taskId]);
    return $stmt->fetchAll();
}

function get_comment(PDO $pdo, int $commentId): ?array
{
    $stmt = $pdo->prepare('SELECT comment_id, task_id, user_id, body, created_at FROM task_comments WHERE comment_id = ?');
    $stmt->execute([$commentId]);
    return $stmt->fetch() ?: null;
}

// The author or the team leader may delete a comment.
function can_delete_comment(array $comment, int $userId, int $leaderId): bool
{
    return $userId === (int) $comment['user_id'] || $userId === $leaderId;
}

function delete_comment(PDO $pdo, int $commentId): void
{
    $stmt = $pdo->prepare('DELETE FROM task_comments WHERE comment_id = ?');
    $stmt->execute([$commentId]);
}

// "Today, 4:05 PM", "Yesterday, 9:30 AM" or "Nov 12, 2:15 PM"
function comment_time(string $createdAt): string
{
    $time = new DateTime($createdAt);
    $day  = $time->format('Y-m-d');
    if ($day === date('Y-m-d')) {
        return 'Today, ' . $time->format('g:i A');
    }
    if ($day === (new DateTime('yesterday'))->format('Y-m-d')) {
        return 'Yesterday, ' . $time->format('g:i A');
    }
    return $time->format('M j, g:i A');
}
