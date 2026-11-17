<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/trust.php';
require_once __DIR__ . '/includes/transactions.php';

$id = (int)($_GET['id'] ?? 0);
$seller = seller_public($id);
$me = current_user();
if (!$seller || $seller['status'] !== 'active') {
    http_response_code(404); $pageTitle = 'Seller not found'; require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><div class="empty-state-icon"><i class="bi bi-person-x"></i></div><h1 class="h3">Seller not found</h1><p>This account is unavailable.</p><a class="btn btn-primary" href="' . url('marketplace.php') . '">Back to marketplace</a></div>';
    require __DIR__ . '/includes/footer.php'; exit;
}
$stats = seller_stats($id);
$badges = seller_badges($seller);
$reviews = seller_reviews($id, 8);
$listings = q("SELECT l.*, c.name AS category, c.listing_type, u.full_name AS seller,
                      (SELECT image_path FROM listing_images WHERE listing_id = l.id ORDER BY id LIMIT 1) AS img
               FROM listings l JOIN categories c ON c.id = l.category_id JOIN users u ON u.id = l.seller_id
               WHERE l.seller_id = ? AND l.moderation_status = 'approved' AND l.status = 'available'
               ORDER BY l.created_at DESC LIMIT 12", [$id])->fetchAll();
$isSelf = $me && (int)$me['id'] === $id;
$pageTitle = $seller['full_name']; require __DIR__ . '/includes/header.php'; ?>
<nav class="listing-breadcrumb" aria-label="Breadcrumb">
  <a href="<?= url('marketplace.php') ?>">Marketplace</a>
  <i class="bi bi-chevron-right"></i>
  <span>Seller</span>
</nav>

<div class="panel mb-4">
  <div class="panel-body">
    <div class="d-flex flex-wrap align-items-start gap-3">
      <span class="avatar avatar-lg"><?= e(initials($seller['full_name'])) ?></span>
      <div class="flex-grow-1 min-w-0">
        <h1 class="page-title mb-1"><?= e($seller['full_name']) ?></h1>
        <p class="meta mb-2">Joined <?= e(date('F Y', strtotime($seller['created_at']))) ?> &middot; <?= e(CAMPUS_NAME) ?></p>
        <?= badge_chips($badges) ?>
        <?php if (!$badges): ?><span class="st st-muted"><?= new_seller($stats) ? 'New seller' : 'No public badges yet' ?></span><?php endif; ?>
      </div>
      <div class="d-flex gap-2">
        <?php if ($isSelf): ?>
          <a class="btn btn-outline-primary" href="<?= url('verification.php') ?>"><i class="bi bi-patch-check"></i> Verification</a>
        <?php elseif ($me): ?>
          <a class="btn btn-outline-danger" href="<?= url('report-user.php?reported=' . $id . '&context=profile') ?>"><i class="bi bi-flag"></i> Report seller</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="seller-stats mt-4">
      <div><strong><?= (int)$stats['completed'] ?></strong><span>Completed</span></div>
      <div><strong><?= (int)$stats['active'] ?></strong><span>Active listings</span></div>
      <div><strong><?= $stats['rating'] !== null ? e(number_format($stats['rating'], 1)) : '&mdash;' ?></strong><span>Rating<?= $stats['review_count'] ? ' (' . (int)$stats['review_count'] . ')' : '' ?></span></div>
      <div><strong><?= $stats['disputes'] ?></strong><span>Open disputes</span></div>
    </div>

    <?php if ($stats['completed'] === 0): ?>
      <div class="sc-note mt-3 mb-0">New seller: no completed transactions yet.</div>
    <?php endif; ?>
    <?php $trusted = trusted_seller($seller, $stats); ?>
    <?php if (!$trusted && !$isSelf): ?>
      <p class="meta mt-3 mb-0">Trusted seller is earned with <?= (int)TRUSTED_MIN_COMPLETED ?> completed transactions, <?= (int)TRUSTED_MIN_RATING_COUNT ?> reviews and a <?= number_format(TRUSTED_MIN_RATING, 1) ?>+ average, with no open reports or disputes.</p>
    <?php endif; ?>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="h5 mb-0">Active listings</h2>
      <span class="meta"><?= count($listings) ?> shown</span>
    </div>
    <?php if ($listings): ?>
      <div class="row row-cols-1 row-cols-md-2 g-3">
        <?php foreach ($listings as $l): include __DIR__ . '/includes/card.php'; endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state"><div class="empty-state-icon"><i class="bi bi-shop"></i></div><h2 class="h5">No active listings</h2><p>This seller has nothing available right now.</p></div>
    <?php endif; ?>
  </div>
  <div class="col-lg-5">
    <h2 class="h5 mb-3">Reviews</h2>
    <?php if ($reviews): ?>
      <div class="review-list">
        <?php foreach ($reviews as $r): ?>
          <article class="review-item">
            <div class="d-flex justify-content-between gap-2">
              <strong><?= e($r['reviewer_name']) ?></strong>
              <span class="review-stars" aria-label="<?= (int)$r['rating'] ?> out of 5"><?php for ($i = 1; $i <= 5; $i++): ?><i class="bi bi-star<?= $i <= (int)$r['rating'] ? '-fill' : '' ?>"></i><?php endfor; ?></span>
            </div>
            <div class="meta mb-1"><?= e($r['title']) ?> &middot; <?= e(date('j M Y', strtotime($r['created_at']))) ?></div>
            <?php if (!empty($r['comment'])): ?><p class="mb-0"><?= e($r['comment']) ?></p><?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="sc-note">Reviews appear after completed transactions.</div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';