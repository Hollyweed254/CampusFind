<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/chat.php';
require_once __DIR__ . '/includes/transactions.php';

$me = require_login();
$context = (string)($_GET['context'] ?? $_POST['context'] ?? 'profile');
if (!in_array($context, ['profile', 'chat', 'transaction'], true)) $context = 'profile';
$reportedId = (int)($_GET['reported'] ?? $_POST['reported'] ?? 0);
$conversationId = (int)($_GET['conversation'] ?? $_POST['conversation'] ?? 0);
$transactionId = (int)($_GET['transaction'] ?? $_POST['transaction'] ?? 0);
$listingId = (int)($_GET['listing'] ?? $_POST['listing'] ?? 0);
$back = 'seller.php?id=' . $reportedId;

if ($context === 'chat' && $conversationId) {
    $conv = chat_get($conversationId, (int)$me['id']);
    if (!$conv) { http_response_code(404); exit('Conversation not found.'); }
    $reportedId = (int)$conv['buyer_id'] === (int)$me['id'] ? (int)$conv['seller_id'] : (int)$conv['buyer_id'];
    $listingId = (int)$conv['listing_id'];
    $back = 'messages.php?c=' . $conversationId;
} elseif ($context === 'transaction' && $transactionId) {
    $tx = transaction_get($transactionId, (int)$me['id']);
    if (!$tx) { http_response_code(404); exit('Transaction not found.'); }
    $reportedId = (int)$tx['buyer_id'] === (int)$me['id'] ? (int)$tx['seller_id'] : (int)$tx['buyer_id'];
    $listingId = (int)$tx['listing_id'];
    $back = 'transaction.php?id=' . $transactionId;
}
if ($reportedId === (int)$me['id']) { flash('danger', 'You cannot report yourself.'); redirect($back); }
$reported = q('SELECT id, full_name FROM users WHERE id = ?', [$reportedId])->fetch();
if (!$reported) { http_response_code(404); exit('User not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $r = report_user_create((int)$me['id'], $reportedId, $context, clean('reason'), clean('details'),
        $listingId ?: null, $conversationId ?: null);
    flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? 'Report sent to the administrators.' : $r['error']);
    redirect($back);
}
$pageTitle = 'Report user'; require __DIR__ . '/includes/header.php'; ?>
<div class="narrow-shell">
  <div class="panel">
    <div class="panel-head"><h1 class="h5 mb-0">Report <?= e($reported['full_name']) ?></h1></div>
    <div class="panel-body">
      <p class="meta">Reports are private. Administrators see who reported whom; other users never do.</p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="context" value="<?= e($context) ?>">
        <input type="hidden" name="reported" value="<?= $reportedId ?>">
        <input type="hidden" name="conversation" value="<?= $conversationId ?>">
        <input type="hidden" name="transaction" value="<?= $transactionId ?>">
        <input type="hidden" name="listing" value="<?= $listingId ?>">
        <div class="mb-3">
          <label class="form-label" for="rp-reason">Reason</label>
          <select class="form-select" id="rp-reason" name="reason" required>
            <option value="">Choose a reason</option>
            <option value="scam">Scam or fraud</option>
            <option value="harassment">Harassment</option>
            <option value="abuse">Abusive behaviour</option>
            <option value="fake_account">Fake account</option>
            <option value="other">Something else</option>
          </select>
        </div>
        <div class="mb-4">
          <label class="form-label" for="rp-details">Details</label>
          <textarea class="form-control" id="rp-details" name="details" rows="4" maxlength="500" placeholder="What happened?"></textarea>
          <div class="form-text">Up to 500 characters. Do not include passwords or private documents.</div>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-primary"><i class="bi bi-flag"></i> Send report</button>
          <a class="btn btn-outline-secondary" href="<?= url($back) ?>">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';