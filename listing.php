<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/trust.php';
$id = (int)($_GET['id'] ?? 0);
$l = q("SELECT l.*, c.name AS category, c.listing_type, u.full_name AS seller_name, u.created_at AS seller_since,
               u.id AS seller_user_id, u.status AS seller_status, u.phone_verified_at, u.phone_verified_mode,
               u.email_verified_at, u.university_email_verified_at, u.student_verified_at, u.trusted_seller_at
        FROM listings l JOIN categories c ON c.id = l.category_id JOIN users u ON u.id = l.seller_id
        WHERE l.id = ? AND u.status = 'active'", [$id])->fetch();
$me = current_user();
$isOwner = $l && $me && (int)$me['id'] === (int)$l['seller_id'];
if (!$l || ($l['moderation_status'] !== 'approved' && !$isOwner && !is_admin())) {
    http_response_code(404); $pageTitle = 'Not found'; require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><div class="empty-state-icon"><i class="bi bi-search"></i></div><h1 class="h3">Listing not found</h1><p>This listing may have been removed, sold, or is still awaiting approval.</p><a class="btn btn-primary" href="' . url('marketplace.php') . '">Back to marketplace</a></div>';
    require __DIR__ . '/includes/footer.php'; exit;
}
$imgs = array_values(array_filter(
    q('SELECT image_path FROM listing_images WHERE listing_id = ? ORDER BY id', [$id])->fetchAll(),
    static fn(array $im): bool => listing_photo($im['image_path']) !== null
));
$ogTitle = $l['title'] . ' | ' . APP_NAME;
$ogDescription = mb_substr(trim(preg_replace('/\s+/', ' ', $l['description'])), 0, 160);
$ogUrl = absolute_url('listing.php?id=' . $id);
$ogImage = $imgs
    ? absolute_url('uploads/' . rawurlencode($imgs[0]['image_path']))
    : absolute_url('assets/images/placeholder.svg');
$ogImageAlt = $l['title'];
$pageTitle = $l['title']; require __DIR__ . '/includes/header.php';
$conds = ['new' => 'Brand new', 'like_new' => 'Like new', 'good' => 'Good', 'fair' => 'Fair'];
$m = $l['contact_method'];
$mIcon = ['whatsapp' => 'whatsapp', 'phone' => 'telephone', 'email' => 'envelope'][$m];
$mLabel = ['whatsapp' => 'WhatsApp', 'phone' => 'phone call', 'email' => 'email'][$m];
$badgeUser = [
    'id' => $l['seller_user_id'], 'status' => $l['seller_status'],
    'phone_verified_at' => $l['phone_verified_at'], 'phone_verified_mode' => $l['phone_verified_mode'],
    'email_verified_at' => $l['email_verified_at'], 'student_verified_at' => $l['student_verified_at'],
];
$sellerBadges = seller_badges($badgeUser);
$sellerProfileUrl = url('seller.php?id=' . $l['seller_user_id']);
?>
<nav class="listing-breadcrumb" aria-label="Breadcrumb">
  <a href="<?= url('marketplace.php') ?>">Marketplace</a>
  <i class="bi bi-chevron-right"></i>
  <a href="<?= url('marketplace.php?category=' . $l['category_id']) ?>"><?= e($l['category']) ?></a>
  <i class="bi bi-chevron-right"></i>
  <span class="text-truncate"><?= e($l['title']) ?></span>
</nav>
<?php if ($l['moderation_status'] !== 'approved'): ?>
  <div class="alert alert-warning d-flex gap-2 align-items-center mb-4"><?= status_badge($l['moderation_status']) ?> This listing is only visible to you<?= is_admin() ? ' and admins' : '' ?>.</div>
<?php endif; ?>
<div class="row g-4 g-lg-5">
  <div class="col-lg-7">
    <?php if ($imgs): ?>
      <div id="car" class="carousel slide">
        <div class="carousel-inner">
          <?php foreach ($imgs as $i => $im): ?>
            <div class="carousel-item <?= $i ? '' : 'active' ?>"><img src="<?= e(thumb($im['image_path'])) ?>" class="d-block w-100 detail-img" alt="<?= e($l['title']) ?> photo <?= $i + 1 ?>"></div>
          <?php endforeach; ?>
        </div>
        <?php if (count($imgs) > 1): ?>
          <button class="carousel-control-prev" type="button" data-bs-target="#car" data-bs-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden">Previous photo</span></button>
          <button class="carousel-control-next" type="button" data-bs-target="#car" data-bs-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden">Next photo</span></button>
        <?php endif; ?>
      </div>
      <?php if (count($imgs) > 1): ?>
        <div class="gallery-thumbs">
          <?php foreach ($imgs as $i => $im): ?>
            <button type="button" class="gallery-thumb<?= $i ? '' : ' active' ?>" data-bs-target="#car" data-bs-slide-to="<?= $i ?>" aria-label="Show photo <?= $i + 1 ?>">
              <img src="<?= e(thumb($im['image_path'])) ?>" alt="">
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="detail-empty">
        <div>
          <span class="detail-empty-icon"><i class="bi <?= e(cat_icon($l['category'])) ?>"></i></span>
          <p class="meta mb-0">No photo available.</p>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-5">
    <div class="detail-tag-row">
      <span class="chip"><i class="bi bi-tag"></i><?= e(ucfirst($l['listing_type'])) ?></span>
      <?= status_badge($l['status']) ?>
    </div>
    <h1 class="detail-title"><?= e($l['title']) ?></h1>
    <div class="detail-price"><?= e(format_price($l['price'], $l['listing_type'])) ?></div>
    <div class="detail-facts">
      <span class="fact-chip"><i class="bi bi-geo-alt"></i><?= e(CAMPUS_NAME) ?></span>
      <span class="fact-chip"><i class="bi bi-clock"></i>Posted <?= e(listing_when($l['created_at'])) ?></span>
      <?php if ($l['item_condition']): ?><span class="fact-chip"><i class="bi bi-stars"></i><?= e($conds[$l['item_condition']] ?? ucfirst($l['item_condition'])) ?></span><?php endif; ?>
    </div>
    <p class="detail-desc mb-4"><?= e($l['description']) ?></p>

    <div class="card sc-card seller-card">
      <div class="seller-head mb-3">
        <span class="avatar"><?= e(initials($l['seller_name'])) ?></span>
        <div class="min-w-0">
          <a class="seller-name" href="<?= $sellerProfileUrl ?>"><?= e($l['seller_name']) ?></a>
          <div class="meta">Joined <?= e(date('F Y', strtotime($l['seller_since']))) ?> &middot; <a href="<?= $sellerProfileUrl ?>">View profile</a></div>
        </div>
      </div>
      <?php if ($sellerBadges): ?><?= badge_chips($sellerBadges) ?><?php endif; ?>
      <?php if ($l['status'] !== 'available'): ?>
        <div class="sc-note mt-3"><i class="bi bi-info-circle me-1"></i>This listing is no longer available.</div>
      <?php elseif (!$me): ?>
        <a class="btn btn-primary w-100 py-2 mt-3" href="<?= url('login.php') ?>"><i class="bi bi-chat-dots"></i> Log in to message the seller</a>
      <?php elseif ($isOwner): ?>
        <a class="btn btn-outline-secondary w-100 py-2 mt-3" href="<?= url('edit-listing.php?id=' . $id) ?>"><i class="bi bi-pencil"></i> Edit listing</a>
      <?php else: ?>
        <form method="post" action="<?= url('chat-start.php') ?>" class="mt-3">
          <?= csrf_field() ?><input type="hidden" name="listing" value="<?= $id ?>">
          <button class="btn btn-primary w-100 py-2"><i class="bi bi-chat-dots"></i> Message seller</button>
        </form>
        <a class="btn btn-outline-secondary w-100 py-2 mt-2" target="_blank" rel="noopener" href="<?= e(contact_link($m, $l['contact_value'], $l['title'], absolute_url('listing.php?id=' . $id))) ?>"><i class="bi bi-<?= $mIcon ?>"></i> Contact via <?= e($mLabel) ?></a>
        <p class="meta mt-3 mb-0 text-center"><?= e($l['contact_value']) ?></p>
      <?php endif; ?>
      <?php if ($me && !$isOwner): ?>
        <div class="d-flex gap-2 mt-3 pt-3 border-top">
          <a class="btn btn-sm btn-outline-danger flex-grow-1" href="<?= url('report-listing.php?id=' . $id) ?>"><i class="bi bi-flag"></i> Report listing</a>
          <a class="btn btn-sm btn-outline-secondary flex-grow-1" href="<?= url('report-user.php?reported=' . $l['seller_user_id'] . '&context=profile&listing=' . $id) ?>"><i class="bi bi-person-exclamation"></i> Report seller</a>
        </div>
      <?php endif; ?>
    </div>
    <p class="safety-note mt-3"><i class="bi bi-shield-check"></i><span>Meet in a public campus spot and never pay in advance.</span></p>
  </div>
</div>
<script>
document.getElementById('car')?.addEventListener('slid.bs.carousel', function (e) {
  document.querySelectorAll('.gallery-thumb').forEach(function (t, i) { t.classList.toggle('active', i === e.to); });
});
</script>
<?php require __DIR__ . '/includes/footer.php';