<?php
// Sending email through an SMTP server (Gmail), shared by reminders (FR-19) and password reset (FR-26).
// The settings live in config/db.php: $MAIL_HOST, $MAIL_PORT, $MAIL_SECURE, $MAIL_USER, $MAIL_PASS, $MAIL_FROM_NAME.

require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

// The SMTP settings, or null when email is not set up (then nothing is sent).
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

// Addresses that can never receive mail (reserved test domains, like the demo accounts).
function is_undeliverable(string $email): bool
{
    return (bool) preg_match('/\.(test|example|invalid|localhost)$/i', $email);
}

// The address of this Teamsync site, for links in emails, e.g. https://teamsync-du.fwh.is
// $SITE_URL in config/db.php wins. $depth = how many folders the running script is below the app
// (0 for index.php, 1 for api/send_reminders.php).
function site_url(int $depth = 0): string
{
    global $SITE_URL;
    if (!empty($SITE_URL)) {
        return rtrim((string) $SITE_URL, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $path = dirname($_SERVER['SCRIPT_NAME']);
    for ($i = 0; $i < $depth; $i++) {
        $path = dirname($path);
    }
    $base = rtrim(str_replace('\\', '/', $path), '/');
    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $base;
}

// Send one email. Returns true when the mail server accepted it.
function send_email(string $toEmail, string $toName, string $subject, string $html, string $text): bool
{
    $settings = mail_settings();
    if (!$settings) {
        return false;
    }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host        = $settings['host'];
        $mail->Port        = $settings['port'];
        $mail->SMTPAuth    = true;
        $mail->Username    = $settings['user'];
        $mail->Password    = $settings['pass'];
        $mail->SMTPSecure  = $settings['secure'];
        $mail->SMTPAutoTLS = $settings['secure'] !== '';
        $mail->Timeout     = 8;   // free hosting stops a PHP request after about 10 seconds
        $mail->CharSet     = 'UTF-8';
        $mail->setFrom($settings['user'], $settings['from_name']);
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $html;
        $mail->AltBody = $text;
        $mail->send();
        return true;
    } catch (MailException $ex) {
        error_log('Teamsync email to ' . $toEmail . ' failed: ' . $mail->ErrorInfo);
        return false;
    }
}
