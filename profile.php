<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/trust.php';
$me = require_login();
$meFull = q('SELECT * FROM users WHERE id = ?', [$me['id']])->fetch();
$myBadges = seller_badges($meFull);
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['form'] ?? '') === 'profile') {
        $name = clean('full_name'); $phone = clean('phone');
        if (mb_strlen($name) < 3 || mb_strlen($name) > 100) $errors[] = 'Full name must be 3-100 characters.';
        if ($phone !== '' && !preg_match('/^\+?[0-9 ]{9,15}$/', $phone)) $errors[] = 'Phone must be 9-15 digits.';
        if (!$errors) { q('UPDATE users SET full_name = ?, phone = ? WHERE id = ?', [$name, $phone ?: null, $me['id']]); flash('success', 'Profile updated.'); redirect('profile.php'); }
    } else {
        $cur = $_POST['current'] ?? ''; $new = $_POST['new'] ?? ''; $new2 = $_POST['new2'] ?? '';
        $hash = q('SELECT password_hash FROM users WHERE id = ?', [$me['id']])->fetchColumn();
        if (!password_verify($cur, $hash)) $errors[] = 'Current password is wrong.';
        if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
        if ($new !== $new2) $errors[] = 'New passwords do not match.';
        if (!$errors) { q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]); session_regenerate_id(true); flash('success', 'Password changed.'); redirect('profile.php'); }
    }
}
$pageTitle = 'My profile'; require __DIR__ . '/includes/header.php'; ?>
<div class="page-head">
  <div>
    <h1 class="page-title">My profile</h1>
  </div>
</div>
<?php if ($errors): ?>
  <div class="sc-error mb-4"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="panel h-100">
      <div class="panel-head"><h2>Personal details</h2></div>
      <div class="panel-body">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="form" value="profile">
          <div class="mb-3">
            <label class="form-label" for="pf-email">Email</label>
            <input class="form-control" id="pf-email" value="<?= e($me['email']) ?>" disabled>
            <div class="form-text">Email is used to sign in and cannot be changed.</div>
          </div>
          <div class="mb-3"><label class="form-label" for="pf-name">Full name</label><input class="form-control" id="pf-name" name="full_name" value="<?= e($me['full_name']) ?>" required></div>
          <div class="mb-4"><label class="form-label" for="pf-phone">Phone</label><input class="form-control" id="pf-phone" name="phone" value="<?= e($me['phone'] ?? '') ?>" autocomplete="tel"></div>
          <button class="btn btn-primary">Save changes</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="panel h-100">
      <div class="panel-head"><h2>Change password</h2></div>
      <div class="panel-body">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="form" value="password">
          <div class="mb-3"><label class="form-label" for="pf-cur">Current password</label><input type="password" class="form-control" id="pf-cur" name="current" autocomplete="current-password" required></div>
          <div class="mb-3"><label class="form-label" for="pf-new">New password</label><input type="password" class="form-control" id="pf-new" name="new" minlength="8" autocomplete="new-password" required></div>
          <div class="mb-4"><label class="form-label" for="pf-new2">Confirm new password</label><input type="password" class="form-control" id="pf-new2" name="new2" autocomplete="new-password" required></div>
          <button class="btn btn-outline-primary">Change password</button>
        </form>
      </div>
    </div>
  </div>
</div>
<div class="panel mt-4">
  <div class="panel-head">
    <h2>Verification &amp; trust</h2>
    <a class="section-link" href="<?= url('verification.php') ?>">Manage <i class="bi bi-arrow-right"></i></a>
  </div>
  <div class="panel-body">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
      <?php if ($myBadges): ?><?= badge_chips($myBadges) ?><?php else: ?><span class="st st-muted">No badges yet</span><?php endif; ?>
      <a class="btn btn-sm btn-outline-primary" href="<?= url('seller.php?id=' . $me['id']) ?>">Public profile</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= url('transactions.php') ?>">Transactions</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= url('messages.php') ?>">Messages</a>
    </div>
    <div class="row g-3 small">
      <div class="col-md-3"><span class="meta d-block">Phone</span><?= !empty($meFull['phone_verified_at']) ? (($meFull['phone_verified_mode'] ?? '') === 'sms' ? '<span class="st st-ok">Verified</span>' : '<span class="st st-muted">Simulated</span>') : '<span class="st st-muted">Not verified</span>' ?></div>
      <div class="col-md-3"><span class="meta d-block">Account email</span><?= !empty($meFull['email_verified_at']) ? '<span class="st st-ok">Verified</span>' : '<span class="st st-muted">Not verified</span>' ?></div>
      <div class="col-md-3"><span class="meta d-block">University email</span><?= !empty($meFull['university_email_verified_at']) ? '<span class="st st-ok">Verified</span>' : '<span class="st st-muted">Not verified</span>' ?></div>
      <div class="col-md-3"><span class="meta d-block">Student status</span><?= !empty($meFull['student_verified_at']) ? '<span class="st st-ok">Approved</span>' : '<span class="st st-muted">Not approved</span>' ?></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';