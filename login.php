<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/verification.php';
if (current_user()) redirect('marketplace.php');

$error = ''; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(clean('email')); $pw = $_POST['password'] ?? '';
    $u = q('SELECT id, password_hash, role, status, email_verified_at FROM users WHERE email = ?', [$email])->fetch();
    if (!$u || !password_verify($pw, $u['password_hash'])) {
        $error = 'Incorrect email or password.';
    } elseif ($u['status'] !== 'active') {
        $error = $u['status'] === 'suspended'
            ? 'This account has been suspended. Contact an administrator.'
            : 'This account has been deactivated. Contact an administrator.';
    } elseif ($u['email_verified_at'] === null) {
        $_SESSION['pending_verify_uid'] = (int)$u['id'];
        $active = q('SELECT id FROM email_verifications WHERE user_id = ? AND expires_at > NOW()', [$u['id']])->fetch();
        if (!$active) verification_send((int)$u['id']);
        flash('info', 'Enter the code we emailed you to finish signing in.');
        redirect('verify-email.php');
    } else {
        login_user((int)$u['id']);
        flash('success', 'Logged in.');
        redirect($u['role'] === 'admin' ? 'admin/dashboard.php' : 'marketplace.php');
    }
}
require_once __DIR__ . '/includes/oauth.php';
$googleLogin = oauth_configured('google');

$pageTitle = 'Log in';
$bareLayout = true;
require __DIR__ . '/includes/header.php'; ?>
<section class="cf-login-wrap flex-grow-1 d-flex align-items-center">
  <div class="container py-5 d-flex justify-content-center w-100">
    <div class="cf-login-card">
      <div class="cf-login-hero" aria-hidden="true">
        <svg class="cf-login-wave" viewBox="0 0 600 200" preserveAspectRatio="none" focusable="false">
          <path d="M0 118c96-34 178 8 268-6 92-14 148-52 242-30 36 8 66 22 90 34v84H0Z" fill="#2f7fc1" opacity=".55"/>
          <path d="M0 148c104-30 190 14 286-2 92-15 156-46 238-24 28 8 54 18 76 28v50H0Z" fill="#1f5f97"/>
        </svg>
      </div>

      <div class="cf-login-panel">
        <span class="cf-login-brand">
          <svg class="cf-login-logo" viewBox="0 0 32 32" aria-hidden="true" focusable="false">
            <circle cx="16" cy="16" r="16" fill="#EAF5FD"/>
            <path d="M3 21c3.5-4 7-4 10.5 0s7 4 10.5 0 5-3.5 5-3.5V29H3Z" fill="#55acee"/>
          </svg>
          <span class="cf-login-word">Campus<span>Find</span></span>
        </span>

        <h1 class="cf-login-title">Log in</h1>

        <?php if ($error): ?><div class="alert alert-danger py-2 mb-0"><?= e($error) ?></div><?php endif; ?>

        <div class="cf-login-socials">
          <a class="cf-login-social<?= $googleLogin ? '' : ' is-disabled' ?>" href="<?= $googleLogin ? url('oauth/start.php?provider=google') : '#' ?>"<?= $googleLogin ? '' : ' aria-disabled="true" tabindex="-1" title="Google sign-in is not configured yet"' ?>>
            <svg viewBox="0 0 48 48" width="18" height="18" aria-hidden="true" focusable="false">
              <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
              <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
              <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
              <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </svg>
            <span><span class="cf-login-extra">Log in with </span>Google</span>
          </a>
        </div>
        <?php if (!$googleLogin): ?>
          <p class="cf-login-note">Google sign-in is not configured. Use your email and password.</p>
        <?php endif; ?>

        <div class="cf-login-or"><span>Or</span></div>

        <form method="post" class="cf-login-form">
          <?= csrf_field() ?>
          <label class="visually-hidden" for="login-email">Email</label>
          <input id="login-email" name="email" type="email" placeholder="Email" value="<?= e($email) ?>" autocomplete="email" required autofocus>
          <label class="visually-hidden" for="login-password">Password</label>
          <input id="login-password" name="password" type="password" placeholder="Password" autocomplete="current-password" required>
          <button type="submit" class="cf-login-submit">Log in</button>
        </form>

        <p class="cf-login-register">New to <?= e(APP_NAME) ?>? <a href="<?= url('register.php') ?>">Create account</a></p>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
