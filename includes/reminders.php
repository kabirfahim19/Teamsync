<?php
// Email reminders before a task's due date (FR-19).
//
// Free hosting has no scheduled jobs (cron), so there is no clock that sends the emails.
// Instead, the dashboard calls api/send_reminders.php in the background each time someone opens it.
// Each call sends at most a few emails: tasks due today or tomorrow that are not done and got no reminder yet.
// A task is "claimed" (reminder_sent_at is set) before its email goes out, so it is never sent twice.

require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

// The SMTP settings from config/db.php, or null when email is not set up (then reminders are off).
function mail_settings(): ?array
{
    global $MAIL_HOST, $MAIL_PORT, $MAIL_SECURE, $MAIL_USER, $MAIL_PASS, $MAIL_FROM_NAME;

    if (empty($MAIL_HOST) || empty($MAIL_USER) || empty($MAIL_PASS)) {
        return null;
    }
    return [
        'host'      => (string) $MAIL_HOST,
        'port'      => (int) ($MAIL_PORT ?? 587),
        'secure'    => (string) ($MAIL_SECURE ?? 'tls'),   // 'tls' (port 587), 'ssl' (port 465) or '' (no encryption)
        'user'      => (string) $MAIL_USER,
        'pass'      => (string) $MAIL_PASS,
        'from_name' => (string) ($MAIL_FROM_NAME ?? 'Teamsync'),
    ];
}

// Unfinished tasks due today or tomorrow that still need a reminder, with the assignee's name and email.
function get_reminders_due(PDO $pdo, int $limit): array
{
    $today    = (new DateTime('today'))->format('Y-m-d');
    $tomorrow = (new DateTime('tomorrow'))->format('Y-m-d');
    $stmt = $pdo->prepare(
        "SELECT t.task_id, t.title, t.due_date, t.priority, p.project_id, p.title AS project_title,
                u.name AS assignee_name, u.email AS assignee_email
         FROM tasks t
         JOIN projects p ON p.project_id = t.project_id
         JOIN users u ON u.user_id = t.assignee_id
         JOIN project_members pm ON pm.project_id = t.project_id AND pm.user_id = t.assignee_id
         WHERE t.status <> 'done' AND t.reminder_sent_at IS NULL
           AND t.due_date IS NOT NULL AND t.due_date BETWEEN ? AND ?
         ORDER BY t.due_date, t.task_id
         LIMIT " . max(1, $limit)
    );
    $stmt->execute([$today, $tomorrow]);
    return $stmt->fetchAll();
}

// Mark the task as reminded. Returns false if another request already did, so only one email goes out.
function claim_reminder(PDO $pdo, int $taskId): bool
{
    $stmt = $pdo->prepare('UPDATE tasks SET reminder_sent_at = ? WHERE task_id = ? AND reminder_sent_at IS NULL');
    $stmt->execute([date('Y-m-d H:i:s'), $taskId]);
    return $stmt->rowCount() === 1;
}

// Undo the claim when the email could not be sent, so the next call tries again.
function release_reminder(PDO $pdo, int $taskId): void
{
    $stmt = $pdo->prepare('UPDATE tasks SET reminder_sent_at = NULL WHERE task_id = ?');
    $stmt->execute([$taskId]);
}

// Addresses that can never receive mail (reserved test domains, like the demo accounts).
function is_undeliverable(string $email): bool
{
    return (bool) preg_match('/\.(test|example|invalid|localhost)$/i', $email);
}

// Subject, HTML body and plain-text body of one reminder.
function reminder_email(array $task, string $siteUrl): array
{
    $when     = strtolower(due_label($task['due_date']));          // "due today" / "due tomorrow"
    $date     = (new DateTime($task['due_date']))->format('l, M j');
    $priority = TASK_PRIORITIES[$task['priority']] ?? $task['priority'];
    $link     = rtrim($siteUrl, '/') . '/board.php?project=' . (int) $task['project_id'];

    $subject = 'Reminder: "' . $task['title'] . '" is ' . $when;
    $text = "Hello {$task['assignee_name']},\n\n"
          . "Your task \"{$task['title']}\" in the project \"{$task['project_title']}\" is {$when} ({$date}).\n"
          . "Priority: {$priority}\n\n"
          . "Open the board: {$link}\n\n"
          . "You get this email once per task. Move the task to Done when it is finished.\n"
          . "- Teamsync";
    $html = '<p>Hello ' . e($task['assignee_name']) . ',</p>'
          . '<p>Your task <b>' . e($task['title']) . '</b> in the project <b>' . e($task['project_title']) . '</b> is '
          . e($when) . ' (' . e($date) . ').<br>Priority: ' . e($priority) . '</p>'
          . '<p><a href="' . e($link) . '">Open the board</a></p>'
          . '<p style="color:#4A5468">You get this email once per task. Move the task to Done when it is finished.<br>Teamsync</p>';

    return [$subject, $html, $text];
}

// Send up to $limit reminders. Returns ['enabled' => bool, 'sent' => n, 'skipped' => n, 'failed' => n].
function send_due_reminders(PDO $pdo, string $siteUrl, int $limit = 5): array
{
    $result   = ['enabled' => false, 'sent' => 0, 'skipped' => 0, 'failed' => 0];
    $settings = mail_settings();
    if (!$settings) {
        return $result;   // email not set up: reminders are off, nothing else changes
    }
    $result['enabled'] = true;

    foreach (get_reminders_due($pdo, $limit) as $task) {
        if (!claim_reminder($pdo, (int) $task['task_id'])) {
            continue;   // another request is sending this one
        }
        if (is_undeliverable($task['assignee_email'])) {
            $result['skipped']++;
            continue;   // demo accounts: mark as done, send nothing
        }

        [$subject, $html, $text] = reminder_email($task, $siteUrl);
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $settings['host'];
            $mail->Port       = $settings['port'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $settings['user'];
            $mail->Password   = $settings['pass'];
            $mail->SMTPSecure = $settings['secure'];
            $mail->SMTPAutoTLS = $settings['secure'] !== '';
            $mail->Timeout    = 8;   // free hosting stops a PHP request after about 10 seconds
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom($settings['user'], $settings['from_name']);
            $mail->addAddress($task['assignee_email'], $task['assignee_name']);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body    = $html;
            $mail->AltBody = $text;
            $mail->send();
            $result['sent']++;
        } catch (MailException $ex) {
            release_reminder($pdo, (int) $task['task_id']);
            $result['failed']++;
            error_log('Teamsync reminder for task ' . $task['task_id'] . ' failed: ' . $mail->ErrorInfo);
            break;   // the mail server is not answering: stop and try again on the next visit
        }
    }
    return $result;
}
