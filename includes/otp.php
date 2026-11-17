<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

/** Normalize a Kenyan mobile number to +2547XXXXXXXX / +2541XXXXXXXX. */
function phone_normalize(?string $raw): ?string {
    $digits = preg_replace('/\D+/', '', (string)$raw);
    if (str_starts_with($digits, '254')) $digits = substr($digits, 3);
    elseif (str_starts_with($digits, '0')) $digits = substr($digits, 1);
    if (strlen($digits) !== 9 || !preg_match('/^[17]\d{8}$/', $digits)) return null;
    return '+254' . $digits;
}

function phone_display(string $phone): string {
    $d = preg_replace('/\D+/', '', $phone);
    if (strlen($d) === 12) return '+' . substr($d, 0, 3) . ' ' . substr($d, 3, 3) . ' ' . substr($d, 6, 3) . ' ' . substr($d, 9);
    return $phone;
}

/** True when the address belongs to a university domain the app accepts. */
function university_email_allowed(string $email): bool {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
    if ($domain === '') return false;
    if (UNIVERSITY_EMAIL_DOMAINS) {
        foreach (UNIVERSITY_EMAIL_DOMAINS as $allowed) {
            if ($domain === $allowed || str_ends_with($domain, '.' . $allowed)) return true;
        }
        return false;
    }
    return str_ends_with($domain, UNIVERSITY_EMAIL_SUFFIX);
}

/** True when SMS can actually reach a handset. */
function sms_configured(): bool {
    return in_array(SMS_PROVIDER, ['africastalking', 'at'], true)
        && SMS_USERNAME !== '' && SMS_API_KEY !== '';
}

/** True only when a live provider can deliver to a real handset. */
function sms_live(): bool {
    return sms_configured() && SMS_ENV === 'production';
}

/**
 * Sends an SMS through Africa's Talking. Without a provider this writes the
 * message to storage/sms and reports mode=dev; that must never be presented
 * as real delivery.
 *
 * @return array{ok: bool, mode: string, error: ?string}
 */
function sms_send(string $phone, string $message): array {
    if (SMS_PROVIDER === '') {
        $dir = dirname(__DIR__) . '/storage/sms';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'mode' => 'dev', 'error' => 'storage/sms unavailable'];
        }
        if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n");
        if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
        $file = $dir . '/sms-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.txt';
        $body = "SIMULATED SMS (no provider configured)\r\nTo: $phone\r\n\r\n$message\r\n";
        if (@file_put_contents($file, $body) === false) {
            return ['ok' => false, 'mode' => 'dev', 'error' => 'could not store simulated SMS'];
        }
        return ['ok' => true, 'mode' => 'dev', 'error' => null];
    }

    if (!sms_configured()) {
        error_log('CampusFind SMS error: provider is set but username or API key is missing');
        return ['ok' => false, 'mode' => 'config', 'error' => 'SMS provider is not fully configured'];
    }
    if (!function_exists('curl_init')) {
        error_log('CampusFind SMS error: PHP cURL extension is not enabled');
        return ['ok' => false, 'mode' => 'sms', 'error' => 'SMS transport unavailable'];
    }

    $payload = [
        'username' => SMS_USERNAME,
        'to' => $phone,
        'message' => $message,
    ];
    if (SMS_SENDER !== '') $payload['from'] = SMS_SENDER;

    $ch = curl_init(SMS_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'apiKey: ' . SMS_API_KEY,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($response === false || $status < 200 || $status >= 300) {
        error_log('CampusFind SMS error: HTTP ' . $status . ' ' . ($err ?: substr((string)$response, 0, 300)));
        return ['ok' => false, 'mode' => 'sms', 'error' => 'SMS gateway rejected the message'];
    }
    $json = json_decode((string)$response, true);
    $recipient = is_array($json) ? ($json['SMSMessageData']['Recipients'][0] ?? null) : null;
    if (!is_array($recipient) || ($recipient['status'] ?? '') !== 'Success') {
        error_log('CampusFind SMS error: ' . (is_array($recipient)
            ? (string)($recipient['status'] ?? 'missing status')
            : 'unexpected gateway response'));
        return ['ok' => false, 'mode' => 'sms', 'error' => 'SMS could not be delivered'];
    }
    return ['ok' => true, 'mode' => sms_live() ? 'sms' : 'sandbox', 'error' => null];
}

/**
 * Creates or refreshes a one-time code. Rate limits use database time so
 * PHP/MySQL clock differences cannot be exploited.
 *
 * @return array{status: string, wait?: int, mode?: string, error?: ?string}
 */
function otp_issue(int $userId, string $purpose, string $destination): array {
    if (!in_array($purpose, ['phone', 'university_email'], true)) return ['status' => 'invalid'];
    $row = q('SELECT *, TIMESTAMPDIFF(SECOND, last_sent_at, NOW()) AS since_last,
                     TIMESTAMPDIFF(SECOND, first_sent_at, NOW()) AS since_first
              FROM verification_otps WHERE user_id = ? AND purpose = ?', [$userId, $purpose])->fetch();
    if ($row) {
        $wait = OTP_RESEND_WAIT - (int)$row['since_last'];
        if ($wait > 0) return ['status' => 'cooldown', 'wait' => $wait];
        $sends = (int)$row['since_first'] < 3600 ? (int)$row['sends'] : 0;
        if ($sends >= OTP_MAX_SENDS) {
            return ['status' => 'limit', 'wait' => max(1, 3600 - (int)$row['since_first'])];
        }
    }

    $code = (string)random_int(100000, 999999);
    if ($row) {
        $sends = (int)$row['since_first'] < 3600 ? (int)$row['sends'] + 1 : 1;
        $first = (int)$row['since_first'] < 3600 ? 'first_sent_at' : 'NOW()';
        q("UPDATE verification_otps SET destination = ?, code_hash = ?, attempts = 0, sends = ?,
             first_sent_at = $first, last_sent_at = NOW(),
             expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
           WHERE user_id = ? AND purpose = ?",
          [$destination, password_hash($code, PASSWORD_DEFAULT), $sends, OTP_TTL, $userId, $purpose]);
    } else {
        q('INSERT INTO verification_otps
             (user_id, purpose, destination, code_hash, sends, first_sent_at, last_sent_at, expires_at)
           VALUES (?, ?, ?, ?, 1, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))',
          [$userId, $purpose, $destination, password_hash($code, PASSWORD_DEFAULT), OTP_TTL]);
    }

    if ($purpose === 'phone') {
        $sent = sms_send($destination, 'Your ' . APP_NAME . ' verification code is ' . $code . '. It expires in ' . (int)(OTP_TTL / 60) . ' minutes.');
    } else {
        $html = '<div style="font-family:Inter,Segoe UI,Arial,sans-serif;color:#173D2C;max-width:480px;margin:0 auto;padding:24px">'
              . '<p style="font-size:15px;line-height:1.6;margin:0">Use this code to verify your university email for ' . APP_NAME . ':</p>'
              . '<p style="font-size:34px;font-weight:800;letter-spacing:10px;color:#173D2C;background:#EAF3E7;border-radius:12px;padding:16px 20px;text-align:center;margin:24px 0">' . $code . '</p>'
              . '<p style="font-size:13px;color:#66736B;line-height:1.6;margin:0">The code expires in ' . (int)(OTP_TTL / 60) . ' minutes. If you did not request it, ignore this email.</p></div>';
        $text = 'Your ' . APP_NAME . ' verification code is ' . $code . '. It expires in ' . (int)(OTP_TTL / 60) . " minutes.\n";
        $sent = mail_send($destination, '', 'Verify your university email', $html, $text);
        $sent['mode'] = 'email';
    }
    if (empty($sent['ok'])) {
        return ['status' => 'send_failed', 'mode' => $sent['mode'] ?? '', 'error' => $sent['error'] ?? 'send failed'];
    }
    return ['status' => 'sent', 'mode' => $sent['mode'] ?? ''];
}

/**
 * Checks a code and, on success, records the verification with database time.
 *
 * @return array{status: string, left?: int, wait?: int}
 */
function otp_verify(int $userId, string $purpose, string $code, string $destination): array {
    if (!preg_match('/^\d{6}$/', $code)) return ['status' => 'wrong'];
    $row = q('SELECT *, (expires_at < NOW()) AS expired FROM verification_otps
              WHERE user_id = ? AND purpose = ?', [$userId, $purpose])->fetch();
    if (!$row || $row['destination'] !== $destination) return ['status' => 'missing'];
    if ((int)$row['expired'] === 1) return ['status' => 'expired'];
    if ((int)$row['attempts'] >= OTP_MAX_ATTEMPTS) return ['status' => 'locked'];
    if (!password_verify($code, $row['code_hash'])) {
        q('UPDATE verification_otps SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
        $left = max(0, OTP_MAX_ATTEMPTS - ((int)$row['attempts'] + 1));
        return ['status' => 'wrong', 'left' => $left];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($purpose === 'phone') {
            $dup = q('SELECT id FROM users WHERE phone = ? AND phone_verified_at IS NOT NULL AND id <> ?', [$destination, $userId])->fetch();
            if ($dup) { $pdo->rollBack(); return ['status' => 'duplicate']; }
            q("UPDATE users SET phone = ?, phone_verified_at = NOW(), phone_verified_mode = ? WHERE id = ?",
              [$destination, sms_live() ? 'sms' : 'dev', $userId]);
        } else {
            $dup = q('SELECT id FROM users WHERE university_email = ? AND id <> ?', [$destination, $userId])->fetch();
            if ($dup) { $pdo->rollBack(); return ['status' => 'duplicate']; }
            q('UPDATE users SET university_email = ?, university_email_verified_at = NOW() WHERE id = ?',
              [$destination, $userId]);
        }
        q('DELETE FROM verification_otps WHERE id = ?', [$row['id']]);
        $pdo->commit();
        return ['status' => 'ok'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('CampusFind OTP consume error: ' . $e->getMessage());
        return ['status' => 'error'];
    }
}

/** Latest OTP row (destination/expiry) for status display. */
function otp_current(int $userId, string $purpose): ?array {
    $row = q('SELECT destination, sent_at, expires_at, attempts,
                     TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS seconds_left
              FROM (SELECT destination, last_sent_at AS sent_at, expires_at, attempts
                    FROM verification_otps WHERE user_id = ? AND purpose = ?) v', [$userId, $purpose])->fetch();
    return $row ?: null;
}

function student_verification_for(int $userId): ?array {
    return q('SELECT * FROM student_verifications WHERE user_id = ?', [$userId])->fetch() ?: null;
}
