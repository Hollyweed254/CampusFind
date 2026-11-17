<?php
$pageTitle = null;
require __DIR__ . '/includes/header.php';
$cats = categories_all();
$featured = q("SELECT l.*, c.name AS category, c.listing_type, u.full_name AS seller,
                      (SELECT image_path FROM listing_images WHERE listing_id = l.id ORDER BY id LIMIT 1) AS img
               FROM listings l
               JOIN categories c ON c.id = l.category_id
               JOIN users u ON u.id = l.seller_id
               WHERE l.moderation_status='approved' AND l.status='available' AND u.status='active'
               ORDER BY l.created_at DESC LIMIT 8")->fetchAll();
$services = q("SELECT l.*, c.name AS category, c.listing_type, u.full_name AS seller,
                      (SELECT image_path FROM listing_images WHERE listing_id = l.id ORDER BY id LIMIT 1) AS img
               FROM listings l
               JOIN categories c ON c.id = l.category_id
               JOIN users u ON u.id = l.seller_id
               WHERE l.moderation_status='approved' AND l.status='available' AND c.listing_type='service' AND u.status='active'
               ORDER BY l.created_at DESC LIMIT 4")->fetchAll();
$catCounts = [];
foreach (q("SELECT l.category_id, COUNT(*) AS n FROM listings l JOIN users u ON u.id = l.seller_id
            WHERE l.moderation_status='approved' AND l.status='available' AND u.status='active' GROUP BY l.category_id")->fetchAll() as $r) {
    $catCounts[(int)$r['category_id']] = (int)$r['n'];
}
$popularCats = [];
foreach ($cats as $c) {
    if (($catCounts[$c['id']] ?? 0) > 0) $popularCats[] = $c;
}
if (!$popularCats) $popularCats = array_values($cats);
?>
<section class="hero home-hero" aria-labelledby="hero-title">
  <div class="container">
    <div class="hh-inner">
      <span class="hh-badge"><i class="bi bi-mortarboard"></i>University Marketplace</span>
      <h1 class="hh-title" id="hero-title">Everything campus.<br><span class="accent">One marketplace.</span></h1>
      <p class="hh-lead">Buy and sell textbooks, tech, hostel essentials and student services with people on your campus.</p>

      <form action="<?= url('marketplace.php') ?>" method="get" class="hh-search">
        <div class="hh-field hh-q"><i class="bi bi-search"></i><input name="q" placeholder="Search for laptops, books, services&hellip;" aria-label="Search listings" maxlength="80"></div>
        <div class="hh-field hh-cat"><i class="bi bi-grid"></i><select name="category" aria-label="Category">
          <option value="">All categories</option>
          <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
        <button class="btn hh-btn">Search<i class="bi bi-arrow-right"></i></button>
      </form>

      <div class="hh-popular">
        <span>Popular searches:</span>
        <a href="<?= url('marketplace.php?q=Laptop') ?>"><i class="bi bi-laptop"></i>Laptops</a>
        <a href="<?= url('marketplace.php?q=Textbook') ?>"><i class="bi bi-book"></i>Textbooks</a>
        <a href="<?= url('marketplace.php?q=Clothing') ?>"><i class="bi bi-handbag"></i>Clothing</a>
        <a href="<?= url('marketplace.php?type=service') ?>"><i class="bi bi-tools"></i>Services</a>
      </div>

      <div class="hh-trust">
        <div class="hh-trust-item">
          <span class="hh-trust-icon"><i class="bi bi-shield-check"></i></span>
          <span><strong>Safe &amp; Secure</strong><span>Trade with confidence</span></span>
        </div>
        <div class="hh-trust-item">
          <span class="hh-trust-icon"><i class="bi bi-people-fill"></i></span>
          <span><strong>Campus Community</strong><span>Connect with students</span></span>
        </div>
        <div class="hh-trust-item">
          <span class="hh-trust-icon"><i class="bi bi-lightning-charge-fill"></i></span>
          <span><strong>Fast &amp; Easy</strong><span>List in minutes</span></span>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="home-section" id="categories">
  <div class="section-head">
    <div>
      <h2 class="section-title mb-0">Popular categories</h2>
    </div>
    <a class="section-link" href="<?= url('marketplace.php') ?>">All listings <i class="bi bi-arrow-right"></i></a>
  </div>
  <div class="cat-grid">
    <?php $tone = 0; foreach ($popularCats as $c): ?>
      <a class="cat-tile cat-tone-<?= ($tone++ % 4) + 1 ?>" href="<?= url('marketplace.php?category=' . $c['id']) ?>">
        <span class="cat-tile-top">
          <span class="cat-tile-icon"><i class="bi <?= e(cat_icon($c['name'])) ?>"></i></span>
          <span class="cat-tile-arrow" aria-hidden="true"><i class="bi bi-arrow-up-right"></i></span>
        </span>
        <span class="cat-tile-name"><?= e($c['name']) ?></span>
        <span class="cat-tile-meta"><?= (int)($catCounts[$c['id']] ?? 0) ?> listing<?= ($catCounts[$c['id']] ?? 0) === 1 ? '' : 's' ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="home-section">
  <div class="section-head">
    <div>
      <h2 class="section-title mb-0">Latest listings</h2>
    </div>
    <a class="section-link" href="<?= url('marketplace.php') ?>">All listings <i class="bi bi-arrow-right"></i></a>
  </div>
  <?php if (!$featured): ?>
    <div class="empty-state">
      <div class="empty-state-icon"><i class="bi bi-shop-window"></i></div>
      <h3>No listings yet</h3>
      <p>Be the first to post something on the campus marketplace.</p>
      <a class="btn btn-primary" href="<?= url('create-listing.php') ?>"><i class="bi bi-plus-lg"></i> Post a listing</a>
    </div>
  <?php else: ?>
    <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3 g-lg-4">
      <?php foreach ($featured as $l) include __DIR__ . '/includes/card.php'; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($services): ?>
<section class="home-section soft-band">
  <div class="section-head">
    <div>
      <h2 class="section-title mb-0">Student services</h2>
    </div>
    <a class="section-link" href="<?= url('marketplace.php?type=service') ?>">All services <i class="bi bi-arrow-right"></i></a>
  </div>
  <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3 g-lg-4">
    <?php foreach ($services as $l) include __DIR__ . '/includes/card.php'; ?>
  </div>
</section>
<?php endif; ?>

<section class="cta-band home-section">
  <div>
    <h2>Have something gathering dust?</h2>
    <p>List it and find a buyer down the hall.</p>
  </div>
  <a class="btn-lime" href="<?= url($me ? 'create-listing.php' : 'register.php') ?>">
    <i class="bi bi-plus-lg"></i><?= $me ? 'Post a listing' : 'Join CampusFind' ?>
  </a>
</section>
<?php require __DIR__ . '/includes/footer.php';