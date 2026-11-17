<?php
$pageTitle = 'Marketplace';
require __DIR__ . '/includes/header.php';
$cats = categories_all();

$qtxt = clean('q', 'GET'); $cat = (int)clean('category', 'GET'); $type = clean('type', 'GET');
$min = clean('min', 'GET'); $max = clean('max', 'GET'); $sort = clean('sort', 'GET');
$page = max(1, (int)clean('page', 'GET'));

$where = ["l.moderation_status = 'approved'", "l.status = 'available'", "u.status = 'active'"]; $p = [];
if ($qtxt !== '') { $where[] = '(l.title LIKE ? OR l.description LIKE ?)'; $like = '%' . addcslashes($qtxt, '%_\\') . '%'; array_push($p, $like, $like); }
if ($cat && isset($cats[$cat])) { $where[] = 'l.category_id = ?'; $p[] = $cat; }
if (in_array($type, ['product', 'service'], true)) { $where[] = 'c.listing_type = ?'; $p[] = $type; }
if (is_numeric($min)) { $where[] = 'l.price >= ?'; $p[] = $min; }
if (is_numeric($max)) { $where[] = 'l.price <= ?'; $p[] = $max; }
$order = ['price_asc' => 'l.price IS NULL, l.price ASC', 'price_desc' => 'l.price DESC', 'new' => 'l.created_at DESC'][$sort] ?? 'l.created_at DESC';
$W = implode(' AND ', $where);

$total = (int)q("SELECT COUNT(*) FROM listings l JOIN categories c ON c.id = l.category_id JOIN users u ON u.id = l.seller_id WHERE $W", $p)->fetchColumn();
$pages = max(1, (int)ceil($total / PER_PAGE)); $page = min($page, $pages);
$off = ($page - 1) * PER_PAGE;
$rows = q("SELECT l.*, c.name AS category, c.listing_type, u.full_name AS seller,
           (SELECT image_path FROM listing_images WHERE listing_id = l.id ORDER BY id LIMIT 1) AS img
           FROM listings l
           JOIN categories c ON c.id = l.category_id
           JOIN users u ON u.id = l.seller_id
           WHERE $W ORDER BY $order LIMIT " . PER_PAGE . " OFFSET $off", $p)->fetchAll();

$qs = $_GET; unset($qs['page']);
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Browse listings</h1>
  </div>
</div>

<form method="get" class="sc-card market-filter mb-4" aria-label="Filter listings">
  <div class="filter-bar">
    <div class="filter-field filter-keyword">
      <label class="visually-hidden" for="f-q">Keyword</label>
      <i class="bi bi-search" aria-hidden="true"></i>
      <input class="form-control" id="f-q" name="q" value="<?= e($qtxt) ?>" placeholder="Search listings" maxlength="80">
    </div>
    <div class="filter-field">
      <label class="visually-hidden" for="f-cat">Category</label>
      <select class="form-select" id="f-cat" name="category">
        <option value="">All categories</option>
        <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="filter-field">
      <label class="visually-hidden" for="f-type">Type</label>
      <select class="form-select" id="f-type" name="type">
        <option value="">All types</option>
        <option value="product" <?= $type === 'product' ? 'selected' : '' ?>>Products</option>
        <option value="service" <?= $type === 'service' ? 'selected' : '' ?>>Services</option>
      </select>
    </div>
    <div class="filter-field">
      <label class="visually-hidden" for="f-sort">Sort by</label>
      <select class="form-select" id="f-sort" name="sort">
        <option value="new" <?= $sort === '' || $sort === 'new' ? 'selected' : '' ?>>Newest first</option>
        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
      </select>
    </div>
    <div class="filter-price">
      <label class="visually-hidden" for="f-min">Minimum price in KSh</label>
      <input class="form-control" id="f-min" type="number" min="0" name="min" value="<?= e($min) ?>" placeholder="Min KSh" aria-label="Minimum price">
      <label class="visually-hidden" for="f-max">Maximum price in KSh</label>
      <input class="form-control" id="f-max" type="number" min="0" name="max" value="<?= e($max) ?>" placeholder="Max KSh" aria-label="Maximum price">
    </div>
    <button class="btn btn-primary filter-submit"><i class="bi bi-funnel" aria-hidden="true"></i><span>Apply</span></button>
    <a class="filter-reset" href="<?= url('marketplace.php') ?>" aria-label="Reset filters" title="Reset filters"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></a>
  </div>
</form>

<div class="results-bar">
  <p><strong><?= $total ?></strong> listing<?= $total === 1 ? '' : 's' ?> found</p>
  <?php if ($qtxt !== '' || $cat || in_array($type, ['product', 'service'], true) || $min !== '' || $max !== ''): ?>
    <div class="chip-row">
      <?php if ($qtxt !== ''): ?><span class="chip"><i class="bi bi-search"></i><?= e($qtxt) ?></span><?php endif; ?>
      <?php if ($cat && isset($cats[$cat])): ?><span class="chip"><i class="bi bi-grid"></i><?= e($cats[$cat]['name']) ?></span><?php endif; ?>
      <?php if (in_array($type, ['product', 'service'], true)): ?><span class="chip"><i class="bi bi-tag"></i><?= e(ucfirst($type)) ?>s</span><?php endif; ?>
      <?php if ($min !== '' || $max !== ''): ?><span class="chip"><i class="bi bi-cash"></i>KSh <?= e($min !== '' ? $min : '0') ?> &ndash; <?= e($max !== '' ? $max : 'any') ?></span><?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if (!$rows): ?>
  <div class="empty-state">
    <div class="empty-state-icon"><i class="bi bi-search"></i></div>
    <h2>No listings found</h2>
    <p>Try another search or clear the filters.</p>
    <a class="btn btn-outline-primary" href="<?= url('marketplace.php') ?>">Clear filters</a>
  </div>
<?php else: ?>
  <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 row-cols-xxl-5 g-3 g-lg-4">
    <?php foreach ($rows as $l) include __DIR__ . '/includes/card.php'; ?>
  </div>
  <?php if ($pages > 1): ?>
  <nav class="mt-4" aria-label="Listing pages"><ul class="pagination justify-content-center">
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?<?= e(http_build_query($qs + ['page' => max(1, $page - 1)])) ?>" aria-label="Previous page">&lsaquo;</a></li>
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?<?= e(http_build_query($qs + ['page' => $i])) ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a></li>
    <?php endfor; ?>
    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="?<?= e(http_build_query($qs + ['page' => min($pages, $page + 1)])) ?>" aria-label="Next page">&rsaquo;</a></li>
  </ul></nav>
  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php';