<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/chat.php';
require_once __DIR__ . '/../includes/trust.php';
$admin = require_admin();

$id = (int)($_GET['id'] ?? 0);
$conv = chat_get($id, (int)$admin['id'], true);
if (!$conv) { http_response_code(404); exit('Conversation not found.'); }
audit_log((int)$admin['id'], 'conversation_viewed', 'conversation', $id, 'Viewed as part of an abuse report');
$messages = chat_messages($id);
$pageTitle = 'Conversation #' . $id; require __DIR__ . '/../includes/header.php'; ?>
<div class="page-head">
  <div>
    <h1 class="page-title">Conversation #<?= $id ?></h1>
    <p><a href="<?= url('seller.php?id=' . $conv['buyer_id']) ?>"><?= e($conv['buyer_name']) ?></a> and <a href="<?= url('seller.php?id=' . $conv['seller_id']) ?>"><?= e($conv['seller_name']) ?></a> about <a href="<?= url('listing.php?id=' . $conv['listing_id']) ?>"><?= e($conv['title']) ?></a></p>
  </div>
  <a class="btn btn-outline-secondary" href="<?= url('admin/user-reports.php') ?>">Back to reports</a>
</div>
<div class="panel">
  <div class="panel-body">
    <div class="chat-messages" style="max-height: 60vh">
      <?php foreach ($messages as $m): $mine = (int)$m['sender_id'] === (int)$conv['seller_id']; ?>
        <div class="msg <?= $mine ? 'msg-mine' : 'msg-theirs' ?>">
          <div class="msg-bubble">
            <?php if (!empty($m['attachment_id'])): ?><a class="msg-image" href="<?= url('attachment.php?id=' . (int)$m['attachment_id']) ?>" target="_blank" rel="noopener">Attachment</a><?php endif; ?>
            <?php if ($m['body'] !== ''): ?><p><?= nl2br(e($m['body'])) ?></p><?php endif; ?>
            <span class="msg-time"><?= e(($mine ? $conv['seller_name'] : $conv['buyer_name']) . ' · ' . date('j M H:i', strtotime($m['created_at']))) ?></span>
          </div>
        </div>
      <?php endforeach; if (!$messages): ?><p class="meta mb-0">No messages.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php';