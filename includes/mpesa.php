<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

/** M-Pesa is only usable with a complete merchant configuration. */
function mpesa_configured(): bool {
    return MPESA_CONSUMER_KEY !== '' && MPESA_CONSUMER_SECRET !== ''
        && MPESA_SHORTCODE !== '' && MPESA_PASSKEY !== '' && MPESA_CALLBACK_TOKEN !== '';
}

function mpesa_base_url(): string {
    return MPESA_ENV === 'production' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
}

function mpesa_callback_url(): string {
    return absolute_url('api/mpesa-callback.php') . '?token=' . rawurlencode(MPESA_CALLBACK_TOKEN);
}

/** @return array{token:?string, error:?string} */
function mpesa_token(): array {
    $ch = curl_init(mpesa_base_url() . '/oauth/v1/generate?grant_type=client_credentials');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERPWD => MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false || $status !== 200) {
        error_log('CampusFind M-Pesa token error: HTTP ' . $status . ' ' . ($err ?: ''));
        return ['token' => null, 'error' => 'M-Pesa authentication failed.'];
    }
    $json = json_decode((string)$raw, true);
    if (empty($json['access_token'])) return ['token' => null, 'error' => 'M-Pesa authentication failed.'];
    return ['token' => (string)$json['access_token'], 'error' => null];
}

function mpesa_timestamp(): string {
    $d = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
    return $d->format('YmdHis');
}

/** Sends an STK Push for an existing pending payment row. */
function mpesa_stk_push(int $paymentId): array {
    if (!mpesa_configured()) return ['ok' => false, 'error' => 'M-Pesa is not configured on this server.'];
    $p = q("SELECT p.*, t.status AS tx_status FROM payments p JOIN transactions t ON t.id = p.transaction_id
            WHERE p.id = ? AND p.status IN ('initiated','failed','timeout')", [$paymentId])->fetch();
    if (!$p) return ['ok' => false, 'error' => 'Payment not found.'];
    if (!in_array($p['tx_status'], ['awaiting_payment'], true)) return ['ok' => false, 'error' => 'This transaction is not awaiting payment.'];

    $amount = (float)$p['amount'];
    if (abs($amount - round($amount)) > 0.001) return ['ok' => false, 'error' => 'M-Pesa only accepts whole-shilling amounts. Adjust the agreed price.'];

    $auth = mpesa_token();
    if (!$auth['token']) return ['ok' => false, 'error' => $auth['error']];

    $ts = mpesa_timestamp();
    $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $ts);
    $payload = [
        'BusinessShortCode' => MPESA_SHORTCODE,
        'Password' => $password,
        'Timestamp' => $ts,
        'TransactionType' => MPESA_TRANSACTION_TYPE,
        'Amount' => (int)round($amount),
        'PartyA' => preg_replace('/^\+/', '', $p['phone']),
        'PartyB' => MPESA_SHORTCODE,
        'PhoneNumber' => preg_replace('/^\+/', '', $p['phone']),
        'CallBackURL' => mpesa_callback_url(),
        'AccountReference' => 'CF-TX' . (int)$p['transaction_id'],
        'TransactionDesc' => 'CampusFind transaction ' . (int)$p['transaction_id'],
    ];
    $ch = curl_init(mpesa_base_url() . '/mpesa/stkpush/v1/processrequest');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $auth['token'],
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $json = json_decode((string)$raw, true) ?: [];

    if ($raw === false || $status !== 200 || (string)($json['ResponseCode'] ?? '') !== '0') {
        $desc = (string)($json['errorMessage'] ?? $json['ResponseDescription'] ?? ($err ?: 'STK push was rejected.'));
        q('UPDATE payments SET status = ?, result_code = ?, result_desc = ? WHERE id = ?',
          ['failed', (string)($json['errorCode'] ?? ''), mb_substr($desc, 0, 255), $paymentId]);
        error_log('CampusFind M-Pesa STK error: ' . $status . ' ' . $desc);
        return ['ok' => false, 'error' => $desc];
    }

    q("UPDATE payments SET status = 'pending', merchant_request_id = ?, checkout_request_id = ?, result_desc = NULL WHERE id = ?",
      [(string)($json['MerchantRequestID'] ?? ''), (string)($json['CheckoutRequestID'] ?? ''), $paymentId]);
    q("UPDATE transactions SET status = 'payment_pending', payment_method = 'mpesa' WHERE id = ? AND status = 'awaiting_payment'",
      [(int)$p['transaction_id']]);
    return ['ok' => true, 'checkout' => (string)($json['CheckoutRequestID'] ?? '')];
}

/**
 * Asks Daraja for the status of a pending STK payment and applies the result.
 * Used by the payment page and admin reconciliation.
 */
function mpesa_query_status(int $paymentId): array {
    if (!mpesa_configured()) return ['ok' => false, 'error' => 'M-Pesa is not configured on this server.'];
    $p = q('SELECT * FROM payments WHERE id = ?', [$paymentId])->fetch();
    if (!$p || empty($p['checkout_request_id'])) return ['ok' => false, 'error' => 'No STK request to check.'];
    if ($p['status'] !== 'pending') return ['ok' => true, 'status' => $p['status']];
    $auth = mpesa_token();
    if (!$auth['token']) return ['ok' => false, 'error' => $auth['error']];
    $ts = mpesa_timestamp();
    $payload = [
        'BusinessShortCode' => MPESA_SHORTCODE,
        'Password' => base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $ts),
        'Timestamp' => $ts,
        'CheckoutRequestID' => $p['checkout_request_id'],
    ];
    $ch = curl_init(mpesa_base_url() . '/mpesa/stkpushquery/v1/query');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $auth['token'],
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false || $status !== 200) {
        error_log('CampusFind M-Pesa query error: HTTP ' . $status . ' ' . ($err ?: ''));
        return ['ok' => false, 'error' => 'Could not reach M-Pesa.'];
    }
    $json = json_decode((string)$raw, true) ?: [];
    $code = (string)($json['ResultCode'] ?? '');
    if ($code === '0') {
        q("UPDATE payments SET status = 'paid', result_code = ?, result_desc = ? WHERE id = ? AND status = 'pending'",
          [$code, mb_substr((string)($json['ResultDesc'] ?? ''), 0, 255), $paymentId]);
        mpesa_apply_paid($paymentId);
        return ['ok' => true, 'status' => 'paid'];
    }
    if ($code === '1032') {
        q("UPDATE payments SET status = 'cancelled', result_code = ?, result_desc = ? WHERE id = ? AND status = 'pending'",
          [$code, 'Cancelled by the customer.', $paymentId]);
        mpesa_release_transaction($paymentId);
        return ['ok' => true, 'status' => 'cancelled'];
    }
    if ($code === '1037') {
        q("UPDATE payments SET status = 'timeout', result_code = ?, result_desc = ? WHERE id = ? AND status = 'pending'",
          [$code, 'No response from the customer.', $paymentId]);
        mpesa_release_transaction($paymentId);
        return ['ok' => true, 'status' => 'timeout'];
    }
    if ($code !== '') {
        q("UPDATE payments SET status = 'failed', result_code = ?, result_desc = ? WHERE id = ? AND status = 'pending'",
          [$code, mb_substr((string)($json['ResultDesc'] ?? ''), 0, 255), $paymentId]);
        mpesa_release_transaction($paymentId);
        return ['ok' => true, 'status' => 'failed'];
    }
    return ['ok' => true, 'status' => 'pending'];
}

function mpesa_apply_paid(int $paymentId): void {
    q("UPDATE transactions t JOIN payments p ON p.transaction_id = t.id
       SET t.status = 'paid', t.payment_verified_at = NOW(), t.payment_method = 'mpesa'
       WHERE p.id = ? AND t.status IN ('payment_pending','awaiting_payment')", [$paymentId]);
}

function mpesa_release_transaction(int $paymentId): void {
    q("UPDATE transactions t JOIN payments p ON p.transaction_id = t.id
       SET t.status = 'awaiting_payment'
       WHERE p.id = ? AND t.status = 'payment_pending'", [$paymentId]);
}

/**
 * Validates and applies a Daraja callback. The amount always comes from the
 * stored payment row, never from the callback body. Duplicate callbacks are
 * recorded once thanks to the unique event key.
 *
 * @return array{ok: bool, status: string, error?: string}
 */
function mpesa_handle_callback(array $payload, string $token): array {
    if (!mpesa_configured() || $token === '' || !hash_equals(MPESA_CALLBACK_TOKEN, $token)) {
        return ['ok' => false, 'status' => 'rejected', 'error' => 'Invalid callback token.'];
    }
    $cb = $payload['Body']['stkCallback'] ?? null;
    if (!is_array($cb) || empty($cb['CheckoutRequestID'])) {
        return ['ok' => false, 'status' => 'rejected', 'error' => 'Malformed callback.'];
    }
    $checkout = (string)$cb['CheckoutRequestID'];
    $payment = q('SELECT * FROM payments WHERE checkout_request_id = ?', [$checkout])->fetch();
    if (!$payment) return ['ok' => false, 'status' => 'rejected', 'error' => 'Unknown checkout request.'];

    $resultCode = (string)($cb['ResultCode'] ?? '');
    $receipt = null;
    $paidAmount = null;
    foreach (($cb['CallbackMetadata']['Item'] ?? []) as $item) {
        if (($item['Name'] ?? '') === 'MpesaReceiptNumber') $receipt = (string)($item['Value'] ?? '');
        if (($item['Name'] ?? '') === 'Amount') $paidAmount = (float)($item['Value'] ?? 0);
    }
    $eventKey = 'cb:' . $checkout . ':' . $resultCode . ':' . ($receipt ?? '');
    try {
        q('INSERT INTO payment_events (payment_id, event_key, event_type, payload) VALUES (?, ?, ?, ?)',
          [$payment['id'], $eventKey, 'callback', json_encode($payload, JSON_UNESCAPED_SLASHES)]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') return ['ok' => true, 'status' => 'duplicate'];
        throw $e;
    }

    if ($resultCode === '0') {
        if ($paidAmount === null || abs($paidAmount - (float)$payment['amount']) > 0.01) {
            q("UPDATE payments SET status = 'failed', result_code = 'amount_mismatch',
                 result_desc = 'Callback amount did not match the recorded amount.', raw_callback = ? WHERE id = ?",
              [json_encode($payload, JSON_UNESCAPED_SLASHES), $payment['id']]);
            mpesa_release_transaction((int)$payment['id']);
            error_log('CampusFind M-Pesa amount mismatch for payment ' . $payment['id']);
            return ['ok' => true, 'status' => 'amount_mismatch'];
        }
        q("UPDATE payments SET status = 'paid', mpesa_receipt = ?, result_code = '0',
             result_desc = ?, raw_callback = ? WHERE id = ? AND status <> 'paid'",
          [mb_substr((string)$receipt, 0, 40), mb_substr((string)($cb['ResultDesc'] ?? ''), 0, 255),
           json_encode($payload, JSON_UNESCAPED_SLASHES), $payment['id']]);
        mpesa_apply_paid((int)$payment['id']);
        return ['ok' => true, 'status' => 'paid'];
    }

    $state = $resultCode === '1032' ? 'cancelled' : ($resultCode === '1037' ? 'timeout' : 'failed');
    q('UPDATE payments SET status = ?, result_code = ?, result_desc = ?, raw_callback = ? WHERE id = ? AND status = ?',
      [$state, mb_substr($resultCode, 0, 10), mb_substr((string)($cb['ResultDesc'] ?? ''), 0, 255),
       json_encode($payload, JSON_UNESCAPED_SLASHES), $payment['id'], 'pending']);
    mpesa_release_transaction((int)$payment['id']);
    return ['ok' => true, 'status' => $state];
}
