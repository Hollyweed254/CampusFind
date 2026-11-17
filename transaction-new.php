<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/transactions.php';

$me = require_login();
$listingId = (int)($_GET['listing'] ?? 0);
$l = q("SELECT l.*, c.name AS category, u.full_name AS seller_name,
               (SELECT image_path FROM listing_images WHERE listing_id = l.id ORDER BY id LIMIT 1) AS image_path
        FROM listings l JOIN categories c ON c.id = l.category_id JOIN users u ON u.id = l.seller_id
        WHERE l.id = ?", [$listingId])->fetch();
if (!$l || $l['moderation_status'] !== 'approved' || $l['status'] !== 'available' || (int)$l['seller_id'] === (int)$me['id']) {
    http_response_code(404); $pageTitle = 'Not available'; require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><div class="empty-state-icon"><i class="bi bi-box-seam"></i></div><h1 class="h3">Cannot start a transaction</h1><p>This listing is unavailable, or it belongs to you.</p><a class="btn btn-primary" href="' . url('marketplace.php') . '">Browse listings</a></div>';
    require __DIR__ . '/includes/footer.php'; exit;
}
$hasChat = (bool)q('SELECT 1 FROM conversations WHERE listing_id = ? AND buyer_id = ?', [$listingId, $me['id']])->fetchColumn();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$hasChat) $errors[] = 'Message the seller before recording a transaction.';
    else {
        $r = transaction_create($listingId, (int)$me['id'], clean('amount'));
        if ($r['id']) { flash('success', 'Transaction recorded. Inspect the item before paying.'); redirect('transaction.php?id=' . $r['id']); }
        $errors[] = $r['error'];
    }
}
$pageTitle = 'Record transaction'; require __DIR__ . '/includes/header.php'; ?>
<div class="narrow-shell">
  <div class="panel">
    <div class="panel-head"><h1 class="h5 mb-0">Record a transaction</h1></div>
    <div class="panel-body">
      <div class="chat-listing mb-4">
        <?php $photo = listing_photo($l['image_path']); ?>
        <?php if ($photo): ?><img src="<?= e($photo) ?>" alt=""><?php endif; ?>
        <div class="min-w-0">
          <a class="row-title d-block text-truncate" href="<?= url('listing.php?id=' . $listingId) ?>"><?= e($l['title']) ?></a>
          <span class="meta"><?= e($l['seller_name']) ?> &middot; listed at <?= e(format_price($l['price'])) ?></span>
        </div>
      </div>
      <?php if ($errors): ?><div class="sc-error mb-3"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <?php if (!$hasChat): ?>
        <div class="sc-note mb-3">Agree on the price in chat first, then record the transaction here.</div>
        <form method="post" action="<?= url('chat-start.php') ?>">
          <?= csrf_field() ?><input type="hidden" name="listing" value="<?= $listingId ?>">
          <button class="btn btn-primary"><i class="bi bi-chat-dots"></i> Message seller</button>
        </form>
      <?php else: ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="tx-amount">Agreed price (KSh)</label>
            <input class="form-control" id="tx-amount" name="amount" type="number" min="1" max="99999999" step="0.01" value="<?= e($l['price'] ?? '') ?>" required>
            <div class="form-text">Both sides can see this amount. M-Pesa only accepts whole shillings.</div>
          </div>
          <div class="sc-note mb-4">Next: inspect the item, pay the seller, then confirm completion together.</div>
          <button class="btn btn-primary">Record transaction</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';