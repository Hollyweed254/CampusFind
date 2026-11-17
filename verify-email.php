<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/verification.php';
if (current_user()) redirect('marketplace.php');

$u = verification_pending_user();
if (!$u) { flash('warning', 'Create an account to get started.'); redirect('register.php'); }
if ($u['email_verified_at'] !== null) {
    verification_clear_pending();
    flash('info', 'That email is already verified. Log in to continue.');
    redirect('login.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'verify';

    if ($action === 'cancel') {
        verification_clear_pending();
        redirect('register.php');
    }

    if ($action === 'resend') {
        $r = verification_send((int)$u['id']);
        if ($r['status'] === 'sent') flash('success', 'A new code is on its way.');
        elseif ($r['status'] === 'wait') flash('info', 'Please wait ' . (int)$r['wait'] . 's before requesting another code.');
        elseif ($r['status'] === 'limit') flash('danger', 'Too many codes requested. Try again in an hour.');
        else flash('danger', 'We could not send the email right now. Try again shortly.');
        redirect('verify-email.php');
    }

    $code = preg_replace('/\D/', '', (string)($_POST['code'] ?? ''));
    if (strlen($code) !== 6) {
        $error = 'Enter the 6-digit code from the email.';
    } else {
        $r = verification_check((int)$u['id'], $code);
        if ($r['status'] === 'ok') {
            verification_complete((int)$u['id']);
            verification_clear_pending();
            login_user((int)$u['id']);
            flash('success', 'Email verified. Welcome to CampusFind.');
            redirect('marketplace.php');
        } elseif ($r['status'] === 'wrong') {
            $error = $r['left'] > 0
                ? 'That code is not right. ' . $r['left'] . ' attempt' . ($r['left'] === 1 ? '' : 's') . ' left.'
                : 'Too many attempts. Request a new code.';
        } elseif ($r['status'] === 'expired') {
            $error = 'That code has expired. Request a new one.';
        } elseif ($r['status'] === 'locked') {
            $error = 'Too many attempts. Request a new code.';
        } else {
            $error = 'There is no active code. Request a new one.';
        }
    }
}
$pageTitle = 'Verify email'; require __DIR__ . '/includes/header.php'; ?>
<div class="narrow-shell">
  <div class="sc-card">
    <div class="auth-head">
      <span class="auth-head-icon"><i class="bi bi-envelope-check"></i></span>
      <div>
        <h1>Verify your email</h1>
      </div>
    </div>
    <p class="text-body-secondary">Enter the 6-digit code we sent to <strong><?= e($u['email']) ?></strong>.</p>
    <?php if ($error): ?>
      <div class="sc-error mb-3"><ul><li><?= e($error) ?></li></ul></div>
    <?php endif; ?>
    <?php if (!mail_configured() && mail_is_local()): ?>
      <div class="sc-note mb-3"><i class="bi bi-info-circle me-1"></i>Email sending is not configured on this machine yet, so the message is saved in <code>storage/mail/</code> instead. Open the newest <code>.txt</code> file there to read the code.</div>
    <?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="verify">
      <div class="mb-3">
        <label class="form-label" for="verify-code">Verification code</label>
        <input class="form-control verify-code" id="verify-code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required autofocus>
      </div>
      <button class="btn btn-primary w-100 py-2">Verify email</button>
    </form>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="resend">
        <button type="submit" class="btn btn-link btn-sm p-0">Resend code</button>
      </form>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel">
        <button type="submit" class="btn btn-link btn-sm p-0 text-body-secondary">Use a different email</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
