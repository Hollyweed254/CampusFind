<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

const VERIFY_CODE_TTL = 900;      // 15 minutes
const VERIFY_MAX_ATTEMPTS = 5;
const VERIFY_RESEND_WAIT = 60;    // seconds between sends
const VERIFY_MAX_SENDS = 6;       // per rolling hour

/**
 * Creates a fresh code, stores its hash and emails it to the user.
 *
 * @return array{status: string, wait?: int, file?: ?string, error?: ?string}
 */
function verification_send(int $userId): array {
    $u = q('SELECT id, full_name, email FROM users WHERE id = ?', [$userId])->fetch();
    if (!$u) return ['status' => 'missing'];

    // Compare against database time so PHP and MySQL timezone settings can differ.
    $row = q('SELECT *, TIMESTAMPDIFF(SECOND, last_sent_at, NOW()) AS since_last,
                     TIMESTAMPDIFF(SECOND, first_sent_at, NOW()) AS since_first
              FROM email_verifications WHERE user_id = ?', [$userId])->fetch();
    $sends = 1;
    if ($row) {
        $sinceLast = (int)$row['since_last'];
        if ($sinceLast < VERIFY_RESEND_WAIT) return ['status' => 'wait', 'wait' => VERIFY_RESEND_WAIT - $sinceLast];
        if ((int)$row['since_first'] < 3600) {
            if ((int)$row['sends'] >= VERIFY_MAX_SENDS) return ['status' => 'limit'];
            $sends = (int)$row['sends'] + 1;
        }
    }

    $code = (string)random_int(100000, 999999);
    $ttl = (int)VERIFY_CODE_TTL;
    if ($row) {
        q("UPDATE email_verifications SET code_hash = ?, attempts = 0, sends = ?, last_sent_at = NOW(),
             expires_at = DATE_ADD(NOW(), INTERVAL $ttl SECOND) WHERE user_id = ?",
          [password_hash($code, PASSWORD_DEFAULT), $sends, $userId]);
    } else {
        q("INSERT INTO email_verifications (user_id, code_hash, attempts, sends, first_sent_at, last_sent_at, expires_at)
           VALUES (?,?,0,1,NOW(),NOW(),DATE_ADD(NOW(), INTERVAL $ttl SECOND))",
          [$userId, password_hash($code, PASSWORD_DEFAULT)]);
    }

    $name = htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8');
    $minutes = (int)(VERIFY_CODE_TTL / 60);
    $html = '<div style="font-family:Inter,Segoe UI,Arial,sans-serif;color:#173D2C;max-width:480px;margin:0 auto;padding:24px">'
        . '<p style="font-size:15px;margin:0 0 14px">Hi ' . $name . ',</p>'
        . '<p style="font-size:15px;line-height:1.6;margin:0">Use this code to verify your email for ' . APP_NAME . ':</p>'
        . '<p style="font-size:34px;font-weight:800;letter-spacing:10px;color:#173D2C;background:#EAF3E7;border-radius:12px;padding:16px 20px;text-align:center;margin:24px 0">' . $code . '</p>'
        . '<p style="font-size:13px;color:#66736B;line-height:1.6;margin:0">The code expires in ' . $minutes . ' minutes. If you did not create a ' . APP_NAME . ' account, you can ignore this email.</p>'
        . '</div>';
    $text = 'Hi ' . $u['full_name'] . ",\n\nYour " . APP_NAME . ' verification code is ' . $code . ".\n"
        . 'The code expires in ' . $minutes . " minutes. If you did not create an account, ignore this email.\n";

    $sent = mail_send($u['email'], $u['full_name'], 'Your ' . APP_NAME . ' verification code', $html, $text);
    if (!$sent['ok']) return ['status' => 'send', 'error' => $sent['error']];
    return ['status' => 'sent', 'file' => $sent['file']];
}

/**
 * @return array{status: string, left?: int} ok|wrong|expired|locked|missing
 */
function verification_check(int $userId, string $code): array {
    $row = q('SELECT *, (expires_at < NOW()) AS expired FROM email_verifications WHERE user_id = ?', [$userId])->fetch();
    if (!$row) return ['status' => 'missing'];
    if ((int)$row['expired'] === 1) {
        q('DELETE FROM email_verifications WHERE user_id = ?', [$userId]);
        return ['status' => 'expired'];
    }
    if ((int)$row['attempts'] >= VERIFY_MAX_ATTEMPTS) return ['status' => 'locked'];
    if (password_verify($code, $row['code_hash'])) {
        q('DELETE FROM email_verifications WHERE user_id = ?', [$userId]);
        return ['status' => 'ok'];
    }
    q('UPDATE email_verifications SET attempts = attempts + 1 WHERE user_id = ?', [$userId]);
    $left = VERIFY_MAX_ATTEMPTS - ((int)$row['attempts'] + 1);
    return ['status' => 'wrong', 'left' => max(0, $left)];
}

function verification_complete(int $userId): void {
    q('UPDATE users SET email_verified_at = NOW() WHERE id = ? AND email_verified_at IS NULL', [$userId]);
    q('DELETE FROM email_verifications WHERE user_id = ?', [$userId]);
}

/** The account waiting on this session's code, if any. */
function verification_pending_user(): ?array {
    $uid = (int)($_SESSION['pending_verify_uid'] ?? 0);
    if (!$uid) return null;
    $u = q('SELECT id, full_name, email, status, email_verified_at FROM users WHERE id = ?', [$uid])->fetch();
    if (!$u || $u['status'] !== 'active') {
        unset($_SESSION['pending_verify_uid']);
        return null;
    }
    return $u;
}

function verification_clear_pending(): void {
    unset($_SESSION['pending_verify_uid']);
}
