<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

// Date range filter (applies to reports 1 and 3). Defaults to the last 30 days.
$valid = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
$to   = $valid(clean('to', 'GET'))   ? clean('to', 'GET')   : date('Y-m-d');
$from = $valid(clean('from', 'GET')) ? clean('from', 'GET') : date('Y-m-d', strtotime('-30 days'));
if ($from > $to) [$from, $to] = [$to, $from];
$range = [$from . ' 00:00:00', $to . ' 23:59:59'];

// REPORT 1: Listing inventory (availability x moderation status), listings created in range
$inv = q("SELECT moderation_status, status, COUNT(*) AS n FROM listings WHERE created_at BETWEEN ? AND ? GROUP BY moderation_status, status", $range)->fetchAll();
$grid = []; $totalInv = 0;
foreach ($inv as $r) { $grid[$r['moderation_status']][$r['status']] = (int)$r['n']; $totalInv += (int)$r['n']; }
$mods = ['pending', 'approved', 'rejected', 'removed']; $avail = ['available', 'sold', 'closed'];

// REPORT 2: Category performance (all approved listings; NULL prices excluded from price stats)
$cat = q("SELECT c.listing_type, c.name, COUNT(l.id) AS n, COUNT(l.price) AS priced, ROUND(AVG(l.price),2) AS avg_p, MIN(l.price) AS min_p, MAX(l.price) AS max_p
          FROM categories c LEFT JOIN listings l ON l.category_id = c.id AND l.moderation_status = 'approved'
          GROUP BY c.id, c.listing_type, c.name ORDER BY c.listing_type, n DESC, c.name")->fetchAll();

// REPORT 3: User and seller activity
$totUsers = (int)q("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$newUsers = (int)q("SELECT COUNT(*) FROM users WHERE role='student' AND created_at BETWEEN ? AND ?", $range)->fetchColumn();
$newListings = (int)q('SELECT COUNT(*) FROM listings WHERE created_at BETWEEN ? AND ?', $range)->fetchColumn();
// Active seller = user with at least one approved listing created in the selected period
$activeSellers = (int)q("SELECT COUNT(DISTINCT seller_id) FROM listings WHERE moderation_status='approved' AND created_at BETWEEN ? AND ?", $range)->fetchColumn();
$perSeller = q("SELECT u.full_name, u.email, COUNT(l.id) AS total, SUM(l.moderation_status='approved') AS approved, SUM(l.status='sold') AS sold
                FROM users u JOIN listings l ON l.seller_id = u.id WHERE l.created_at BETWEEN ? AND ?
                GROUP BY u.id, u.full_name, u.email ORDER BY total DESC, u.full_name LIMIT 50", $range)->fetchAll();

$pageTitle = 'Reports'; require __DIR__ . '/../includes/header.php'; ?>
<div class="page-head d-print-none">
  <div>
    <h1 class="page-title">Reports</h1>
  </div>
</div>
<div class="d-print-none"><?php include __DIR__ . '/_nav.php'; ?></div>
<form class="sc-card filter-panel mb-3 d-print-none">
  <div class="row g-3 align-items-end">
    <div class="col-6 col-md-3">
      <label class="form-label" for="rp-from">From</label>
      <input type="date" class="form-control" id="rp-from" name="from" value="<?= e($from) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label" for="rp-to">To</label>
      <input type="date" class="form-control" id="rp-to" name="to" value="<?= e($to) ?>">
    </div>
    <div class="col-12 col-md-6 d-flex gap-2">
      <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
      <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
    </div>
  </div>
</form>
<p class="meta mb-4">Period: <?= e($from) ?> to <?= e($to) ?>. Counts are listings, not sales.</p>

<div class="panel mb-4">
  <div class="panel-head"><h2>Listing inventory</h2></div>
  <div class="panel-body table-responsive">
    <table class="table table-hover text-center mb-0"><thead><tr><th class="text-start">Moderation \ Availability</th><?php foreach ($avail as $a): ?><th><?= ucfirst($a) ?></th><?php endforeach; ?><th>Total</th></tr></thead><tbody>
    <?php foreach ($mods as $m): $rowT = 0; ?>
      <tr><th class="text-start text-capitalize"><?= ucfirst($m) ?></th>
      <?php foreach ($avail as $a): $v = $grid[$m][$a] ?? 0; $rowT += $v; ?><td><?= $v ?></td><?php endforeach; ?><td class="fw-bold"><?= $rowT ?></td></tr>
    <?php endforeach; ?>
    <tr class="table-active"><th class="text-start">Total listings</th><?php foreach ($avail as $a): $c = 0; foreach ($mods as $m) $c += $grid[$m][$a] ?? 0; ?><td><?= $c ?></td><?php endforeach; ?><td class="fw-bold"><?= $totalInv ?></td></tr>
    </tbody></table>
  </div>
</div>

<div class="panel mb-4">
  <div class="panel-head"><h2>Category performance</h2><span class="meta">All approved listings</span></div>
  <div class="panel-body table-responsive">
    <table class="table table-hover mb-0"><thead><tr><th>Type</th><th>Category</th><th class="text-end">Listings</th><th class="text-end">With price</th><th class="text-end">Avg (KSh)</th><th class="text-end">Min</th><th class="text-end">Max</th></tr></thead><tbody>
    <?php $sub = []; foreach ($cat as $c): $sub[$c['listing_type']] = ($sub[$c['listing_type']] ?? 0) + $c['n']; ?>
      <tr><td><span class="chip text-capitalize"><?= e($c['listing_type']) ?></span></td><td><?= e($c['name']) ?></td><td class="text-end"><?= (int)$c['n'] ?></td><td class="text-end"><?= (int)$c['priced'] ?></td>
        <td class="text-end"><?= $c['avg_p'] !== null ? number_format((float)$c['avg_p'], 0) : '-' ?></td><td class="text-end"><?= $c['min_p'] !== null ? number_format((float)$c['min_p'], 0) : '-' ?></td><td class="text-end"><?= $c['max_p'] !== null ? number_format((float)$c['max_p'], 0) : '-' ?></td></tr>
    <?php endforeach; ?>
    <tr class="table-active"><td colspan="2">Products total / Services total</td><td class="text-end fw-bold" colspan="5"><?= (int)($sub['product'] ?? 0) ?> / <?= (int)($sub['service'] ?? 0) ?></td></tr>
    </tbody></table>
  </div>
</div>

<div class="panel mb-4">
  <div class="panel-head"><h2>User and seller activity</h2></div>
  <div class="panel-body">
    <div class="row g-3 mb-3">
      <div class="col-6 col-md-3"><div class="sc-card text-center h-100"><div class="num"><?= $totUsers ?></div><div class="stat-label">Registered users (all time)</div></div></div>
      <div class="col-6 col-md-3"><div class="sc-card text-center h-100"><div class="num"><?= $newUsers ?></div><div class="stat-label">New users in period</div></div></div>
      <div class="col-6 col-md-3"><div class="sc-card text-center h-100"><div class="num"><?= $newListings ?></div><div class="stat-label">New listings in period</div></div></div>
      <div class="col-6 col-md-3"><div class="sc-card text-center h-100"><div class="num"><?= $activeSellers ?></div><div class="stat-label">Active sellers*</div></div></div>
    </div>
    <p class="meta">*Active seller: a user with at least one approved listing created during the selected period.</p>
    <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Seller</th><th>Email</th><th class="text-end">Listings</th><th class="text-end">Approved</th><th class="text-end">Marked sold</th></tr></thead><tbody>
    <?php foreach ($perSeller as $s): ?><tr><td class="row-title"><?= e($s['full_name']) ?></td><td class="text-body-secondary"><?= e($s['email']) ?></td><td class="text-end"><?= (int)$s['total'] ?></td><td class="text-end"><?= (int)$s['approved'] ?></td><td class="text-end"><?= (int)$s['sold'] ?></td></tr>
    <?php endforeach; if (!$perSeller): ?><tr><td colspan="5" class="text-body-secondary">No listings in this period.</td></tr><?php endif; ?></tbody></table></div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php';