<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/otp.php';
require_once __DIR__ . '/mpesa.php';
require_once __DIR__ . '/trust.php';

function transaction_get(int $id, int $viewerId, bool $allowAdmin = false): ?array {
    $t = q("SELECT t.*, l.title, l.status AS listing_status, l.moderation_status,
                   ub.full_name AS buyer_name, us.full_name AS seller_name,
                   (SELECT li.image_path FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.id LIMIT 1) AS image_path
            FROM transactions t
            JOIN listings l ON l.id = t.listing_id
            JOIN users ub ON ub.id = t.buyer_id
            JOIN users us ON us.id = t.seller_id
            WHERE t.id = ?", [$id])->fetch();
    if (!$t) return null;
    $isParty = (int)$t['buyer_id'] === $viewerId || (int)$t['seller_id'] === $viewerId;
    if (!$isParty && !$allowAdmin) return null;
    return $t;
}

/** @return array{label:string, tone:string} */
function transaction_status_meta(string $status): array {
    $map = [
        'initiated' => ['Initiated', 'muted'],
        'awaiting_inspection' => ['Awaiting inspection', 'warn'],
        'awaiting_payment' => ['Awaiting payment', 'warn'],
        'payment_pending' => ['Payment pending', 'warn'],
        'paid' => ['Paid', 'ok'],
        'completed' => ['Completed', 'good'],
        'cancelled' => ['Cancelled', 'bad'],
        'disputed' => ['Disputed', 'bad'],
    ];
    [$label, $tone] = $map[$status] ?? [ucfirst($status), 'muted'];
    return ['label' => $label, 'tone' => $tone];
}

function transaction_status_badge(string $status): string {
    $m = transaction_status_meta($status);
    return '<span class="st st-' . e($m['tone']) . '">' . e($m['label']) . '</span>';
}

/** Creates a transaction at the inspection stage. Amount is validated server-side. */
function transaction_create(int $listingId, int $buyerId, string $amountRaw): array {
    $l = q("SELECT l.id, l.seller_id, l.status, l.moderation_status, l.price, u.status AS seller_status
            FROM listings l JOIN users u ON u.id = l.seller_id WHERE l.id = ?", [$listingId])->fetch();
    if (!$l || $l['moderation_status'] !== 'approved' || $l['seller_status'] !== 'active') {
        return ['id' => null, 'error' => 'This listing is not available.'];
    }
    if ($l['status'] !== 'available') return ['id' => null, 'error' => 'This listing is no longer available.'];
    if ((int)$l['seller_id'] === $buyerId) return ['id' => null, 'error' => 'You cannot open a transaction on your own listing.'];
    if (!is_numeric($amountRaw)) return ['id' => null, 'error' => 'Enter the agreed amount.'];

    $amount = round((float)$amountRaw, 2);
    if ($amount <= 0 || $amount > 99999999) return ['id' => null, 'error' => 'Enter a valid amount.'];

    $existing = q("SELECT id FROM transactions WHERE listing_id = ? AND buyer_id = ?
                   AND status NOT IN ('cancelled','completed') LIMIT 1", [$listingId, $buyerId])->fetch();
    if ($existing) return ['id' => null, 'error' => 'You already have an open transaction for this listing.'];

    q("INSERT INTO transactions (listing_id, buyer_id, seller_id, amount, status, payment_method)
       VALUES (?, ?, ?, ?, 'awaiting_inspection', 'direct')", [$listingId, $buyerId, (int)$l['seller_id'], $amount]);
    return ['id' => (int)db()->lastInsertId(), 'error' => null];
}

/**
 * Applies a user action with explicit authorization and state checks.
 * Nothing here treats a browser click as independently verified payment.
 *
 * @return array{ok: bool, error?: string, message?: string}
 */
function transaction_action(int $txId, int $userId, string $action, array $in = []): array {
    $t = transaction_get($txId, $userId);
    if (!$t) return ['ok' => false, 'error' => 'Transaction not found.'];
    $isBuyer = (int)$t['buyer_id'] === $userId;
    $isSeller = (int)$t['seller_id'] === $userId;
    $status = $t['status'];

    switch ($action) {
        case 'inspect':
            if (!$isBuyer) return ['ok' => false, 'error' => 'Only the buyer can confirm inspection.'];
            if ($status !== 'awaiting_inspection') return ['ok' => false, 'error' => 'This transaction is not awaiting inspection.'];
            q("UPDATE transactions SET status = 'awaiting_payment' WHERE id = ? AND status = 'awaiting_inspection'", [$txId]);
            return ['ok' => true, 'message' => 'Inspection confirmed. You can now pay the seller.'];

        case 'report_payment':
            if (!$isBuyer) return ['ok' => false, 'error' => 'Only the buyer can report a payment.'];
            if (!in_array($status, ['awaiting_payment', 'paid'], true)) return ['ok' => false, 'error' => 'This transaction is not awaiting payment.'];
            q("UPDATE transactions SET buyer_reported_paid_at = COALESCE(buyer_reported_paid_at, NOW()),
                 status = IF(status = 'awaiting_payment', 'paid', status) WHERE id = ?", [$txId]);
            return ['ok' => true, 'message' => 'Payment reported. It is marked as reported, not verified by CampusFind.'];

        case 'seller_received':
            if (!$isSeller) return ['ok' => false, 'error' => 'Only the seller can confirm receipt.'];
            if (!in_array($status, ['awaiting_payment', 'paid'], true)) return ['ok' => false, 'error' => 'This transaction is not awaiting payment.'];
            q("UPDATE transactions SET seller_reported_paid_at = COALESCE(seller_reported_paid_at, NOW()),
                 status = IF(status = 'awaiting_payment', 'paid', status) WHERE id = ?", [$txId]);
            return ['ok' => true, 'message' => 'Receipt confirmed.'];

        case 'confirm_buyer':
            if (!$isBuyer) return ['ok' => false, 'error' => 'Only the buyer can do that.'];
            if ($status !== 'paid') return ['ok' => false, 'error' => 'Payment must be recorded before completion.'];
            q('UPDATE transactions SET buyer_confirmed_at = COALESCE(buyer_confirmed_at, NOW()) WHERE id = ?', [$txId]);
            return transaction_finish_if_ready($txId);

        case 'confirm_seller':
            if (!$isSeller) return ['ok' => false, 'error' => 'Only the seller can do that.'];
            if ($status !== 'paid') return ['ok' => false, 'error' => 'Payment must be recorded before completion.'];
            q('UPDATE transactions SET seller_confirmed_at = COALESCE(seller_confirmed_at, NOW()) WHERE id = ?', [$txId]);
            return transaction_finish_if_ready($txId);

        case 'cancel':
            if (!in_array($status, ['initiated', 'awaiting_inspection', 'awaiting_payment'], true)) {
                return ['ok' => false, 'error' => 'This transaction can no longer be cancelled; open a dispute instead.'];
            }
            q("UPDATE transactions SET status = 'cancelled', cancelled_at = NOW() WHERE id = ? AND status = ?", [$txId, $status]);
            return ['ok' => true, 'message' => 'Transaction cancelled.'];

        case 'dispute':
            if (in_array($status, ['completed', 'cancelled'], true)) return ['ok' => false, 'error' => 'This transaction is already closed.'];
            $reason = trim((string)($in['reason'] ?? ''));
            if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) return ['ok' => false, 'error' => 'Describe the problem in 10-500 characters.'];
            q("UPDATE transactions SET status = 'disputed', dispute_reason = ? WHERE id = ?", [$reason, $txId]);
            return ['ok' => true, 'message' => 'Dispute sent to the administrators.'];

        case 'pay_mpesa':
            if (!$isBuyer) return ['ok' => false, 'error' => 'Only the buyer can start the payment.'];
            if ($status !== 'awaiting_payment') return ['ok' => false, 'error' => 'This transaction is not awaiting payment.'];
            if (!mpesa_configured()) return ['ok' => false, 'error' => 'M-Pesa is not configured on this server.'];
            $phone = phone_normalize((string)($in['phone'] ?? ''));
            if (!$phone) return ['ok' => false, 'error' => 'Enter a valid Safaricom number.'];
            $pending = q("SELECT id, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age FROM payments
                          WHERE transaction_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1", [$txId])->fetch();
            if ($pending && (int)$pending['age'] < 120) return ['ok' => false, 'error' => 'A payment request is already waiting on the phone.'];
            q("INSERT INTO payments (transaction_id, provider, environment, phone, amount, status)
               VALUES (?, 'mpesa', ?, ?, ?, 'initiated')",
              [$txId, MPESA_ENV === 'production' ? 'production' : 'sandbox', $phone, $t['amount']]);
            $paymentId = (int)db()->lastInsertId();
            $push = mpesa_stk_push($paymentId);
            if (!$push['ok']) return ['ok' => false, 'error' => $push['error'] ?? 'The payment request failed.'];
            return ['ok' => true, 'message' => 'Check your phone and enter the M-Pesa PIN.'];

        default:
            return ['ok' => false, 'error' => 'Unknown action.'];
    }
}

function transaction_finish_if_ready(int $txId): array {
    $t = q('SELECT * FROM transactions WHERE id = ?', [$txId])->fetch();
    if ($t && $t['buyer_confirmed_at'] && $t['seller_confirmed_at'] && $t['status'] === 'paid') {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            q("UPDATE transactions SET status = 'completed', completed_at = NOW() WHERE id = ? AND status = 'paid'", [$txId]);
            q("UPDATE listings SET status = 'sold' WHERE id = ?", [(int)$t['listing_id']]);
            $pdo->commit();
            trust_refresh((int)$t['seller_id']);
            return ['ok' => true, 'message' => 'Transaction completed. You can now leave a review.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('CampusFind transaction completion error: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not complete the transaction.'];
        }
    }
    return ['ok' => true, 'message' => 'Confirmation saved. Completion needs both sides.'];
}

/** Reviews are restricted to the two parties of a completed transaction, one per transaction. */
function transaction_review_create(int $txId, int $reviewerId, int $rating, string $comment): array {
    $t = transaction_get($txId, $reviewerId);
    if (!$t) return ['ok' => false, 'error' => 'Transaction not found.'];
    if ($t['status'] !== 'completed') return ['ok' => false, 'error' => 'Reviews open after the transaction is completed.'];
    if ($rating < 1 || $rating > 5) return ['ok' => false, 'error' => 'Choose a rating from 1 to 5.'];
    if (mb_strlen($comment) > 1000) return ['ok' => false, 'error' => 'Keep the review under 1000 characters.'];
    $reviewee = (int)$t['buyer_id'] === $reviewerId ? (int)$t['seller_id'] : (int)$t['buyer_id'];
    if ($reviewee === $reviewerId) return ['ok' => false, 'error' => 'You cannot review yourself.'];
    try {
        q('INSERT INTO transaction_reviews (transaction_id, reviewer_id, reviewee_id, rating, comment) VALUES (?, ?, ?, ?, ?)',
          [$txId, $reviewerId, $reviewee, $rating, $comment !== '' ? $comment : null]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') return ['ok' => false, 'error' => 'This transaction already has a review.'];
        throw $e;
    }
    trust_refresh($reviewee);
    return ['ok' => true, 'error' => null];
}

function transaction_review(int $txId, int $viewerId): ?array {
    $r = q('SELECT r.*, u.full_name AS reviewer_name FROM transaction_reviews r JOIN users u ON u.id = r.reviewer_id
            WHERE r.transaction_id = ? AND (r.reviewer_id = ? OR r.reviewee_id = ?)', [$txId, $viewerId, $viewerId])->fetch();
    return $r ?: null;
}

function seller_reviews(int $sellerId, int $limit = 10): array {
    return q("SELECT r.rating, r.comment, r.created_at, u.full_name AS reviewer_name, l.title
              FROM transaction_reviews r
              JOIN transactions t ON t.id = r.transaction_id
              JOIN users u ON u.id = r.reviewer_id
              JOIN listings l ON l.id = t.listing_id
              WHERE r.reviewee_id = ?
              ORDER BY r.created_at DESC LIMIT " . max(1, min(50, $limit)), [$sellerId])->fetchAll();
}

/** Human-readable lifecycle events that actually happened. */
function transaction_timeline(array $t): array {
    $out = [['Created', $t['created_at']]];
    if (!empty($t['buyer_reported_paid_at'])) $out[] = ['Buyer reported payment', $t['buyer_reported_paid_at']];
    if (!empty($t['seller_reported_paid_at'])) $out[] = ['Seller confirmed receipt', $t['seller_reported_paid_at']];
    if (!empty($t['payment_verified_at'])) $out[] = ['Payment verified by M-Pesa', $t['payment_verified_at']];
    if (!empty($t['buyer_confirmed_at'])) $out[] = ['Buyer confirmed completion', $t['buyer_confirmed_at']];
    if (!empty($t['seller_confirmed_at'])) $out[] = ['Seller confirmed completion', $t['seller_confirmed_at']];
    if (!empty($t['completed_at'])) $out[] = ['Completed', $t['completed_at']];
    if (!empty($t['cancelled_at'])) $out[] = ['Cancelled', $t['cancelled_at']];
    if (!empty($t['dispute_reason'])) $out[] = ['Dispute opened', $t['updated_at']];
    return $out;
}
