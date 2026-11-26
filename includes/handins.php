<?php
// Handing a task in to Review with a link to the work (FR-31).
// Whoever moves a task to Review must give a link (a pull request, a Google Drive file, ...), so the
// leader can check the work before approving it. The link and the note are posted as a comment on the
// task, and the leader's "Waiting for your approval" list on the dashboard shows the link.

require_once __DIR__ . '/comments.php';

const HANDIN_LINK_MAX = 500;   // characters
const HANDIN_NOTE_MAX = 400;   // characters; with the link it always fits in one comment

// Check a hand-in. Returns [$link, $note, $errors]; $errors is empty when it is OK.
function validate_handin(string $link, string $note): array
{
    $link   = trim($link);
    $note   = trim(str_replace(["\r\n", "\r"], "\n", $note));
    $errors = [];

    if ($link === '') {
        $errors[] = 'Add a link to your work, for example a pull request or a Google Drive file.';
    } elseif (mb_strlen($link) > HANDIN_LINK_MAX) {
        $errors[] = 'The link can be at most ' . HANDIN_LINK_MAX . ' characters.';
    } elseif (!is_web_link($link)) {
        $errors[] = 'The link must be a web address that starts with https:// or http://.';
    }
    if (mb_strlen($note) > HANDIN_NOTE_MAX) {
        $errors[] = 'The note can be at most ' . HANDIN_NOTE_MAX . ' characters.';
    }
    return [$link, $note, $errors];
}

// The comment a hand-in posts on the task: the note (or a short default) and the link.
function handin_comment(string $link, string $note): string
{
    return ($note !== '' ? $note : 'Ready for review.') . "\nWork: " . $link;
}

// Saves the link of a hand-in. move_task() calls it, together with the move and the comment.
function add_handin(PDO $pdo, int $taskId, int $userId, string $link): void
{
    $stmt = $pdo->prepare('INSERT INTO task_handins (task_id, user_id, link, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$taskId, $userId, $link, date('Y-m-d H:i:s')]);
}

// "github.com" for https://www.github.com/..., to show where a link goes before it is opened.
function link_host(string $link): string
{
    $host = strtolower((string) parse_url($link, PHP_URL_HOST));
    return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
}
