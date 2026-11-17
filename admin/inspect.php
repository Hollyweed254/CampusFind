<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();

$filters = ['pending', 'approved', 'rejected', 'removed', 'all'];
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$back = $_GET['back'] ?? $_POST['back'] ?? 'pending';
if (!in_array($back, $filters, true)) $back = 'pending';

$l = q("SELECT l.*, c.name AS category, c.listing_type, u.full_name AS seller_name, u.email AS seller_email, u.phone AS seller_phone, u.created_at AS seller_since
        FROM listings l JOIN categories c ON c.id = l.category_id JOIN users u ON u.id = l.seller_id WHERE l.id = ?", [$id])->fetch();
if (!$l) {
    flash('danger', 'That listing no longer exists.');
    redirect('admin/listings.php?' . http_build_query(['status' => $back]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    if (($_POST['action'] ?? '') === 'inspect') {
        q('UPDATE listings SET inspected_at = NOW(), inspected_by = ? WHERE id = ?', [$admin['id'], $id]);
        q("INSERT INTO moderation_logs (admin_id, listing_id, action, notes) VALUES (?,?,?,?)", [$admin['id'], $id, 'inspect', null]);
        flash('success', 'Listing inspected. You can approve it now.');
        redirect('admin/listings.php?' . http_build_query(['status' => $back]));
    }
}

$imgs = array_values(array_filter(
    q('SELECT image_path FROM listing_images WHERE listing_id = ? ORDER BY id', [$id])->fetchAll(),
    static fn(array $im): bool => listing_photo($im['image_path']) !== null
));
$inspector = $l['inspected_by'] ? (q('SELECT full_name FROM users WHERE id = ?', [$l['inspected_by']])->fetchColumn() ?: null) : null;
$conds = ['new' => 'Brand new', 'like_new' => 'Like new', 'good' => 'Good', 'fair' => 'Fair'];
$mLabel = ['whatsapp' => 'WhatsApp', 'phone' => 'Phone call', 'email' => 'Email'][$l['contact_method']] ?? ucfirst($l['contact_method']);
$pageTitle = 'Inspect listing'; require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Inspect listing</h1>
    <p class="meta mb-0">Review the photos and details before approving this listing.</p>
  </div>
</div>
<?php include __DIR__ . '/_nav.php'; ?>
<a class="section-link d-inline-flex mb-3" href="<?= url('admin/listings.php?' . http_build_query(['status' => $back])) ?>"><i class="bi bi-arrow-left"></i>Back to moderation</a>
<div class="row g-4">
  <div class="col-lg-7">
    <?php if ($imgs): ?>
      <div id="inspectCar" class="carousel slide">
        <div class="carousel-inner">
          <?php foreach ($imgs as $i => $im): ?>
            <div class="carousel-item <?= $i ? '' : 'active' ?>"><img src="<?= e(thumb($im['image_path'])) ?>" class="d-block w-100 detail-img" alt="<?= e($l['title']) ?> photo <?= $i + 1 ?>"></div>
          <?php endforeach; ?>
        </div>
        <?php if (count($imgs) > 1): ?>
          <button class="carousel-control-prev" type="button" data-bs-target="#inspectCar" data-bs-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden">Previous photo</span></button>
          <button class="carousel-control-next" type="button" data-bs-target="#inspectCar" data-bs-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden">Next photo</span></button>
        <?php endif; ?>
      </div>
      <?php if (count($imgs) > 1): ?>
        <div class="gallery-thumbs">
          <?php foreach ($imgs as $i => $im): ?>
            <button type="button" class="gallery-thumb<?= $i ? '' : ' active' ?>" data-bs-target="#inspectCar" data-bs-slide-to="<?= $i ?>" aria-label="Show photo <?= $i + 1 ?>"><img src="<?= e(thumb($im['image_path'])) ?>" alt=""></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="detail-empty">
        <div>
          <span class="detail-empty-icon"><i class="bi <?= e(cat_icon($l['category'])) ?>"></i></span>
          <p class="meta mb-0">No photo was uploaded for this listing.</p>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-5">
    <div class="detail-tag-row">
      <?= status_badge($l['moderation_status']) ?> <?= status_badge($l['status']) ?>
      <?php if (!empty($l['inspected_at'])): ?><?= status_badge('inspected') ?><?php endif; ?>
    </div>
    <h2 class="detail-title"><?= e($l['title']) ?></h2>
    <div class="detail-price"><?= e(format_price($l['price'], $l['listing_type'])) ?></div>
    <div class="detail-facts">
      <span class="fact-chip"><i class="bi bi-grid"></i><?= e($l['category']) ?></span>
      <?php if ($l['item_condition']): ?><span class="fact-chip"><i class="bi bi-stars"></i><?= e($conds[$l['item_condition']] ?? ucfirst($l['item_condition'])) ?></span><?php endif; ?>
      <span class="fact-chip"><i class="bi bi-clock"></i>Posted <?= e(listing_when($l['created_at'])) ?></span>
    </div>

    <div class="sc-card mb-3">
      <div class="seller-head mb-3">
        <span class="avatar"><?= e(initials($l['seller_name'])) ?></span>
        <div>
          <div class="seller-name"><?= e($l['seller_name']) ?></div>
          <div class="meta">Joined <?= e(date('F Y', strtotime($l['seller_since']))) ?></div>
        </div>
      </div>
      <div class="small">
        <div class="d-flex justify-content-between gap-3 py-2 border-top"><span class="text-body-secondary">Email</span><span class="text-end text-break"><?= e($l['seller_email']) ?></span></div>
        <?php if ($l['seller_phone']): ?><div class="d-flex justify-content-between gap-3 py-2 border-top"><span class="text-body-secondary">Phone</span><span class="text-end"><?= e($l['seller_phone']) ?></span></div><?php endif; ?>
        <div class="d-flex justify-content-between gap-3 py-2 border-top"><span class="text-body-secondary">Contact preference</span><span class="text-end"><?= e($mLabel) ?> &middot; <?= e($l['contact_value']) ?></span></div>
      </div>
    </div>

    <div class="sc-card mb-3">
      <h3 class="h6 mb-2">Description</h3>
      <p class="detail-desc mb-0"><?= e($l['description']) ?></p>
    </div>

    <div class="sc-card">
      <?php if (!empty($l['inspected_at'])): ?>
        <p class="mb-0 small"><i class="bi bi-check2-circle me-1"></i>Inspected by <?= e($inspector ?? 'an admin') ?> on <?= e(date('j M Y, H:i', strtotime($l['inspected_at']))) ?>.</p>
      <?php else: ?>
        <p class="meta mb-3">Approval is locked until this listing is inspected.</p>
        <form method="post"><?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $l['id'] ?>">
          <input type="hidden" name="back" value="<?= e($back) ?>">
          <button name="action" value="inspect" class="btn btn-primary w-100 py-2"><i class="bi bi-eye"></i> Mark as inspected</button>
        </form>
      <?php endif; ?>
    </div>
    <p class="meta mt-3 mb-0">Listing #<?= (int)$l['id'] ?> &middot; <?= e(ucfirst($l['listing_type'])) ?></p>
  </div>
</div>
<script>
document.getElementById('inspectCar')?.addEventListener('slid.bs.carousel', function (e) {
  document.querySelectorAll('.gallery-thumb').forEach(function (t, i) { t.classList.toggle('active', i === e.to); });
});
</script>
<?php require __DIR__ . '/../includes/footer.php';
