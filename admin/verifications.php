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
    $req = q('SELECT * FROM student_verifications WHERE id = ?', [$id])->fetch();
    if ($req && $req['status'] === 'pending' && in_array($act, ['approve', 'reject'], true)) {
        if ($act === 'reject' && $note === '') {
            flash('danger', 'Add a reason before rejecting.');
        } else {
            q("UPDATE student_verifications SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?",
              [$act === 'approve' ? 'approved' : 'rejected', $note !== '' ? $note : null, $admin['id'], $id]);
            if ($act === 'approve') {
                q('UPDATE users SET student_verified_at = NOW() WHERE id = ?', [$req['user_id']]);
            } else {
                q('UPDATE users SET student_verified_at = NULL WHERE id = ?', [$req['user_id']]);
            }
            audit_log((int)$admin['id'], 'student_' . ($act === 'approve' ? 'approved' : 'rejected'), 'student_verification', $id, $note);
            flash('success', 'Verification ' . ($act === 'approve' ? 'approved' : 'rejected') . '.');
        }
    }
    redirect('admin/verifications.php');
}

$pending = q("SELECT v.*, u.full_name AS account_name, u.email, u.university_email_verified_at
              FROM student_verifications v JOIN users u ON u.id = v.user_id
              WHERE v.status = 'pending' ORDER BY v.created_at ASC LIMIT 100")->fetchAll();
$recent = q("SELECT v.*, u.full_name AS account_name, a.full_name AS reviewer
             FROM student_verifications v JOIN users u ON u.id = v.user_id LEFT JOIN users a ON a.id = v.reviewed_by
             WHERE v.status <> 'pending' ORDER BY v.reviewed_at DESC LIMIT 25")->fetchAll();
$statuses = q("SELECT id, full_name, email, phone, phone_verified_at, phone_verified_mode, email_verified_at,
                      university_email, university_email_verified_at, student_verified_at, trusted_seller_at, status
               FROM users WHERE role = 'student' ORDER BY created_at DESC LIMIT 50")->fetchAll();
$pageTitle = 'Verification requests'; require __DIR__ . '/../includes/header.php'; ?>
<div class="page-head">
  <div>
    <h1 class="page-title">Verification</h1>
  </div>
</div>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="panel mb-4">
  <div class="panel-head"><h2>Student requests</h2><span class="st st-warn"><?= count($pending) ?> pending</span></div>
  <?php if ($pending): ?>
    <div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th>Applicant</th><th>Registration</th><th>University</th><th>Evidence</th><th>Submitted</th><th>Decision</th></tr></thead>
      <tbody>
        <?php foreach ($pending as $r): ?>
          <tr>
            <td>
              <div class="row-title"><?= e($r['full_name']) ?></div>
              <div class="row-sub"><?= e($r['email']) ?> &middot; <?= e($r['account_name']) ?></div>
              <?php if (empty($r['university_email_verified_at'])): ?><div class="row-sub text-danger">University email not verified</div><?php endif; ?>
            </td>
            <td><?= e($r['registration_number']) ?></td>
            <td><?= e($r['university']) ?></td>
            <td><?= $r['evidence_path'] ? '<a href="' . url('admin/evidence.php?id=' . $r['id']) . '" target="_blank" rel="noopener">View document</a>' : '<span class="meta">None</span>' ?></td>
            <td class="small text-nowrap"><?= e(date('j M Y H:i', strtotime($r['created_at']))) ?></td>
            <td style="min-width: 240px">
              <form method="post" class="d-flex flex-column gap-2">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input class="form-control form-control-sm" name="note" maxlength="500" placeholder="Reason (required to reject)">
                <div class="d-flex gap-2">
                  <button name="action" value="approve" class="btn btn-sm btn-primary">Approve</button>
                  <button name="action" value="reject" class="btn btn-sm btn-outline-danger">Reject</button>
                </div>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <div class="panel-body"><p class="meta mb-0">No pending requests.</p></div>
  <?php endif; ?>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="panel h-100">
      <div class="panel-head"><h2>Recent decisions</h2></div>
      <ul class="list-group list-group-flush">
        <?php foreach ($recent as $r): ?>
          <li class="list-group-item">
            <div class="d-flex justify-content-between gap-2">
              <span><strong><?= e($r['account_name']) ?></strong> <?= status_badge($r['status']) ?></span>
              <span class="meta text-nowrap"><?= e($r['reviewed_at'] ? date('j M', strtotime($r['reviewed_at'])) : '') ?></span>
            </div>
            <?php if ($r['admin_note']): ?><div class="row-sub"><?= e($r['admin_note']) ?></div><?php endif; ?>
            <div class="row-sub">by <?= e($r['reviewer'] ?: '—') ?></div>
          </li>
        <?php endforeach; if (!$recent): ?><li class="list-group-item meta">No decisions yet.</li><?php endif; ?>
      </ul>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="panel h-100">
      <div class="panel-head"><h2>Account checks</h2><span class="meta">Latest 50</span></div>
      <div class="table-responsive" style="max-height: 420px">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>User</th><th>Phone</th><th>Email</th><th>Student</th></tr></thead>
          <tbody>
            <?php foreach ($statuses as $u): ?>
              <tr>
                <td><a class="row-title" href="<?= url('seller.php?id=' . $u['id']) ?>"><?= e($u['full_name']) ?></a><?php if ($u['status'] !== 'active'): ?> <?= status_badge($u['status']) ?><?php endif; ?></td>
                <td class="small"><?php if ($u['phone_verified_at']): ?><?= ($u['phone_verified_mode'] ?? '') === 'sms' ? '<span class="st st-ok">SMS</span>' : '<span class="st st-muted">Simulated</span>' ?><?php else: ?><span class="meta">—</span><?php endif; ?></td>
                <td class="small"><?= $u['email_verified_at'] ? '<span class="st st-ok">Verified</span>' : '<span class="meta">—</span>' ?></td>
                <td class="small"><?= $u['student_verified_at'] ? '<span class="st st-ok">Approved</span>' : '<span class="meta">—</span>' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php';