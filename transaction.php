<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/transactions.php';
require_once __DIR__ . '/includes/trust.php';

$me = require_login();
$id = (int)($_GET['id'] ?? 0);
$tx = transaction_get($id, (int)$me['id'], is_admin());
if (!$tx) {
    http_response_code(404); $pageTitle = 'Not found'; require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><div class="empty-state-icon"><i class="bi bi-receipt"></i></div><h1 class="h3">Transaction not found</h1><p>It may belong to another account.</p><a class="btn btn-primary" href="' . url('transactions.php') . '">My transactions</a></div>';
    require __DIR__ . '/includes/footer.php'; exit;
}
$isBuyer = (int)$tx['buyer_id'] === (int)$me['id'];
$isSeller = (int)$tx['seller_id'] === (int)$me['id'];
$isParty = $isBuyer || $isSeller;
$isAdmin = is_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isParty) {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'review') {
        $r = transaction_review_create($id, (int)$me['id'], (int)($_POST['rating'] ?? 0), clean('comment'));
    } elseif ($action === 'check_status') {
        $latest = q('SELECT id FROM payments WHERE transaction_id = ? ORDER BY id DESC LIMIT 1', [$id])->fetchColumn();
        $r = $latest ? mpesa_query_status((int)$latest) : ['ok' => false, 'error' => 'No payment to check.'];
        if ($r['ok']) $r['message'] = 'Payment status: ' . $r['status'] . '.';
    } else {
        $r = transaction_action($id, (int)$me['id'], $action, ['reason' => clean('reason'), 'phone' => clean('phone')]);
    }
    flash(!empty($r['ok']) ? 'success' : 'danger', $r['message'] ?? $r['error'] ?? 'Done.');
    redirect('transaction.php?id=' . $id);
}

$tx = transaction_get($id, (int)$me['id'], $isAdmin);
$payment = q('SELECT * FROM payments WHERE transaction_id = ? ORDER BY id DESC LIMIT 1', [$id])->fetch();
$payments = q('SELECT * FROM payments WHERE transaction_id = ? ORDER BY id DESC', [$id])->fetchAll();
$review = transaction_review($id, (int)$me['id']);
$timeline = transaction_timeline($tx);
$otherId = $isBuyer ? (int)$tx['seller_id'] : (int)$tx['buyer_id'];
$st = transaction_status_meta($tx['status']);
$pageTitle = 'Transaction #' . $id; require __DIR__ . '/includes/header.php'; ?>
<div class="page-head">
  <div>
    <h1 class="page-title">Transaction #<?= $id ?></h1>
    <p class="meta mb-0"><?= e($tx['title']) ?></p>
  </div>
  <?= transaction_status_badge($tx['status']) ?>
</div>

<?php if (!$isParty): ?>
  <div class="sc-note mb-4">Administrator view. Actions stay with the buyer and seller.</div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="panel mb-4">
      <div class="panel-body">
        <div class="chat-listing mb-0">
          <?php $photo = listing_photo($tx['image_path'] ?? null); ?>
          <?php if ($photo): ?><img src="<?= e($photo) ?>" alt=""><?php endif; ?>
          <div class="min-w-0 flex-grow-1">
            <a class="row-title d-block text-truncate" href="<?= url('listing.php?id=' . $tx['listing_id']) ?>"><?= e($tx['title']) ?></a>
            <span class="meta"><?= e($tx['listing_status'] === 'sold' ? 'Sold' : ucfirst($tx['listing_status'])) ?></span>
          </div>
          <div class="text-end">
            <div class="detail-price"><?= e(format_price($tx['amount'], 'product')) ?></div>
            <span class="meta">Agreed price</span>
          </div>
        </div>
        <div class="row g-3 mt-2 small">
          <div class="col-sm-6"><span class="meta d-block">Buyer</span><a href="<?= url('seller.php?id=' . $tx['buyer_id']) ?>"><?= e($tx['buyer_name']) ?></a></div>
          <div class="col-sm-6"><span class="meta d-block">Seller</span><a href="<?= url('seller.php?id=' . $tx['seller_id']) ?>"><?= e($tx['seller_name']) ?></a></div>
          <div class="col-sm-6"><span class="meta d-block">Payment method</span><?= e($tx['payment_method'] === 'mpesa' ? 'M-Pesa' : 'Direct to seller') ?></div>
          <div class="col-sm-6"><span class="meta d-block">Recorded</span><?= e(date('j M Y H:i', strtotime($tx['created_at']))) ?></div>
        </div>
      </div>
    </div>

    <div class="panel mb-4">
      <div class="panel-head"><h2>Timeline</h2></div>
      <div class="panel-body">
        <ol class="tx-timeline">
          <?php foreach ($timeline as [$label, $time]): ?>
            <li><span class="tx-dot"></span><div><strong><?= e($label) ?></strong><span class="meta d-block"><?= e(date('j M Y H:i', strtotime($time))) ?></span></div></li>
          <?php endforeach; ?>
        </ol>
        <?php if ($tx['status'] === 'disputed' && $tx['dispute_reason']): ?>
          <div class="sc-error mt-3">Dispute: <?= e($tx['dispute_reason']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><h2>Payment</h2></div>
      <div class="panel-body">
        <?php if (!empty($tx['payment_verified_at'])): ?>
          <div class="sc-note mb-3" style="background: var(--sc-ok-bg); color: var(--sc-ok-fg)"><i class="bi bi-patch-check-fill me-1"></i>Payment confirmed by M-Pesa on <?= e(date('j M Y H:i', strtotime($tx['payment_verified_at']))) ?>.</div>
        <?php elseif (!empty($tx['buyer_reported_paid_at']) || !empty($tx['seller_reported_paid_at'])): ?>
          <div class="sc-note mb-3"><i class="bi bi-info-circle me-1"></i>Payment is reported by the parties, not verified by CampusFind.
            <?php if (!empty($tx['buyer_reported_paid_at'])): ?> Buyer reported <?= e(date('j M H:i', strtotime($tx['buyer_reported_paid_at']))) ?>.<?php endif; ?>
            <?php if (!empty($tx['seller_reported_paid_at'])): ?> Seller confirmed receipt <?= e(date('j M H:i', strtotime($tx['seller_reported_paid_at']))) ?>.<?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($isParty && in_array($tx['status'], ['awaiting_inspection', 'awaiting_payment'], true)): ?>
          <?php if ($isBuyer && $tx['status'] === 'awaiting_inspection'): ?>
            <form method="post" class="mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="inspect">
              <button class="btn btn-primary"><i class="bi bi-eye"></i> I have inspected the item</button>
            </form>
          <?php endif; ?>
          <?php if ($tx['status'] === 'awaiting_payment' && empty($tx['buyer_reported_paid_at'])): ?>
            <?php if ($isBuyer): ?>
              <form method="post" class="mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="report_payment">
                <button class="btn btn-outline-primary"><i class="bi bi-cash-coin"></i> I have paid the seller</button>
                <span class="meta ms-2">Reported payment, not verified.</span>
              </form>
            <?php endif; ?>
            <?php if ($isSeller && empty($tx['seller_reported_paid_at'])): ?>
              <form method="post" class="mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="seller_received">
                <button class="btn btn-outline-primary"><i class="bi bi-check2-circle"></i> I received the payment</button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($isBuyer && $tx['status'] === 'awaiting_payment'): ?>
          <div class="mpesa-box mt-3">
            <div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-phone"></i><strong>Pay with M-Pesa</strong><span class="st st-muted"><?= e(MPESA_ENV === 'production' ? 'Live' : 'Sandbox') ?></span></div>
            <?php if (!mpesa_configured()): ?>
              <p class="meta mb-0">M-Pesa is not configured on this server. Ask the administrator to set the Daraja environment variables.</p>
            <?php else: ?>
              <form method="post" class="d-flex gap-2 flex-wrap align-items-end"><?= csrf_field() ?><input type="hidden" name="action" value="pay_mpesa">
                <div>
                  <label class="form-label" for="mp-phone">Safaricom number</label>
                  <input class="form-control" id="mp-phone" name="phone" value="<?= e($me['phone'] ?? '') ?>" placeholder="0712 345 678" inputmode="tel" required>
                </div>
                <button class="btn btn-primary">Send request</button>
              </form>
              <p class="meta mt-2 mb-0">An STK prompt is sent to the phone. CampusFind only marks the payment paid after Safaricom confirms it.</p>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($tx['status'] === 'payment_pending'): ?>
          <div class="mpesa-box mt-3">
            <div class="mb-2"><i class="bi bi-hourglass-split me-1"></i>Waiting for the M-Pesa prompt. Check the phone and enter the PIN.</div>
            <?php if ($payment): ?><p class="meta mb-2">Request <?= e($payment['checkout_request_id']) ?> &middot; <?= e(format_price($payment['amount'])) ?></p><?php endif; ?>
            <?php if ($isParty): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="check_status">
                <button class="btn btn-sm btn-outline-primary">Check status</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($payments): ?>
          <div class="table-responsive mt-3">
            <table class="table table-sm align-middle mb-0 tx-pay-table">
              <thead><tr><th>Time</th><th>Amount</th><th>Status</th><th>Receipt</th></tr></thead>
              <tbody>
                <?php foreach ($payments as $p): ?>
                  <tr>
                    <td class="meta" data-label="Time"><?= e(date('j M H:i', strtotime($p['created_at']))) ?></td>
                    <td data-label="Amount"><?= e(format_price($p['amount'])) ?></td>
                    <td data-label="Status"><?= e(ucfirst($p['status'])) ?></td>
                    <td class="meta" data-label="Receipt"><?= e($p['mpesa_receipt'] ?: ($p['result_desc'] ?: '—')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="panel mb-4">
      <div class="panel-head"><h2>Next step</h2><?= transaction_status_badge($tx['status']) ?></div>
      <div class="panel-body">
        <?php if ($tx['status'] === 'awaiting_inspection'): ?>
          <p class="mb-0">Meet the seller, inspect the item, then confirm inspection before paying.</p>
        <?php elseif ($tx['status'] === 'awaiting_payment'): ?>
          <p class="mb-0">Pay the agreed amount, then record it. Direct payments are reported by the parties; M-Pesa payments are verified by Safaricom.</p>
        <?php elseif ($tx['status'] === 'paid'): ?>
          <p class="mb-2">Payment recorded. Both sides confirm completion.</p>
          <div class="d-flex flex-column gap-2">
            <?php if ($isBuyer): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="confirm_buyer">
                <button class="btn btn-primary w-100" <?= $tx['buyer_confirmed_at'] ? 'disabled' : '' ?>><?= $tx['buyer_confirmed_at'] ? 'You confirmed completion' : 'Confirm completion' ?></button>
              </form>
            <?php endif; ?>
            <?php if ($isSeller): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="confirm_seller">
                <button class="btn btn-primary w-100" <?= $tx['seller_confirmed_at'] ? 'disabled' : '' ?>><?= $tx['seller_confirmed_at'] ? 'You confirmed completion' : 'Confirm completion' ?></button>
              </form>
            <?php endif; ?>
          </div>
          <p class="meta mt-2 mb-0">Needs both confirmations. This is not a payment guarantee.</p>
        <?php elseif ($tx['status'] === 'completed'): ?>
          <p class="mb-0">Completed <?= e(date('j M Y', strtotime($tx['completed_at']))) ?>. The listing is marked sold.</p>
        <?php elseif ($tx['status'] === 'disputed'): ?>
          <p class="mb-0">An administrator will review the dispute.</p>
        <?php elseif ($tx['status'] === 'cancelled'): ?>
          <p class="mb-0">This transaction was cancelled.</p>
        <?php else: ?>
          <p class="mb-0">This transaction is in progress.</p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($isParty && in_array($tx['status'], ['paid', 'completed', 'disputed'], true)): ?>
      <div class="panel mb-4">
        <div class="panel-head"><h2>Transaction review</h2></div>
        <div class="panel-body">
          <?php if ($review): ?>
            <div class="review-stars mb-2" aria-label="<?= (int)$review['rating'] ?> out of 5"><?php for ($i = 1; $i <= 5; $i++): ?><i class="bi bi-star<?= $i <= (int)$review['rating'] ? '-fill' : '' ?>"></i><?php endfor; ?></div>
            <p class="mb-1"><?= e($review['comment'] ?: 'No comment.') ?></p>
            <p class="meta mb-0">By <?= e($review['reviewer_name']) ?> &middot; <?= e(date('j M Y', strtotime($review['created_at']))) ?></p>
          <?php elseif ($tx['status'] === 'completed'): ?>
            <p class="meta">Reviews are only accepted from the buyer and seller of this completed transaction.</p>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="review">
              <div class="mb-3">
                <label class="form-label" for="rv-rating">Rating</label>
                <select class="form-select" id="rv-rating" name="rating" required>
                  <option value="5">5 — Excellent</option><option value="4">4 — Good</option><option value="3">3 — Okay</option><option value="2">2 — Poor</option><option value="1">1 — Bad</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label" for="rv-comment">Comment</label>
                <textarea class="form-control" id="rv-comment" name="comment" rows="3" maxlength="1000"></textarea>
              </div>
              <button class="btn btn-primary">Submit review</button>
            </form>
          <?php else: ?>
            <p class="meta mb-0">Reviews open once the transaction is completed.</p>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($isParty): ?>
      <div class="panel">
        <div class="panel-head"><h2>Help</h2></div>
        <div class="panel-body">
          <a class="btn btn-sm btn-outline-danger w-100 mb-3" href="<?= url('report-user.php?reported=' . $otherId . '&context=transaction&transaction=' . $id) ?>"><i class="bi bi-flag"></i> Report a problem</a>
          <?php if (in_array($tx['status'], ['initiated', 'awaiting_inspection', 'awaiting_payment'], true)): ?>
            <form method="post" onsubmit="return confirm('Cancel this transaction?')"><?= csrf_field() ?><input type="hidden" name="action" value="cancel">
              <button class="btn btn-sm btn-outline-secondary w-100">Cancel transaction</button>
            </form>
          <?php elseif (!in_array($tx['status'], ['completed', 'cancelled'], true)): ?>
            <details>
              <summary class="small fw-semibold">Open a dispute</summary>
              <form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="dispute">
                <textarea class="form-control mb-2" name="reason" rows="3" minlength="10" maxlength="500" placeholder="Describe the problem" required></textarea>
                <button class="btn btn-sm btn-outline-danger">Send dispute</button>
              </form>
            </details>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';