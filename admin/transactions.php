<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/transactions.php';
require_once __DIR__ . '/../includes/trust.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $id = (int)($_POST['id'] ?? 0);
    $act = (string)($_POST['action'] ?? '');
    $note = mb_substr(clean('note'), 0, 500);
    $t = q('SELECT * FROM transactions WHERE id = ?', [$id])->fetch();
    if ($t && $t['status'] === 'disputed' && in_array($act, ['complete', 'cancel'], true)) {
        if ($act === 'complete') {
            q("UPDATE transactions SET status = 'completed', completed_at = NOW() WHERE id = ? AND status = 'disputed'", [$id]);
            q("UPDATE listings SET status = 'sold' WHERE id = ?", [(int)$t['listing_id']]);
            trust_refresh((int)$t['seller_id']);
        } else {
            q("UPDATE transactions SET status = 'cancelled', cancelled_at = NOW() WHERE id = ? AND status = 'disputed'", [$id]);
        }
        audit_log((int)$admin['id'], 'dispute_' . $act, 'transaction', $id, $note);
        flash('success', 'Dispute resolved.');
    }
    redirect('admin/transactions.php');
}

$filter = (string)($_GET['show'] ?? 'open');
if (!in_array($filter, ['open', 'disputed', 'completed', 'all'], true)) $filter = 'open';
$where = '';
if ($filter === 'open') $where = "WHERE t.status NOT IN ('completed','cancelled')";
elseif ($filter === 'disputed') $where = "WHERE t.status = 'disputed'";
elseif ($filter === 'completed') $where = "WHERE t.status = 'completed'";
$rows = q("SELECT t.*, l.title, ub.full_name AS buyer_name, us.full_name AS seller_name
           FROM transactions t JOIN listings l ON l.id = t.listing_id
           JOIN users ub ON ub.id = t.buyer_id JOIN users us ON us.id = t.seller_id
           $where ORDER BY t.updated_at DESC LIMIT 200")->fetchAll();
$counts = [
  'open' => (int)q("SELECT COUNT(*) FROM transactions WHERE status NOT IN ('completed','cancelled')")->fetchColumn(),
  'disputed' => (int)q("SELECT COUNT(*) FROM transactions WHERE status = 'disputed'")->fetchColumn(),
];
$pageTitle = 'Transactions'; require __DIR__ . '/../includes/header.php'; ?>
<div class="page-head">
  <div>
    <h1 class="page-title">Transactions</h1>
    <p><?= $counts['open'] ?> open &middot; <?= $counts['disputed'] ?> disputed</p>
  </div>
</div>
<?php include __DIR__ . '/_nav.php'; ?>
<div class="seg-control mb-4">
  <?php foreach (['open' => 'Open', 'disputed' => 'Disputes', 'completed' => 'Completed', 'all' => 'All'] as $k => $label): ?>
    <a class="btn <?= $filter === $k ? 'active' : '' ?>" href="?show=<?= $k ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>
<?php if ($rows): ?>
  <div class="table-responsive table-card"><table class="table align-middle mb-0">
    <thead><tr><th>Item</th><th>Buyer / Seller</th><th>Amount</th><th>Status</th><th>Payment</th><th>Updated</th><th>Dispute</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $t): ?>
        <tr>
          <td><a class="row-title" href="<?= url('transaction.php?id=' . $t['id']) ?>">#<?= (int)$t['id'] ?> · <?= e($t['title']) ?></a></td>
          <td class="small"><?= e($t['buyer_name']) ?><div class="row-sub"><?= e($t['seller_name']) ?></div></td>
          <td><?= e(format_price($t['amount'])) ?></td>
          <td><?= transaction_status_badge($t['status']) ?></td>
          <td class="small"><?= $t['payment_verified_at'] ? '<span class="st st-ok">M-Pesa verified</span>' : ($t['buyer_reported_paid_at'] ? '<span class="st st-warn">Reported</span>' : '<span class="meta">—</span>') ?></td>
          <td class="meta text-nowrap"><?= e(date('j M H:i', strtotime($t['updated_at']))) ?></td>
          <td style="min-width: 230px">
            <?php if ($t['status'] === 'disputed'): ?>
              <div class="row-sub mb-2"><?= e($t['dispute_reason']) ?></div>
              <form method="post" class="d-flex gap-2 flex-wrap"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <input class="form-control form-control-sm" name="note" maxlength="500" placeholder="Resolution note" style="max-width:170px">
                <button name="action" value="complete" class="btn btn-sm btn-primary">Complete</button>
                <button name="action" value="cancel" class="btn btn-sm btn-outline-danger">Cancel</button>
              </form>
            <?php else: ?><span class="meta">—</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <div class="empty-state"><div class="empty-state-icon"><i class="bi bi-receipt"></i></div><h2>No transactions</h2><p>Nothing matches this filter.</p></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';