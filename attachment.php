<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/chat.php';

$me = require_login();
$attachmentId = (int)($_GET['id'] ?? 0);
$a = q("SELECT a.*, m.conversation_id, c.buyer_id, c.seller_id
        FROM message_attachments a
        JOIN messages m ON m.id = a.message_id
        JOIN conversations c ON c.id = m.conversation_id
        WHERE a.id = ?", [$attachmentId])->fetch();
if (!$a) { http_response_code(404); exit('Attachment not found.'); }
$allowed = (int)$a['buyer_id'] === (int)$me['id'] || (int)$a['seller_id'] === (int)$me['id'] || is_admin();
if (!$allowed) { http_response_code(403); exit('403 - Not allowed.'); }

$mime = $a['mime'];
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) { http_response_code(415); exit('Unsupported attachment.'); }
$file = __DIR__ . '/storage/chat/' . basename($a['path']);
if (!is_file($file)) { http_response_code(404); exit('Attachment not found.'); }

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="' . rawurlencode($a['original_name']) . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($file);
