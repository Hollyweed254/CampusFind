<?php /* expects $l with title, price, listing_type, category, img, seller, item_condition, created_at, moderation_status */ ?>
<?php
$tone = ((int)($l['category_id'] ?? 0) % 4) + 1;
$photo = listing_photo($l['img'] ?? null);
$condition = condition_label($l['item_condition'] ?? null);
$seller = trim((string)($l['seller'] ?? ''));
$unapproved = ($l['moderation_status'] ?? 'approved') !== 'approved';
?>
<div class="col">
  <a href="<?= url('listing.php?id=' . $l['id']) ?>" class="listing-link d-block h-100">
    <article class="card listing-card h-100">
      <div class="thumb">
        <span class="lc-badge"><i class="bi <?= e(cat_icon($l['category'])) ?>"></i><?= e($l['category']) ?></span>
        <?php if ($photo): ?>
          <img src="<?= e($photo) ?>" alt="<?= e($l['title']) ?>" loading="lazy">
        <?php else: ?>
          <?php $fallbackCategory = $l['category']; $fallbackTone = $tone; include __DIR__ . '/fallback_art.php'; ?>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <h3 class="lc-title"><?= e($l['title']) ?></h3>
        <div class="lc-buy">
          <div class="lc-price"><?= e(format_price($l['price'], $l['listing_type'])) ?></div>
          <?php if ($unapproved): ?>
            <?= status_badge($l['moderation_status']) ?>
          <?php elseif ($condition): ?>
            <span class="lc-cond"><?= e($condition) ?></span>
          <?php endif; ?>
        </div>
        <?php if ($seller !== ''): ?>
          <div class="lc-seller">
            <span class="lc-avatar" aria-hidden="true"><?= e(initials($seller)) ?></span>
            <span class="lc-seller-name"><?= e($seller) ?></span>
            <span class="lc-date">&middot; <?= e(listing_when($l['created_at'])) ?></span>
          </div>
        <?php endif; ?>
        <div class="lc-foot">
          <span class="lc-foot-main"><i class="bi bi-geo-alt"></i><span><?= e(CAMPUS_NAME) ?></span></span>
          <span class="lc-arrow" aria-hidden="true"><i class="bi bi-arrow-up-right"></i></span>
        </div>
      </div>
    </article>
  </a>
</div>
