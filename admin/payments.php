<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mpesa.php';
require_once __DIR__ . '/../includes/trust.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'recheck') {
        $r = mpesa_query_status($id);
        audit_log((int)$admin['id'], 'payment_rechecked', 'payment', $id, $r['ok'] ? ('Status: ' . $r['status']) : ($r['error'] ?? 'failed'));
        flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? 'M-Pesa status: ' . $r['status'] . '.' : ($r['error'] ?? 'Could not check.'));
    }
    redirect('admin/payments.php');
}

$show = (string)($_GET['show'] ?? 'all');
$where = $show === 'pending' ? "WHERE p.status = 'pending'" : '';
$rows = q("SELECT p.*, t.status AS tx_status, ub.full_name AS buyer_name, us.full_name AS seller_name
           FROM payments p JOIN transactions t ON t.id = p.transaction_id
           JOIN users ub ON ub.id = t.buyer_id JOIN users us ON us.id = t.seller_id
           $where ORDER BY p.created_at DESC LIMIT 200")->fetchAll();
$pageTitle = 'Payments'; require __DIR__ . '/../includes/header.php'; ?>
<div class="page-head">
  <div>
    <h1 class="page-title">Payments</h1>
    <p class="mb-0"><?= mpesa_configured() ? 'Daraja is configured (' . e(MPESA_ENV) . ').' : 'Daraja is not configured on this server. No payment request can be sent.' ?></p>
  </div>
</div>
<?php include __DIR__ . '/_nav.php'; ?>
<?php if (!mpesa_configured()): ?>
  <div class="sc-note mb-4"><i class="bi bi-info-circle me-1"></i>Set the <code>CF_MPESA_*</code> environment variables to enable STK Push. Payments are never marked successful without a Safaricom confirmation.</div>
<?php endif; ?>
<div class="seg-control mb-4">
  <a class="btn <?= $show !== 'pending' ? 'active' : '' ?>" href="?show=all">All</a>
  <a class="btn <?= $show === 'pending' ? 'active' : '' ?>" href="?show=pending">Pending</a>
</div>
<?php if ($rows): ?>
  <div class="table-responsive table-card"><table class="table align-middle mb-0">
    <thead><tr><th>Payment</th><th>Transaction</th><th>Amount</th><th>Status</th><th>Receipt / result</th><th>Created</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $p): ?>
        <tr>
          <td><strong>#<?= (int)$p['id'] ?></strong><div class="row-sub"><?= e($p['environment']) ?> &middot; <?= e($p['phone']) ?></div></td>
          <td><a class="row-title" href="<?= url('transaction.php?id=' . (int)$p['transaction_id']) ?>">#<?= (int)$p['transaction_id'] ?></a><div class="row-sub"><?= e($p['buyer_name']) ?> → <?= e($p['seller_name']) ?></div></td>
          <td><?= e(format_price($p['amount'])) ?></td>
          <td><span class="st st-<?= $p['status'] === 'paid' ? 'ok' : (in_array($p['status'], ['failed', 'cancelled', 'timeout'], true) ? 'bad' : 'warn') ?>"><?= e(ucfirst($p['status'])) ?></span></td>
          <td class="small">
            <?php if ($p['mpesa_receipt']): ?><strong><?= e($p['mpesa_receipt']) ?></strong><?php endif; ?>
            <?php if ($p['result_desc']): ?><div class="row-sub"><?= e($p['result_desc']) ?></div><?php endif; ?>
            <?php if ($p['checkout_request_id']): ?><div class="row-sub text-truncate" style="max-width:220px"><?= e($p['checkout_request_id']) ?></div><?php endif; ?>
          </td>
          <td class="meta text-nowrap"><?= e(date('j M H:i', strtotime($p['created_at']))) ?></td>
          <td class="text-end">
            <?php if ($p['status'] === 'pending' && mpesa_configured()): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="recheck">
                <button class="btn btn-sm btn-outline-primary">Check status</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <div class="empty-state"><div class="empty-state-icon"><i class="bi bi-phone"></i></div><h2>No payments</h2><p>M-Pesa attempts appear here.</p></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';