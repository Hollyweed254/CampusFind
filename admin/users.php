<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/trust.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $id = (int)($_POST['id'] ?? 0); $act = $_POST['action'] ?? '';
    $reason = mb_substr(clean('reason'), 0, 255);
    if ($id === (int)$admin['id']) flash('danger', 'You cannot change your own account status.');
    elseif (in_array($act, ['deactivate', 'suspend', 'reactivate', 'reinstate'], true)) {
        if ($act === 'suspend') {
            q("UPDATE users SET status = 'suspended', suspended_at = NOW(), suspension_reason = ? WHERE id = ? AND role = 'student'",
              [$reason !== '' ? $reason : 'Suspended by administrator', $id]);
            audit_log((int)$admin['id'], 'user_suspended', 'user', $id, $reason);
        } elseif ($act === 'deactivate') {
            q("UPDATE users SET status = 'deactivated' WHERE id = ? AND role = 'student'", [$id]);
            audit_log((int)$admin['id'], 'user_deactivated', 'user', $id, $reason);
        } else {
            q("UPDATE users SET status = 'active', suspended_at = NULL, suspension_reason = NULL WHERE id = ? AND role = 'student'", [$id]);
            audit_log((int)$admin['id'], $act === 'reinstate' ? 'user_reinstated' : 'user_reactivated', 'user', $id, $reason);
        }
        flash('success', 'Account updated.');
    }
    redirect('admin/users.php');
}
$search = clean('q', 'GET');
$p = []; $w = '';
if ($search !== '') { $w = 'WHERE u.full_name LIKE ? OR u.email LIKE ?'; $like = '%' . addcslashes($search, '%_\\') . '%'; $p = [$like, $like]; }
$rows = q("SELECT u.*, (SELECT COUNT(*) FROM listings WHERE seller_id = u.id) AS listings FROM users u $w ORDER BY u.created_at DESC LIMIT 200", $p)->fetchAll();
$pageTitle = 'Manage users'; require __DIR__ . '/../includes/header.php'; ?>
<div class="page-head">
  <div>
    <h1 class="page-title">Users</h1>
  </div>
</div>
<?php include __DIR__ . '/_nav.php'; ?>
<form class="row g-2 mb-4">
  <div class="col-12 col-md-5 col-lg-4">
    <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search name or email" aria-label="Search users">
  </div>
  <div class="col-auto"><button class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Search</button></div>
</form>
<?php if (!$rows): ?>
  <div class="empty-state">
    <div class="empty-state-icon"><i class="bi bi-people"></i></div>
    <h2>No users found</h2>
    <p>No accounts match<?= $search !== '' ? ' "' . e($search) . '"' : '' ?>.</p>
    <a class="btn btn-outline-primary" href="<?= url('admin/users.php') ?>">Clear search</a>
  </div>
<?php else: ?>
<div class="table-card table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>User</th><th>Role</th><th>Listings</th><th>Joined</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $u): ?>
    <tr>
      <td>
        <div class="d-flex align-items-center gap-3">
          <span class="avatar"><?= e(initials($u['full_name'])) ?></span>
          <div class="min-w-0">
            <div class="row-title text-truncate"><?= e($u['full_name']) ?><?= (int)$u['id'] === (int)$admin['id'] ? ' <span class="meta">(you)</span>' : '' ?></div>
            <div class="row-sub text-truncate"><?= e($u['email']) ?></div>
            <?php $vb = [];
              if (!empty($u['phone_verified_at']) && ($u['phone_verified_mode'] ?? '') === 'sms') $vb[] = ['telephone-fill', 'Phone'];
              if (!empty($u['email_verified_at'])) $vb[] = ['envelope-check-fill', 'Email'];
              if (!empty($u['student_verified_at'])) $vb[] = ['mortarboard-fill', 'Student'];
              if (!empty($u['trusted_seller_at'])) $vb[] = ['patch-check-fill', 'Trusted'];
              if ($vb): ?>
              <span class="table-badges"><?php foreach ($vb as [$ic, $label]): ?><span class="vbadge vbadge-ok" title="<?= e($label) ?> verified"><i class="bi bi-<?= $ic ?>"></i><?= e($label) ?></span><?php endforeach; ?></span>
            <?php endif; ?>
          </div>
        </div>
      </td>
      <td><span class="chip text-capitalize"><?= e($u['role']) ?></span></td>
      <td><?= (int)$u['listings'] ?></td>
      <td class="small text-nowrap"><?= e(date('j M Y', strtotime($u['created_at']))) ?></td>
      <td><?= status_badge($u['status']) ?></td>
      <td class="text-end text-nowrap"><?php if ($u['role'] === 'student'): ?>
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>">
        <?php if ($u['status'] === 'active'): ?>
          <button name="action" value="deactivate" class="btn btn-sm btn-outline-secondary" data-confirm-title="Deactivate this account?" data-confirm="The user will no longer be able to log in or use the marketplace.">Deactivate</button>
          <button name="action" value="suspend" class="btn btn-sm btn-outline-danger" data-confirm-title="Suspend this account?" data-confirm="The user is locked out immediately and the action is recorded in the audit log.">Suspend</button>
        <?php else: ?>
          <button name="action" value="reinstate" class="btn btn-sm btn-outline-primary">Reinstate</button>
        <?php endif; ?></form>
      <?php endif; ?></td>
    </tr>
  <?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';