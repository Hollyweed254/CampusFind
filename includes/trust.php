<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/** Records an administrative action. Never stores credentials or document contents. */
function audit_log(int $adminId, string $action, string $entityType, ?int $entityId = null, string $notes = ''): void {
    try {
        q('INSERT INTO admin_audit_logs (admin_id, action, entity_type, entity_id, notes) VALUES (?, ?, ?, ?, ?)',
          [$adminId, mb_substr($action, 0, 60), mb_substr($entityType, 0, 40), $entityId, $notes !== '' ? mb_substr($notes, 0, 500) : null]);
    } catch (Throwable $e) {
        error_log('CampusFind audit error: ' . $e->getMessage());
    }
}

/** Public-safe seller record. University email, registration number and evidence are excluded. */
function seller_public(int $userId): ?array {
    return q("SELECT id, full_name, phone_verified_at, phone_verified_mode, email_verified_at,
                     university_email_verified_at, student_verified_at, trusted_seller_at,
                     created_at, status
              FROM users WHERE id = ?", [$userId])->fetch() ?: null;
}

/** @return array{completed:int, active:int, rating:?float, review_count:int, open_reports:int, disputes:int} */
function seller_stats(int $userId): array {
    $completed = (int)q("SELECT COUNT(*) FROM transactions WHERE seller_id = ? AND status = 'completed'", [$userId])->fetchColumn();
    $active = (int)q("SELECT COUNT(*) FROM listings WHERE seller_id = ? AND moderation_status = 'approved' AND status = 'available'", [$userId])->fetchColumn();
    $rev = q('SELECT AVG(rating) AS avg_rating, COUNT(*) AS n FROM transaction_reviews WHERE reviewee_id = ?', [$userId])->fetch();
    $openReports = (int)q("SELECT COUNT(*) FROM user_reports WHERE reported_user_id = ? AND status = 'open'", [$userId])->fetchColumn();
    $disputes = (int)q("SELECT COUNT(*) FROM transactions WHERE seller_id = ? AND status = 'disputed'", [$userId])->fetchColumn();
    return [
        'completed' => $completed,
        'active' => $active,
        'rating' => $rev && $rev['n'] ? round((float)$rev['avg_rating'], 1) : null,
        'review_count' => $rev ? (int)$rev['n'] : 0,
        'open_reports' => $openReports,
        'disputes' => $disputes,
    ];
}

/**
 * Trusted Seller is earned, never granted at registration:
 * at least TRUSTED_MIN_COMPLETED completed transactions, at least
 * TRUSTED_MIN_RATING_COUNT reviews, average >= TRUSTED_MIN_RATING,
 * no open abuse reports and no unresolved disputes.
 */
function trusted_seller(array $user, array $stats): bool {
    if (($user['status'] ?? 'active') !== 'active') return false;
    return $stats['completed'] >= TRUSTED_MIN_COMPLETED
        && $stats['review_count'] >= TRUSTED_MIN_RATING_COUNT
        && $stats['rating'] !== null && $stats['rating'] >= TRUSTED_MIN_RATING
        && $stats['open_reports'] === 0
        && $stats['disputes'] === 0;
}

/** Persists the date the seller first earned the badge. */
function trust_refresh(int $userId): void {
    $u = seller_public($userId);
    if (!$u) return;
    if (trusted_seller($u, seller_stats($userId))) {
        q('UPDATE users SET trusted_seller_at = COALESCE(trusted_seller_at, NOW()) WHERE id = ?', [$userId]);
    }
}

/**
 * Badges representing completed, real verification steps.
 *
 * @return array<int, array{key:string, label:string, icon:string, tone:string, hint:string}>
 */
function seller_badges(array $user): array {
    $out = [];
    if (!empty($user['phone_verified_at']) && ($user['phone_verified_mode'] ?? '') === 'sms') {
        $out[] = ['key' => 'phone', 'label' => 'Phone verified', 'icon' => 'telephone-fill', 'tone' => 'ok', 'hint' => 'Phone number confirmed by SMS.'];
    }
    if (!empty($user['email_verified_at'])) {
        $out[] = ['key' => 'email', 'label' => 'Email verified', 'icon' => 'envelope-check-fill', 'tone' => 'ok', 'hint' => 'Account email confirmed.'];
    }
    if (!empty($user['student_verified_at'])) {
        $out[] = ['key' => 'student', 'label' => 'Verified student', 'icon' => 'mortarboard-fill', 'tone' => 'ok', 'hint' => 'Student status reviewed by an administrator.'];
    }
    $stats = seller_stats((int)$user['id']);
    if (trusted_seller($user, $stats)) {
        $out[] = ['key' => 'trusted', 'label' => 'Trusted seller', 'icon' => 'patch-check-fill', 'tone' => 'good', 'hint' => 'Earned through completed transactions and positive reviews.'];
    }
    return $out;
}

function badge_chips(array $badges): string {
    if (!$badges) return '';
    $html = '<span class="badge-row">';
    foreach ($badges as $b) {
        $html .= '<span class="vbadge vbadge-' . e($b['tone']) . '" title="' . e($b['hint']) . '"><i class="bi bi-' . e($b['icon']) . '"></i>' . e($b['label']) . '</span>';
    }
    return $html . '</span>';
}

function new_seller(array $stats): bool {
    return $stats['completed'] === 0;
}
