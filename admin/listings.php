<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $id = (int)($_POST['id'] ?? 0); $act = $_POST['action'] ?? ''; $notes = mb_substr(clean('notes'), 0, 255);
    $map = ['approve' => 'approved', 'reject' => 'rejected', 'remove' => 'removed', 'restore' => 'approved'];
    $row = isset($map[$act]) ? q('SELECT inspected_at FROM listings WHERE id = ?', [$id])->fetch() : null;
    if ($row) {
        if (in_array($act, ['approve', 'restore'], true) && empty($row['inspected_at'])) {
            flash('danger', 'Inspect this listing before approving it.');
        } elseif (in_array($act, ['reject', 'remove'], true) && $notes === '') {
            flash('danger', 'Add a short note explaining the reason.');
        } else {
            q('UPDATE listings SET moderation_status = ? WHERE id = ?', [$map[$act], $id]);
            q('INSERT INTO moderation_logs (admin_id, listing_id, action, notes) VALUES (?,?,?,?)', [$admin['id'], $id, $act, $notes ?: null]);
            flash('success', 'Listing ' . $map[$act] . '.');
        }
    }
    redirect('admin/listings.php?' . http_build_query(['status' => $_POST['back'] ?? 'pending']));
}
$filter = $_GET['status'] ?? 'pending';
if (!in_array($filter, ['pending', 'approved', 'rejected', 'removed', 'all'], true)) $filter = 'pending';
$sql = "SELECT l.*, c.name AS category, c.listing_type, u.full_name AS seller, (SELECT image_path FROM listing_images WHERE listing_id = l.id ORDER BY id LIMIT 1) AS img
        FROM listings l JOIN categories c ON c.id = l.category_id JOIN users u ON u.id = l.seller_id";
$rows = $filter === 'all' ? q("$sql ORDER BY l.created_at DESC LIMIT 200")->fetchAll() : q("$sql WHERE l.moderation_status = ? ORDER BY l.created_at DESC LIMIT 200", [$filter])->fetchAll();
$pageTitle = 'Manage listings'; require __DIR__ . '/../includes/header.php'; ?>
<div class="page-head">
  <div>
    <h1 class="page-title">Listings</h1>
  </div>
</div>
<?php include __DIR__ . '/_nav.php'; ?>
<div class="seg-control mb-4">
  <?php $filters = ['pending' => 'hourglass-split', 'approved' => 'check-circle', 'rejected' => 'x-circle', 'removed' => 'eye-slash', 'all' => 'grid']; ?>
  <?php foreach ($filters as $f => $ic): ?>
    <a class="btn <?= $filter === $f ? 'active' : '' ?>" href="?status=<?= $f ?>"><i class="bi bi-<?= $ic ?> me-1"></i><?= ucfirst($f) ?></a>
  <?php endforeach; ?>
</div>
<?php if (!$rows): ?>
  <div class="empty-state">
    <div class="empty-state-icon"><i class="bi bi-inbox"></i></div>
    <h2>No listings in this view</h2>
    <p>Try another status filter.</p>
  </div>
<?php endif; ?>
<?php foreach ($rows as $l): ?>
  <div class="panel mb-3"><div class="panel-body">
    <div class="admin-listing">
      <img src="<?= e(thumb($l['img'])) ?>" class="admin-listing-img" alt="">
      <div class="min-w-0">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
          <a class="row-title" href="<?= url('listing.php?id=' . $l['id']) ?>"><?= e($l['title']) ?></a>
          <?= status_badge($l['moderation_status']) ?> <?= status_badge($l['status']) ?>
          <?php if (!empty($l['inspected_at']) && $l['moderation_status'] !== 'approved'): ?><?= status_badge('inspected') ?><?php endif; ?>
        </div>
        <div class="row-sub mb-2"><?= e($l['category']) ?> &middot; <?= e(format_price($l['price'], $l['listing_type'])) ?> &middot; by <?= e($l['seller']) ?> &middot; <?= e(date('j M Y', strtotime($l['created_at']))) ?></div>
        <div class="text-body-secondary small"><?= e(mb_strimwidth($l['description'], 0, 220, '...')) ?></div>
      </div>
      <div class="admin-listing-actions">
        <?php if (in_array($l['moderation_status'], ['pending', 'rejected', 'removed'], true)): ?>
          <?php if (empty($l['inspected_at'])): ?>
            <a class="btn btn-sm btn-primary" href="<?= url('admin/inspect.php?' . http_build_query(['id' => $l['id'], 'back' => $filter])) ?>"><i class="bi bi-eye"></i> Inspect</a>
          <?php else: ?>
            <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $l['id'] ?>"><input type="hidden" name="back" value="<?= e($filter) ?>">
              <button name="action" value="<?= $l['moderation_status'] === 'removed' ? 'restore' : 'approve' ?>" class="btn btn-sm btn-primary"><?= $l['moderation_status'] === 'removed' ? 'Restore' : 'Approve' ?></button></form>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($l['moderation_status'] === 'pending'): ?>
          <button type="button" class="btn btn-sm btn-warning" data-reason data-action="reject" data-id="<?= $l['id'] ?>" data-back="<?= e($filter) ?>" data-title="<?= e($l['title']) ?>" data-url="<?= e(url('admin/listings.php')) ?>">Reject</button>
        <?php endif; ?>
        <?php if ($l['moderation_status'] === 'approved'): ?>
          <button type="button" class="btn btn-sm btn-outline-danger" data-reason data-action="remove" data-id="<?= $l['id'] ?>" data-back="<?= e($filter) ?>" data-title="<?= e($l['title']) ?>" data-url="<?= e(url('admin/listings.php')) ?>">Remove</button>
        <?php endif; ?>
      </div>
    </div>
  </div></div>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/footer.php';