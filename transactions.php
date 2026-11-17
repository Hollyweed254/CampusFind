<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/transactions.php';

$me = require_login();
$role = ($_GET['role'] ?? '') === 'selling' ? 'selling' : 'buying';
$column = $role === 'buying' ? 'buyer_id' : 'seller_id';
$otherColumn = $role === 'buying' ? 'seller_id' : 'buyer_id';
$rows = q("SELECT t.*, l.title, u.full_name AS other_name,
                  (SELECT li.image_path FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.id LIMIT 1) AS image_path
           FROM transactions t
           JOIN listings l ON l.id = t.listing_id
           JOIN users u ON u.id = t.$otherColumn
           WHERE t.$column = ?
           ORDER BY t.created_at DESC, t.id DESC", [(int)$me['id']])->fetchAll();
$open = (int)q("SELECT COUNT(*) FROM transactions WHERE $column = ? AND status NOT IN ('completed','cancelled')", [(int)$me['id']])->fetchColumn();
$pageTitle = 'Transactions'; require __DIR__ . '/includes/header.php'; ?>
<div class="page-head">
  <div>
    <h1 class="page-title">Transactions</h1>
    <p><?= $open ?> open</p>
  </div>
</div>
<ul class="nav nav-pills mb-4">
  <li class="nav-item"><a class="nav-link <?= $role === 'buying' ? 'active' : '' ?>" href="<?= url('transactions.php?role=buying') ?>">Buying</a></li>
  <li class="nav-item"><a class="nav-link <?= $role === 'selling' ? 'active' : '' ?>" href="<?= url('transactions.php?role=selling') ?>">Selling</a></li>
</ul>

<?php if ($rows): ?>
  <div class="panel">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Item</th><th><?= $role === 'buying' ? 'Seller' : 'Buyer' ?></th><th>Amount</th><th>Status</th><th>Updated</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $t): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php $photo = listing_photo($t['image_path'] ?? null); ?>
                  <?php if ($photo): ?><img class="table-thumb" src="<?= e($photo) ?>" alt=""><?php endif; ?>
                  <a class="row-title" href="<?= url('listing.php?id=' . $t['listing_id']) ?>"><?= e($t['title']) ?></a>
                </div>
              </td>
              <td><?= e($t['other_name']) ?></td>
              <td><?= e(format_price($t['amount'])) ?></td>
              <td><?= transaction_status_badge($t['status']) ?></td>
              <td class="meta"><?= e(date('j M H:i', strtotime($t['updated_at']))) ?></td>
              <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url('transaction.php?id=' . $t['id']) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php else: ?>
  <div class="empty-state">
    <div class="empty-state-icon"><i class="bi bi-receipt"></i></div>
    <h2 class="h4">No <?= $role ?> transactions</h2>
    <p><?= $role === 'buying' ? 'Record one from a conversation once you agree with a seller.' : 'Buyers can record a transaction from their conversation with you.' ?></p>
    <a class="btn btn-primary" href="<?= url('marketplace.php') ?>">Browse listings</a>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php';