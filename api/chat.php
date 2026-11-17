<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/chat.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$me = current_user();
if (!$me) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Please log in.']); exit; }
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'poll') {
    $cid = (int)($_GET['c'] ?? 0);
    $after = max(0, (int)($_GET['after'] ?? 0));
    $conv = chat_get($cid, (int)$me['id']);
    if (!$conv) { http_response_code(403); echo json_encode(['ok' => false, 'error' => 'Conversation not found.']); exit; }
    $rows = chat_messages($cid, $after);
    chat_mark_read($cid, (int)$me['id']);
    $out = [];
    foreach ($rows as $m) {
        $out[] = [
            'id' => (int)$m['id'],
            'sender_id' => (int)$m['sender_id'],
            'body' => $m['body'],
            'time' => date('j M H:i', strtotime($m['created_at'])),
            'time_label' => chat_time_label($m['created_at']),
            'attachment_id' => $m['attachment_id'] ? (int)$m['attachment_id'] : null,
            'attachment_url' => $m['attachment_id'] ? url('attachment.php?id=' . (int)$m['attachment_id']) : null,
            'attachment_name' => $m['original_name'],
        ];
    }
    $readUpto = (int)q('SELECT COALESCE(MAX(id), 0) FROM messages WHERE conversation_id = ? AND sender_id = ? AND read_at IS NOT NULL',
                       [$cid, (int)$me['id']])->fetchColumn();
    echo json_encode(['ok' => true, 'messages' => $out, 'read_upto' => $readUpto]);
    exit;
}

if ($action === 'send') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false, 'error' => 'POST required.']); exit; }
    $token = (string)($_POST['csrf'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403); echo json_encode(['ok' => false, 'error' => 'Your session expired. Reload the page.']); exit;
    }
    $cid = (int)($_POST['c'] ?? 0);
    $r = chat_send($cid, (int)$me['id'], (string)($_POST['body'] ?? ''), $_FILES['image'] ?? null);
    if (!$r['ok']) { http_response_code(422); echo json_encode($r); exit; }
    $m = q('SELECT m.id, m.sender_id, m.body, m.created_at, a.id AS attachment_id, a.original_name
            FROM messages m LEFT JOIN message_attachments a ON a.message_id = m.id WHERE m.id = ?', [$r['id']])->fetch();
    echo json_encode(['ok' => true, 'message' => [
        'id' => (int)$m['id'],
        'sender_id' => (int)$m['sender_id'],
        'body' => $m['body'],
        'time' => date('j M H:i', strtotime($m['created_at'])),
        'time_label' => chat_time_label($m['created_at']),
        'attachment_id' => $m['attachment_id'] ? (int)$m['attachment_id'] : null,
        'attachment_url' => $m['attachment_id'] ? url('attachment.php?id=' . (int)$m['attachment_id']) : null,
        'attachment_name' => $m['original_name'],
    ]]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
