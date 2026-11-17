<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $rid = (int)($_POST['id'] ?? 0); $act = $_POST['action'] ?? '';
    $r = q('SELECT * FROM listing_reports WHERE id = ?', [$rid])->fetch();
    if ($r) {
        if ($act === 'dismiss') { q("UPDATE listing_reports SET status='dismissed' WHERE id = ?", [$rid]); flash('success', 'Report dismissed.'); }
        elseif ($act === 'remove') {
            q("UPDATE listings SET moderation_status='removed' WHERE id = ?", [$r['listing_id']]);
            q('INSERT INTO moderation_logs (admin_id, listing_id, action, notes) VALUES (?,?,?,?)', [$admin['id'], $r['listing_id'], 'remove', 'Removed after report #' . $rid . ' (' . $r['reason'] . ')']);
            q("UPDATE listing_reports SET status='resolved' WHERE listing_id = ? AND status='open'", [$r['listing_id']]);
            flash('success', 'Listing removed and its reports resolved.');
        }
    }
    redirect('admin/flags.php');
}
$show = ($_GET['show'] ?? 'open') === 'all' ? 'all' : 'open';
$rows = q("SELECT r.*, l.title, l.moderation_status, rep.full_name AS reporter, s.full_name AS seller
           FROM listing_reports r JOIN listings l ON l.id = r.listing_id JOIN users rep ON rep.id = r.reporter_id JOIN users s ON s.id = l.seller_id
           " . ($show === 'open' ? "WHERE r.status = 'open'" : '') . " ORDER BY r.created_at DESC LIMIT 200")->fetchAll();
$pageTitle = 'Reported listings'; require __DIR__ . '/../includes/header.php'; ?>
<div class="page-head">
  <div>
    <h1 class="page-title">Reported listings</h1>
  </div>
</div>
<?php include __DIR__ . '/_nav.php'; ?>
<div class="seg-control mb-4">
  <a class="btn <?= $show === 'open' ? 'active' : '' ?>" href="?show=open"><i class="bi bi-exclamation-circle me-1"></i>Open</a>
  <a class="btn <?= $show === 'all' ? 'active' : '' ?>" href="?show=all"><i class="bi bi-collection me-1"></i>All reports</a>
</div>
<?php if (!$rows): ?>
  <div class="empty-state">
    <div class="empty-state-icon"><i class="bi bi-flag"></i></div>
    <h2>No reports</h2>
    <p>Nothing needs attention.</p>
  </div>
<?php else: ?>
<div class="table-card table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Listing</th><th>Reason</th><th>Reported by</th><th>Date</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td>
        <a class="row-title" href="<?= url('listing.php?id=' . $r['listing_id']) ?>"><?= e($r['title']) ?></a>
        <div class="row-sub">Seller: <?= e($r['seller']) ?> <?= status_badge($r['moderation_status']) ?></div>
      </td>
      <td><?= e(ucfirst($r['reason'])) ?><?php if ($r['details']): ?><div class="row-sub"><?= e($r['details']) ?></div><?php endif; ?></td>
      <td><?= e($r['reporter']) ?></td>
      <td class="small text-nowrap"><?= e(date('j M Y', strtotime($r['created_at']))) ?></td>
      <td><?= status_badge($r['status']) ?></td>
      <td class="text-end text-nowrap"><?php if ($r['status'] === 'open'): ?>
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button name="action" value="dismiss" class="btn btn-sm btn-outline-secondary">Dismiss</button>
          <button name="action" value="remove" class="btn btn-sm btn-danger" data-confirm-title="Remove this listing?" data-confirm="It will no longer appear in the marketplace and all open reports on it will be resolved.">Remove listing</button></form>
      <?php endif; ?></td>
    </tr>
  <?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';