<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/trust.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $id = (int)($_POST['id'] ?? 0);
    $act = (string)($_POST['action'] ?? '');
    $note = mb_substr(clean('note'), 0, 500);
    $r = q('SELECT * FROM user_reports WHERE id = ?', [$id])->fetch();
    if ($r && $r['status'] === 'open' && in_array($act, ['dismiss', 'resolve', 'suspend'], true)) {
        $newStatus = $act === 'dismiss' ? 'dismissed' : 'resolved';
        q('UPDATE user_reports SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?',
          [$newStatus, $note !== '' ? $note : null, $admin['id'], $id]);
        if ($act === 'suspend' && (int)$r['reported_user_id'] !== (int)$admin['id']) {
            q("UPDATE users SET status = 'suspended', suspended_at = NOW(), suspension_reason = ? WHERE id = ? AND role = 'student'",
              ['Suspended after report #' . $id . ($note !== '' ? ': ' . $note : ''), $r['reported_user_id']]);
        }
        audit_log((int)$admin['id'], 'report_' . $act, 'user_report', $id, $note);
        flash('success', 'Report ' . ($act === 'dismiss' ? 'dismissed' : 'resolved') . '.');
    }
    redirect('admin/user-reports.php');
}

$show = ($_GET['show'] ?? 'open') === 'all' ? 'all' : 'open';
$rows = q("SELECT r.*, rep.full_name AS reporter_name, tar.full_name AS reported_name, tar.status AS reported_status,
                  l.title AS listing_title
           FROM user_reports r
           JOIN users rep ON rep.id = r.reporter_id
           JOIN users tar ON tar.id = r.reported_user_id
           LEFT JOIN listings l ON l.id = r.listing_id
           " . ($show === 'open' ? "WHERE r.status = 'open'" : '') . "
           ORDER BY r.created_at DESC LIMIT 200")->fetchAll();
$pageTitle = 'Abuse reports'; require __DIR__ . '/../includes/header.php'; ?>
<div class="page-head">
  <div>
    <h1 class="page-title">Abuse reports</h1>
  </div>
</div>
<?php include __DIR__ . '/_nav.php'; ?>
<div class="seg-control mb-4">
  <a class="btn <?= $show === 'open' ? 'active' : '' ?>" href="?show=open">Open</a>
  <a class="btn <?= $show === 'all' ? 'active' : '' ?>" href="?show=all">All</a>
</div>
<?php if ($rows): ?>
  <div class="table-responsive table-card"><table class="table align-middle mb-0">
    <thead><tr><th>Reported user</th><th>Reporter</th><th>Context</th><th>Reason</th><th>Date</th><th>Status</th><th>Decision</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td>
            <a class="row-title" href="<?= url('seller.php?id=' . $r['reported_user_id']) ?>"><?= e($r['reported_name']) ?></a>
            <?= status_badge($r['reported_status']) ?>
          </td>
          <td><?= e($r['reporter_name']) ?></td>
          <td>
            <span class="chip text-capitalize"><?= e($r['context']) ?></span>
            <?php if ($r['listing_id']): ?><a class="row-sub d-block" href="<?= url('listing.php?id=' . $r['listing_id']) ?>"><?= e($r['listing_title']) ?></a><?php endif; ?>
            <?php if ($r['conversation_id']): ?><a class="row-sub d-block" href="<?= url('admin/conversation.php?id=' . (int)$r['conversation_id']) ?>">View conversation</a><?php endif; ?>
          </td>
          <td><?= e(ucfirst(str_replace('_', ' ', $r['reason']))) ?><?php if ($r['details']): ?><div class="row-sub"><?= e($r['details']) ?></div><?php endif; ?></td>
          <td class="small text-nowrap"><?= e(date('j M Y', strtotime($r['created_at']))) ?></td>
          <td><?= status_badge($r['status']) ?></td>
          <td style="min-width: 220px">
            <?php if ($r['status'] === 'open'): ?>
              <form method="post" class="d-flex flex-column gap-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input class="form-control form-control-sm" name="note" maxlength="500" placeholder="Admin note">
                <div class="d-flex gap-2">
                  <button name="action" value="dismiss" class="btn btn-sm btn-outline-secondary">Dismiss</button>
                  <button name="action" value="resolve" class="btn btn-sm btn-outline-primary">Resolve</button>
                  <button name="action" value="suspend" class="btn btn-sm btn-danger" data-confirm-title="Suspend this user?" data-confirm="The reported account is locked out until an administrator reinstates it.">Suspend</button>
                </div>
              </form>
            <?php else: ?>
              <span class="meta"><?= e($r['admin_note'] ?: '—') ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <div class="empty-state"><div class="empty-state-icon"><i class="bi bi-shield-check"></i></div><h2>No reports</h2><p>Nothing needs attention.</p></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';