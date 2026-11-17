<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$s = [
  'Registered users'   => q("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(),
  'Pending approval'   => q("SELECT COUNT(*) FROM listings WHERE moderation_status='pending'")->fetchColumn(),
  'Live listings'      => q("SELECT COUNT(*) FROM listings WHERE moderation_status='approved' AND status='available'")->fetchColumn(),
  'Open reports'       => q("SELECT COUNT(*) FROM listing_reports WHERE status='open'")->fetchColumn(),
];
$pending = q("SELECT l.id, l.title, l.created_at, u.full_name FROM listings l JOIN users u ON u.id = l.seller_id WHERE l.moderation_status='pending' ORDER BY l.created_at LIMIT 8")->fetchAll();
$logs = q("SELECT m.*, u.full_name AS admin, l.title FROM moderation_logs m JOIN users u ON u.id = m.admin_id JOIN listings l ON l.id = m.listing_id ORDER BY m.created_at DESC LIMIT 8")->fetchAll();
$meta = [
  'Registered users' => ['people', 1],
  'Pending approval' => ['hourglass-split', 3],
  'Live listings'    => ['shop-window', 2],
  'Open reports'     => ['flag', 4],
];
$pageTitle = 'Admin dashboard'; require __DIR__ . '/../includes/header.php'; ?>
<div class="page-head">
  <div>
    <h1 class="page-title">Dashboard</h1>
  </div>
</div>
<?php include __DIR__ . '/_nav.php'; ?>
<div class="row g-3 mb-4">
  <?php foreach ($s as $label => $v): [$ic, $tone] = $meta[$label] ?? ['dot', 1]; ?>
    <div class="col-6 col-xl-3">
      <div class="card stat-card">
        <div class="card-body">
          <span class="stat-icon stat-tone-<?= $tone ?>"><i class="bi bi-<?= $ic ?>"></i></span>
          <div>
            <div class="num"><?= (int)$v ?></div>
            <div class="stat-label"><?= e($label) ?></div>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="panel h-100">
      <div class="panel-head">
        <h2>Awaiting review</h2>
        <a class="section-link" href="<?= url('admin/listings.php') ?>">Review all <i class="bi bi-arrow-right"></i></a>
      </div>
      <ul class="list-group list-group-flush">
        <?php foreach ($pending as $p): ?>
          <li class="list-group-item d-flex justify-content-between gap-2">
            <span class="min-w-0"><a class="row-title d-block text-truncate" href="<?= url('admin/inspect.php?' . http_build_query(['id' => $p['id'], 'back' => 'pending'])) ?>"><?= e($p['title']) ?></a>
              <span class="row-sub d-block">by <?= e($p['full_name']) ?></span></span>
            <span class="meta flex-none"><?= e(date('j M', strtotime($p['created_at']))) ?></span>
          </li>
        <?php endforeach; if (!$pending): ?><li class="list-group-item text-body-secondary">Nothing pending.</li><?php endif; ?>
      </ul>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="panel h-100">
      <div class="panel-head"><h2>Recent moderation actions</h2></div>
      <ul class="list-group list-group-flush">
        <?php $actLabel = ['approve' => 'approved', 'reject' => 'rejected', 'remove' => 'removed', 'restore' => 'restored', 'inspect' => 'inspected']; ?>
        <?php foreach ($logs as $g): ?>
          <li class="list-group-item small"><strong><?= e($g['admin']) ?></strong> <?= e($actLabel[$g['action']] ?? $g['action']) ?> <?= e($g['title']) ?> <span class="text-body-secondary"><?= e(date('j M H:i', strtotime($g['created_at']))) ?></span></li>
        <?php endforeach; if (!$logs): ?><li class="list-group-item text-body-secondary">No actions yet.</li><?php endif; ?>
      </ul>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php';