<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$me = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $id = (int)($_POST['id'] ?? 0); $act = $_POST['action'] ?? '';
    // Ownership enforced in every query
    $own = q('SELECT id, moderation_status FROM listings WHERE id = ? AND seller_id = ?', [$id, $me['id']])->fetch();
    if (!$own) { http_response_code(403); exit('403 - Not your listing.'); }
    if ($act === 'delete') {
        delete_listing_files($id);
        q('DELETE FROM listings WHERE id = ? AND seller_id = ?', [$id, $me['id']]);
        flash('success', 'Listing deleted.');
    } elseif (in_array($act, ['sold', 'closed', 'available'], true)) {
        if ($act === 'available' && $own['moderation_status'] === 'removed') flash('danger', 'Removed listings cannot be reopened.');
        else { q('UPDATE listings SET status = ? WHERE id = ? AND seller_id = ?', [$act, $id, $me['id']]); flash('success', 'Listing updated.'); }
    }
    redirect('my-listings.php');
}
$rows = q("SELECT l.*, c.name AS category, c.listing_type, (SELECT image_path FROM listing_images WHERE listing_id = l.id ORDER BY id LIMIT 1) AS img
           FROM listings l JOIN categories c ON c.id = l.category_id WHERE l.seller_id = ? ORDER BY l.created_at DESC", [$me['id']])->fetchAll();
$pageTitle = 'My listings'; require __DIR__ . '/includes/header.php'; ?>
<div class="page-head">
  <div>
    <h1 class="page-title">My listings</h1>
  </div>
  <a class="btn btn-primary" href="<?= url('create-listing.php') ?>"><i class="bi bi-plus-lg"></i> Post a listing</a>
</div>
<?php if (!$rows): ?>
  <div class="empty-state">
    <div class="empty-state-icon"><i class="bi bi-card-list"></i></div>
    <h2>You have no listings yet</h2>
    <p>Post your first item or service.</p>
    <a class="btn btn-primary" href="<?= url('create-listing.php') ?>"><i class="bi bi-plus-lg"></i> Post a listing</a>
  </div>
<?php else: ?>
<div class="table-card table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th class="ps-3">Listing</th><th>Price</th><th>Availability</th><th>Review</th><th class="text-end pe-3">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $l): ?>
    <tr>
      <td class="ps-3">
        <div class="d-flex align-items-center gap-3">
          <?php $photo = listing_photo($l['img'] ?? null); ?>
          <?php if ($photo): ?>
            <img class="table-thumb" src="<?= e($photo) ?>" alt="">
          <?php else: ?>
            <span class="table-thumb table-thumb-art"><i class="bi <?= e(cat_icon($l['category'])) ?>"></i></span>
          <?php endif; ?>
          <div class="min-w-0">
            <a class="row-title d-block text-truncate" href="<?= url('listing.php?id=' . $l['id']) ?>"><?= e($l['title']) ?></a>
            <div class="row-sub"><?= e($l['category']) ?> &middot; <?= e(listing_when($l['created_at'])) ?></div>
          </div>
        </div>
      </td>
      <td class="text-nowrap"><?= e(format_price($l['price'], $l['listing_type'])) ?></td>
      <td><?= status_badge($l['status']) ?></td>
      <td><?= status_badge($l['moderation_status']) ?></td>
      <td class="text-end pe-3 text-nowrap">
        <form method="post" class="d-inline-flex flex-wrap gap-1 justify-content-end"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $l['id'] ?>">
          <?php if ($l['moderation_status'] !== 'removed'): ?><a class="btn btn-sm btn-outline-secondary" href="<?= url('edit-listing.php?id=' . $l['id']) ?>"><i class="bi bi-pencil"></i> Edit</a><?php endif; ?>
          <?php if ($l['status'] === 'available'): ?>
            <button name="action" value="sold" class="btn btn-sm btn-outline-primary">Mark sold</button>
            <button name="action" value="closed" class="btn btn-sm btn-outline-secondary">Close</button>
          <?php else: ?><button name="action" value="available" class="btn btn-sm btn-outline-primary">Reopen</button><?php endif; ?>
          <button name="action" value="delete" class="btn btn-sm btn-outline-danger" data-confirm-title="Remove this listing?" data-confirm="This listing will no longer appear in the marketplace. This action cannot be undone.">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?></tbody></table></div>
<p class="meta mt-3">Marking sold only changes the listing status. CampusFind does not record sales or process payments.</p>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php';