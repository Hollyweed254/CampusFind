<?php
require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

function mail_configured(): bool {
    return SMTP_HOST !== '' && SMTP_USER !== '' && SMTP_PASS !== '';
}

/** True when browsing the app locally, where the file transport is expected. */
function mail_is_local(): bool {
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $host = preg_replace('/:\d+$/', '', trim($host, '[]'));
    return $host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

/**
 * Sends an HTML email. Falls back to a .eml file in storage/mail when SMTP
 * credentials are not configured, which keeps local testing working on XAMPP.
 *
 * @return array{ok: bool, file: ?string, error: ?string}
 */
function mail_send(string $toEmail, string $toName, string $subject, string $html, string $text = ''): array {
    if (!mail_configured()) {
        if (!mail_is_local()) {
            error_log('CampusFind mail error: SMTP is not configured for this host.');
            return ['ok' => false, 'file' => null, 'error' => 'SMTP not configured'];
        }
        return mail_save_file($toEmail, $toName, $subject, $html, $text);
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->Timeout = 15;
        if (SMTP_SECURE === 'ssl') $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        elseif (SMTP_SECURE === 'tls') $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $text !== '' ? $text : trim(strip_tags(str_replace(['<br>', '</p>', '</div>'], "\n", $html)));
        $mail->send();
        return ['ok' => true, 'file' => null, 'error' => null];
    } catch (Throwable $e) {
        error_log('CampusFind mail error: ' . $e->getMessage());
        return ['ok' => false, 'file' => null, 'error' => $e->getMessage()];
    }
}

/** @return array{ok: bool, file: ?string, error: ?string} */
function mail_save_file(string $toEmail, string $toName, string $subject, string $html, string $text = ''): array {
    $dir = dirname(__DIR__) . '/storage/mail';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        error_log('CampusFind mail error: cannot create storage/mail');
        return ['ok' => false, 'file' => null, 'error' => 'log directory unavailable'];
    }
    if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');

    $enc = static fn(string $s): string => '=?UTF-8?B?' . base64_encode($s) . '?=';
    $boundary = bin2hex(random_bytes(12));
    $host = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), PHP_URL_HOST) ?: 'localhost';
    $headers = [
        'Date: ' . date('r'),
        'From: ' . $enc(SMTP_FROM_NAME) . ' <' . SMTP_FROM . '>',
        'To: ' . ($toName !== '' ? $enc($toName) . ' ' : '') . '<' . $toEmail . '>',
        'Subject: ' . $enc($subject),
        'X-CampusFind-Transport: local-file',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . $host . '>',
    ];
    $text = $text !== '' ? $text : trim(strip_tags($html));
    $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($text), 76, "\r\n")
          . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($html), 76, "\r\n")
          . "--$boundary--\r\n";
    $base = 'mail-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    $file = $base . '.eml';
    if (@file_put_contents($dir . '/' . $file, implode("\r\n", $headers) . "\r\n\r\n" . $body) === false) {
        error_log('CampusFind mail error: cannot write ' . $file);
        return ['ok' => false, 'file' => null, 'error' => 'could not store message'];
    }
    @file_put_contents($dir . '/' . $base . '.txt',
        'To: ' . $toEmail . "\r\nSubject: " . $subject . "\r\n\r\n" . $text);
    error_log('CampusFind mail not configured; message saved to storage/mail/' . $file);
    return ['ok' => true, 'file' => $file, 'error' => null];
}
