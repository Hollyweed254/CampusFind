<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/chat.php';

$me = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST required.'); }
csrf_check();

$listingId = (int)($_POST['listing'] ?? 0);
$r = chat_start($listingId, (int)$me['id']);
if ($r['id']) redirect('messages.php?c=' . $r['id']);
flash('danger', $r['error'] ?? 'The conversation could not be opened.');
redirect('listing.php?id=' . $listingId);
