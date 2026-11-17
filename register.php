<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/verification.php';
if (current_user()) redirect('marketplace.php');

$errors = []; $f = ['full_name' => '', 'email' => '', 'phone' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $f = ['full_name' => clean('full_name'), 'email' => strtolower(clean('email')), 'phone' => clean('phone')];
    $pw = $_POST['password'] ?? ''; $pw2 = $_POST['password2'] ?? '';
    if (mb_strlen($f['full_name']) < 3 || mb_strlen($f['full_name']) > 100) $errors[] = 'Full name must be 3-100 characters.';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || strlen($f['email']) > 150) $errors[] = 'Enter a valid email address.';
    if ($f['phone'] !== '' && !preg_match('/^\+?[0-9 ]{9,15}$/', $f['phone'])) $errors[] = 'Phone must be 9-15 digits.';
    if (strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pw !== $pw2) $errors[] = 'Passwords do not match.';
    if (!$errors && q('SELECT id FROM users WHERE email = ?', [$f['email']])->fetch())
        $errors[] = 'That email is already registered.';
    if (!$errors) {
        q('INSERT INTO users (full_name, email, password_hash, phone) VALUES (?,?,?,?)',
          [$f['full_name'], $f['email'], password_hash($pw, PASSWORD_DEFAULT), $f['phone'] ?: null]);
        $uid = (int)db()->lastInsertId();
        $_SESSION['pending_verify_uid'] = $uid;
        $sent = verification_send($uid);
        if ($sent['status'] === 'sent') flash('success', 'Account created. Check your email for the code.');
        else flash('danger', 'Account created, but the code could not be sent. Try resending it on the next screen.');
        redirect('verify-email.php');
    }
}
$pageTitle = 'Create account'; require __DIR__ . '/includes/header.php'; ?>
<div class="narrow-shell">
  <div class="sc-card">
    <div class="auth-head">
      <span class="auth-head-icon"><i class="bi bi-person-plus"></i></span>
      <div>
        <h1>Create your account</h1>
      </div>
    </div>
    <?php if ($errors): ?>
      <div class="sc-error mb-3"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label" for="reg-name">Full name</label>
        <input class="form-control" id="reg-name" name="full_name" value="<?= e($f['full_name']) ?>" autocomplete="name" required>
      </div>
      <div class="mb-3">
        <label class="form-label" for="reg-email">Email</label>
        <input type="email" class="form-control" id="reg-email" name="email" value="<?= e($f['email']) ?>" autocomplete="email" required>
        <div class="form-text">We'll email you a 6-digit code to verify this address.</div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="reg-phone">Phone <span class="text-body-secondary fw-normal">(optional)</span></label>
        <input class="form-control" id="reg-phone" name="phone" value="<?= e($f['phone']) ?>" autocomplete="tel">
      </div>
      <div class="mb-3">
        <label class="form-label" for="reg-pass">Password</label>
        <input type="password" class="form-control" id="reg-pass" name="password" minlength="8" autocomplete="new-password" required>
        <div class="form-text">At least 8 characters.</div>
      </div>
      <div class="mb-4">
        <label class="form-label" for="reg-pass2">Confirm password</label>
        <input type="password" class="form-control" id="reg-pass2" name="password2" autocomplete="new-password" required>
      </div>
      <button class="btn btn-primary w-100 py-2">Create account</button>
    </form>
    <p class="small text-body-secondary mt-3 mb-0 text-center">Already have an account? <a href="<?= url('login.php') ?>">Log in</a>.</p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';