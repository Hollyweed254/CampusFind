<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

function chat_is_participant(array $conv, int $userId): bool {
    return (int)$conv['buyer_id'] === $userId || (int)$conv['seller_id'] === $userId;
}

/** Loads a conversation for a participant (admins may inspect when authorized). */
function chat_get(int $id, int $userId, bool $allowAdmin = false): ?array {
    $c = q("SELECT c.*, l.title, l.price, l.status AS listing_status, l.moderation_status,
                   cat.listing_type,
                   ub.full_name AS buyer_name, ub.status AS buyer_status,
                   us.full_name AS seller_name, us.id AS seller_user_id, us.status AS seller_status,
                   (SELECT li.image_path FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.id LIMIT 1) AS image_path
            FROM conversations c
            JOIN listings l ON l.id = c.listing_id
            JOIN categories cat ON cat.id = l.category_id
            JOIN users ub ON ub.id = c.buyer_id
            JOIN users us ON us.id = c.seller_id
            WHERE c.id = ?", [$id])->fetch();
    if (!$c) return null;
    if (!chat_is_participant($c, $userId) && !$allowAdmin) return null;
    return $c;
}

function chat_blocked(int $a, int $b): bool {
    return (bool)q('SELECT 1 FROM blocked_users
                    WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?) LIMIT 1',
                   [$a, $b, $b, $a])->fetchColumn();
}

function chat_block(int $blocker, int $blocked): void {
    if ($blocker === $blocked) return;
    q('INSERT IGNORE INTO blocked_users (blocker_id, blocked_id) VALUES (?, ?)', [$blocker, $blocked]);
}

function chat_unblock(int $blocker, int $blocked): void {
    q('DELETE FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?', [$blocker, $blocked]);
}

/** Opens (or reuses) the single conversation for a listing and buyer. */
function chat_start(int $listingId, int $buyerId): array {
    $l = q("SELECT l.id, l.seller_id, l.status, l.moderation_status, u.status AS seller_status
            FROM listings l JOIN users u ON u.id = l.seller_id WHERE l.id = ?", [$listingId])->fetch();
    if (!$l || $l['moderation_status'] !== 'approved' || $l['seller_status'] !== 'active') {
        return ['id' => null, 'error' => 'This listing is not available.'];
    }
    if ((int)$l['seller_id'] === $buyerId) return ['id' => null, 'error' => 'You cannot message yourself.'];
    if ($l['status'] !== 'available') return ['id' => null, 'error' => 'This listing is no longer available.'];
    if (chat_blocked($buyerId, (int)$l['seller_id'])) return ['id' => null, 'error' => 'Messaging is blocked for this user.'];
    q('INSERT INTO conversations (listing_id, buyer_id, seller_id) VALUES (?, ?, ?)
       ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)', [$listingId, $buyerId, (int)$l['seller_id']]);
    return ['id' => (int)db()->lastInsertId(), 'error' => null];
}

function chat_unread_count(int $userId): int {
    return (int)q("SELECT COUNT(*) FROM messages m JOIN conversations c ON c.id = m.conversation_id
                   WHERE (c.buyer_id = ? OR c.seller_id = ?) AND m.sender_id <> ? AND m.read_at IS NULL",
                  [$userId, $userId, $userId])->fetchColumn();
}

function chat_mark_read(int $conversationId, int $userId): void {
    q("UPDATE messages m JOIN conversations c ON c.id = m.conversation_id
       SET m.read_at = NOW()
       WHERE m.conversation_id = ? AND m.sender_id <> ?
         AND (c.buyer_id = ? OR c.seller_id = ?) AND m.read_at IS NULL",
      [$conversationId, $userId, $userId, $userId]);
}

/** Conversations for the messages list, with last message and unread count. */
function chat_list(int $userId): array {
    return q("SELECT c.*, l.title, l.price, l.status AS listing_status,
                     (SELECT li.image_path FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.id LIMIT 1) AS image_path,
                     CASE WHEN c.buyer_id = ? THEN c.seller_id ELSE c.buyer_id END AS other_id,
                     CASE WHEN c.buyer_id = ? THEN us.full_name ELSE ub.full_name END AS other_name,
                     CASE WHEN c.buyer_id = ? THEN us.student_verified_at ELSE ub.student_verified_at END AS other_student_verified,
                     CASE WHEN c.buyer_id = ? THEN us.trusted_seller_at ELSE ub.trusted_seller_at END AS other_trusted_at,
                     (SELECT m.body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_body,
                     (SELECT m.sender_id FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_sender,
                     (SELECT a.original_name FROM message_attachments a
                      JOIN messages m2 ON m2.id = a.message_id
                      WHERE m2.conversation_id = c.id ORDER BY m2.id DESC LIMIT 1) AS last_attachment,
                     (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_id <> ? AND m.read_at IS NULL) AS unread,
                     c.last_message_at
              FROM conversations c
              JOIN listings l ON l.id = c.listing_id
              JOIN users ub ON ub.id = c.buyer_id
              JOIN users us ON us.id = c.seller_id
              WHERE c.buyer_id = ? OR c.seller_id = ?
              ORDER BY COALESCE(c.last_message_at, c.created_at) DESC, c.id DESC",
             [$userId, $userId, $userId, $userId, $userId, $userId, $userId])->fetchAll();
}

/** Human-friendly message/list timestamp in the database's local time. */
function chat_time_label(?string $ts, bool $compact = false): string {
    if (!$ts) return '';
    $t = strtotime($ts);
    if ($t === false) return '';
    if (date('Y-m-d', $t) === date('Y-m-d')) return date('g:i A', $t);
    return $compact ? date(date('Y', $t) === date('Y') ? 'j M' : 'j M Y', $t) : date('j M, g:i A', $t);
}

/**
 * Stores a message (with an optional image) after validation and rate limiting.
 *
 * @param array{name:string,type:string,tmp_name:string,error:int,size:int}|null $file
 * @return array{ok:bool, id?:int, error?:string}
 */
function chat_send(int $conversationId, int $senderId, string $body, ?array $file = null): array {
    $conv = chat_get($conversationId, $senderId);
    if (!$conv) return ['ok' => false, 'error' => 'Conversation not found.'];
    $other = (int)$conv['buyer_id'] === $senderId ? (int)$conv['seller_id'] : (int)$conv['buyer_id'];
    if (chat_blocked($senderId, $other)) return ['ok' => false, 'error' => 'Messaging is blocked.'];
    $otherStatus = (int)$conv['buyer_id'] === $senderId ? $conv['seller_status'] : $conv['buyer_status'];
    if ($otherStatus !== 'active') return ['ok' => false, 'error' => 'This conversation is no longer available.'];
    if ($conv['moderation_status'] !== 'approved') {
        return ['ok' => false, 'error' => 'This listing is no longer available.'];
    }

    $body = trim($body);
    if (mb_strlen($body) > 2000) return ['ok' => false, 'error' => 'Message is too long (max 2000 characters).'];
    $hasFile = $file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($body === '' && !$hasFile) return ['ok' => false, 'error' => 'Write a message before sending.'];

    $recent = (int)q('SELECT COUNT(*) FROM messages WHERE sender_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)', [$senderId])->fetchColumn();
    if ($recent >= CHAT_RATE_LIMIT) return ['ok' => false, 'error' => 'Slow down a little and try again in a minute.'];

    $attachment = null;
    if ($hasFile) {
        $v = chat_validate_image($file);
        if (isset($v['error'])) return ['ok' => false, 'error' => $v['error']];
        $attachment = $v;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('INSERT INTO messages (conversation_id, sender_id, body) VALUES (?, ?, ?)',
          [$conversationId, $senderId, $body]);
        $messageId = (int)$pdo->lastInsertId();
        if ($attachment) {
            $dir = dirname(__DIR__) . '/storage/chat';
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('storage unavailable');
            if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n");
            $stored = bin2hex(random_bytes(16)) . '.' . $attachment['ext'];
            if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) throw new RuntimeException('move failed');
            q('INSERT INTO message_attachments (message_id, path, mime, bytes, original_name) VALUES (?, ?, ?, ?, ?)',
              [$messageId, $stored, $attachment['mime'], (int)$file['size'], mb_substr((string)$file['name'], 0, 150)]);
        }
        q('UPDATE conversations SET last_message_at = NOW() WHERE id = ?', [$conversationId]);
        $pdo->commit();
        return ['ok' => true, 'id' => $messageId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('CampusFind chat send error: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'The message could not be sent.'];
    }
}

/** @return array{mime:string, ext:string}|array{error:string} */
function chat_validate_image(array $file): array {
    if ((int)$file['error'] !== UPLOAD_ERR_OK) return ['error' => 'The image upload failed.'];
    if ((int)$file['size'] > CHAT_IMAGE_BYTES) return ['error' => 'Images must be 2MB or smaller.'];
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime]) || !@getimagesize($file['tmp_name'])) {
        return ['error' => 'Only JPG, PNG or WebP images are allowed.'];
    }
    return ['mime' => $mime, 'ext' => $allowed[$mime]];
}

/** Messages for a conversation, oldest first. */
function chat_messages(int $conversationId, int $afterId = 0): array {
    return q("SELECT m.id, m.sender_id, m.body, m.read_at, m.created_at,
                     a.id AS attachment_id, a.mime, a.original_name
              FROM messages m
              LEFT JOIN message_attachments a ON a.message_id = m.id
              WHERE m.conversation_id = ? AND m.id > ?
              ORDER BY m.id ASC LIMIT 300", [$conversationId, $afterId])->fetchAll();
}

function report_user_create(int $reporterId, int $reportedId, string $context, string $reason, string $details, ?int $listingId, ?int $conversationId): array {
    if ($reporterId === $reportedId) return ['ok' => false, 'error' => 'You cannot report yourself.'];
    if (!in_array($context, ['profile', 'chat', 'transaction'], true)) return ['ok' => false, 'error' => 'Invalid report type.'];
    if (!in_array($reason, ['scam', 'harassment', 'abuse', 'fake_account', 'other'], true)) return ['ok' => false, 'error' => 'Choose a reason.'];
    if (mb_strlen($details) > 500) return ['ok' => false, 'error' => 'Keep the details under 500 characters.'];
    $target = q('SELECT id FROM users WHERE id = ? AND status <> ?', [$reportedId, 'deactivated'])->fetch();
    if (!$target) return ['ok' => false, 'error' => 'That account is not available.'];
    if ($conversationId !== null) {
        $conv = chat_get($conversationId, $reporterId);
        if (!$conv) return ['ok' => false, 'error' => 'Conversation not found.'];
        $other = (int)$conv['buyer_id'] === $reporterId ? (int)$conv['seller_id'] : (int)$conv['buyer_id'];
        if ($other !== $reportedId) return ['ok' => false, 'error' => 'That user is not in this conversation.'];
    }
    $dup = q("SELECT 1 FROM user_reports WHERE reporter_id = ? AND reported_user_id = ? AND context = ? AND status = 'open'",
             [$reporterId, $reportedId, $context])->fetchColumn();
    if ($dup) return ['ok' => false, 'error' => 'You already have an open report about this user.'];
    q('INSERT INTO user_reports (reporter_id, reported_user_id, listing_id, conversation_id, context, reason, details)
       VALUES (?, ?, ?, ?, ?, ?, ?)',
      [$reporterId, $reportedId, $listingId, $conversationId, $context, $reason, $details !== '' ? $details : null]);
    return ['ok' => true];
}
