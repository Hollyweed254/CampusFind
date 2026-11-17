<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/otp.php';
require_once __DIR__ . '/includes/trust.php';

$me = require_login();
$user = q('SELECT * FROM users WHERE id = ?', [$me['id']])->fetch();
$studentReq = student_verification_for((int)$me['id']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'phone_send') {
        $phone = phone_normalize(clean('phone'));
        if (!$phone) {
            $errors[] = 'Enter a Kenyan mobile number, e.g. 0712 345 678.';
        } else {
            $dup = q('SELECT 1 FROM users WHERE phone = ? AND phone_verified_at IS NOT NULL AND id <> ?', [$phone, $me['id']])->fetchColumn();
            if ($dup) {
                $errors[] = 'That phone number is already verified on another account.';
            } else {
                $r = otp_issue((int)$me['id'], 'phone', $phone);
                if ($r['status'] === 'sent') {
                    if (($r['mode'] ?? '') === 'dev') flash('info', 'SMS is not configured. The simulated code was saved in storage/sms/.');
                    elseif (($r['mode'] ?? '') === 'sandbox') flash('info', 'Sandbox code created. Open the Africa\'s Talking simulator to read it. This does not earn the public badge.');
                    else flash('success', 'Code sent by SMS.');
                    redirect('verification.php');
                }
                if ($r['status'] === 'cooldown') $errors[] = 'Wait ' . (int)$r['wait'] . ' seconds before requesting another code.';
                elseif ($r['status'] === 'limit') $errors[] = 'Too many codes requested. Try again in about an hour.';
                elseif ($r['status'] === 'send_failed' && ($r['mode'] ?? '') === 'config') $errors[] = 'SMS is not fully configured. Add the provider username and API key.';
                elseif ($r['status'] === 'send_failed') $errors[] = 'The SMS could not be sent. Try again shortly.';
                else $errors[] = 'Could not create a code.';
            }
        }
    }

    if ($action === 'phone_verify') {
        $row = otp_current((int)$me['id'], 'phone');
        if (!$row) $errors[] = 'Request a code first.';
        else {
            $r = otp_verify((int)$me['id'], 'phone', clean('code'), $row['destination']);
            if ($r['status'] === 'ok') {
                flash('success', sms_live()
                    ? 'Phone verified.'
                    : (sms_configured() ? 'Phone confirmed in the sandbox. It will not earn the public badge until live SMS is enabled.' : 'Phone confirmed in development mode. This does not earn the public badge.'));
                redirect('verification.php');
            }
            elseif ($r['status'] === 'wrong') $errors[] = 'That code is wrong.' . (isset($r['left']) ? ' ' . $r['left'] . ' attempts left.' : '');
            elseif ($r['status'] === 'expired') $errors[] = 'That code has expired. Request a new one.';
            elseif ($r['status'] === 'locked') $errors[] = 'Too many wrong attempts. Request a new code.';
            elseif ($r['status'] === 'duplicate') $errors[] = 'That phone number is already verified on another account.';
            else $errors[] = 'The code could not be verified.';
        }
    }

    if ($action === 'email_send') {
        $email = strtolower(clean('university_email'));
        if (!university_email_allowed($email)) {
            $errors[] = UNIVERSITY_EMAIL_DOMAINS
                ? 'Use an email from an approved university domain.'
                : 'Use a university email address ending in ' . UNIVERSITY_EMAIL_SUFFIX . '.';
        } elseif (q('SELECT 1 FROM users WHERE university_email = ? AND id <> ?', [$email, $me['id']])->fetchColumn()) {
            $errors[] = 'That university email is already verified on another account.';
        } else {
            $r = otp_issue((int)$me['id'], 'university_email', $email);
            if ($r['status'] === 'sent') { flash('success', 'Code sent to ' . $email . '.'); redirect('verification.php'); }
            if ($r['status'] === 'cooldown') $errors[] = 'Wait ' . (int)$r['wait'] . ' seconds before requesting another code.';
            elseif ($r['status'] === 'limit') $errors[] = 'Too many codes requested. Try again in about an hour.';
            else $errors[] = 'The email could not be sent. Try again shortly.';
        }
    }

    if ($action === 'email_verify') {
        $row = otp_current((int)$me['id'], 'university_email');
        if (!$row) $errors[] = 'Request a code first.';
        else {
            $r = otp_verify((int)$me['id'], 'university_email', clean('code'), $row['destination']);
            if ($r['status'] === 'ok') { flash('success', 'University email verified.'); redirect('verification.php'); }
            elseif ($r['status'] === 'wrong') $errors[] = 'That code is wrong.' . (isset($r['left']) ? ' ' . $r['left'] . ' attempts left.' : '');
            elseif ($r['status'] === 'expired') $errors[] = 'That code has expired. Request a new one.';
            elseif ($r['status'] === 'locked') $errors[] = 'Too many wrong attempts. Request a new code.';
            elseif ($r['status'] === 'duplicate') $errors[] = 'That university email belongs to another account.';
            else $errors[] = 'The code could not be verified.';
        }
    }

    if ($action === 'student_submit') {
        $fullName = clean('full_name');
        $regNo = strtoupper(clean('registration_number'));
        $university = clean('university');
        if (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 100) $errors[] = 'Full name must be 3-100 characters.';
        if (!preg_match('/^[A-Z0-9\/-]{3,60}$/', $regNo)) $errors[] = 'Enter a valid registration number.';
        if (mb_strlen($university) < 2 || mb_strlen($university) > 120) $errors[] = 'Enter your university.';
        if (empty($user['university_email_verified_at'])) $errors[] = 'Verify your university email before requesting student status.';

        $evidence = null;
        $upload = $_FILES['evidence'] ?? null;
        if ($upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ((int)$upload['error'] !== UPLOAD_ERR_OK) $errors[] = 'The evidence upload failed.';
            elseif ((int)$upload['size'] > STUDENT_EVIDENCE_BYTES) $errors[] = 'Evidence must be 3MB or smaller.';
            else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($upload['tmp_name']);
                $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
                if (!isset($allowed[$mime])) $errors[] = 'Evidence must be a PDF, JPG or PNG.';
                else $evidence = ['tmp' => $upload['tmp_name'], 'ext' => $allowed[$mime]];
            }
        }
        if (!$errors) {
            $dir = __DIR__ . '/storage/verifications';
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                $errors[] = 'Verification storage is unavailable.';
            } else {
                if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n");
                $path = null;
                if ($evidence) {
                    $path = bin2hex(random_bytes(16)) . '.' . $evidence['ext'];
                    if (!move_uploaded_file($evidence['tmp'], $dir . '/' . $path)) {
                        $errors[] = 'The evidence could not be saved.';
                        $path = null;
                    }
                }
                if (!$errors) {
                    if ($studentReq && $studentReq['status'] === 'rejected' && $studentReq['evidence_path']) {
                        @unlink($dir . '/' . basename($studentReq['evidence_path']));
                    }
                    if ($studentReq) {
                        q("UPDATE student_verifications SET full_name = ?, registration_number = ?, university = ?,
                             evidence_path = ?, status = 'pending', admin_note = NULL,
                             reviewed_by = NULL, reviewed_at = NULL WHERE user_id = ?",
                          [$fullName, $regNo, $university, $path, $me['id']]);
                    } else {
                        q('INSERT INTO student_verifications (user_id, full_name, registration_number, university, evidence_path)
                           VALUES (?, ?, ?, ?, ?)', [$me['id'], $fullName, $regNo, $university, $path]);
                    }
                    flash('success', 'Student verification submitted for review.');
                    redirect('verification.php');
                }
            }
        }
    }
}

$user = q('SELECT * FROM users WHERE id = ?', [$me['id']])->fetch();
$studentReq = student_verification_for((int)$me['id']);
$phoneCode = otp_current((int)$me['id'], 'phone');
$emailCode = otp_current((int)$me['id'], 'university_email');
$badges = seller_badges($user);
$pageTitle = 'Verification'; require __DIR__ . '/includes/header.php'; ?>
<div class="page-head">
  <div>
    <h1 class="page-title">Verification</h1>
    <p>Each badge comes from a completed check. None of these details are shown publicly.</p>
  </div>
  <?php if ($badges): ?><?= badge_chips($badges) ?><?php endif; ?>
</div>
<?php if ($errors): ?><div class="sc-error mb-4"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="panel h-100">
      <div class="panel-head">
        <h2>Phone</h2>
        <?php if (!empty($user['phone_verified_at'])): ?>
          <?= ($user['phone_verified_mode'] ?? '') === 'sms'
              ? '<span class="st st-ok">Verified</span>'
              : '<span class="st st-muted">Simulated</span>' ?>
        <?php else: ?><span class="st st-muted">Not verified</span><?php endif; ?>
      </div>
      <div class="panel-body">
        <?php if (!empty($user['phone_verified_at'])): ?>
          <p class="mb-1"><strong><?= e(phone_display($user['phone'] ?? '')) ?></strong></p>
          <p class="meta mb-0"><?= ($user['phone_verified_mode'] ?? '') === 'sms'
              ? 'Confirmed by SMS.'
              : 'Confirmed in development mode. It will not appear on your public profile until SMS is configured and the number is re-verified.' ?></p>
        <?php else: ?>
          <p class="meta mb-3">We send a 6-digit code by SMS. One number can belong to one account.</p>
          <?php if (SMS_PROVIDER === ''): ?>
            <div class="sc-note mb-3"><i class="bi bi-info-circle me-1"></i>SMS is not configured here. Codes are written to <code>storage/sms/</code> and are clearly simulated.</div>
          <?php elseif (!sms_configured()): ?>
            <div class="sc-error mb-3"><i class="bi bi-exclamation-triangle me-1"></i>SMS provider is selected but incomplete. Add the provider username and API key in <code>includes/config.local.php</code>.</div>
          <?php elseif (!sms_live()): ?>
            <div class="sc-note mb-3"><i class="bi bi-info-circle me-1"></i>Africa's Talking sandbox is active. Codes appear in the sandbox simulator, not on the phone, and do not earn the public badge. Switch to production for real SMS.</div>
          <?php endif; ?>
          <form method="post" class="d-flex gap-2 flex-wrap">
            <?= csrf_field() ?><input type="hidden" name="action" value="phone_send">
            <input class="form-control" name="phone" value="<?= e($user['phone'] ?? '') ?>" placeholder="0712 345 678" inputmode="tel" required style="max-width:220px">
            <button class="btn btn-primary">Send code</button>
          </form>
          <?php if ($phoneCode && (int)$phoneCode['seconds_left'] > 0): ?>
            <form method="post" class="d-flex gap-2 flex-wrap mt-3">
              <?= csrf_field() ?><input type="hidden" name="action" value="phone_verify">
              <input class="form-control verify-code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required style="max-width:180px">
              <button class="btn btn-outline-primary">Verify</button>
            </form>
            <p class="meta mt-2 mb-0">Code sent to <?= e(phone_display($phoneCode['destination'])) ?>. Expires in <?= (int)ceil($phoneCode['seconds_left'] / 60) ?> min.</p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="panel h-100">
      <div class="panel-head">
        <h2>University email</h2>
        <?php if (!empty($user['university_email_verified_at'])): ?><span class="st st-ok">Verified</span>
        <?php else: ?><span class="st st-muted">Not verified</span><?php endif; ?>
      </div>
      <div class="panel-body">
        <?php if (!empty($user['university_email_verified_at'])): ?>
          <p class="mb-1"><strong><?= e($user['university_email']) ?></strong></p>
          <p class="meta mb-0">Confirmed on <?= e(date('j M Y', strtotime($user['university_email_verified_at']))) ?>.</p>
        <?php else: ?>
          <p class="meta mb-3">
            <?= UNIVERSITY_EMAIL_DOMAINS
                ? 'Accepted domains: ' . e(implode(', ', UNIVERSITY_EMAIL_DOMAINS)) . '.'
                : 'Use an address ending in ' . e(UNIVERSITY_EMAIL_SUFFIX) . '.' ?>
          </p>
          <form method="post" class="d-flex gap-2 flex-wrap">
            <?= csrf_field() ?><input type="hidden" name="action" value="email_send">
            <input class="form-control" type="email" name="university_email" placeholder="you@students.ac.ke" required style="max-width:280px">
            <button class="btn btn-primary">Send code</button>
          </form>
          <?php if ($emailCode && (int)$emailCode['seconds_left'] > 0): ?>
            <form method="post" class="d-flex gap-2 flex-wrap mt-3">
              <?= csrf_field() ?><input type="hidden" name="action" value="email_verify">
              <input class="form-control verify-code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required style="max-width:180px">
              <button class="btn btn-outline-primary">Verify</button>
            </form>
            <p class="meta mt-2 mb-0">Code sent to <?= e($emailCode['destination']) ?>.</p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="panel">
      <div class="panel-head">
        <h2>Student status</h2>
        <?php if (!empty($user['student_verified_at'])): ?><span class="st st-ok">Approved</span>
        <?php elseif ($studentReq && $studentReq['status'] === 'pending'): ?><span class="st st-warn">Pending review</span>
        <?php elseif ($studentReq && $studentReq['status'] === 'rejected'): ?><span class="st st-bad">Rejected</span>
        <?php else: ?><span class="st st-muted">Not submitted</span><?php endif; ?>
      </div>
      <div class="panel-body">
        <?php if (!empty($user['student_verified_at'])): ?>
          <p class="meta mb-0">Approved on <?= e(date('j M Y', strtotime($user['student_verified_at']))) ?>. Your registration number and evidence stay private.</p>
        <?php elseif ($studentReq && $studentReq['status'] === 'pending'): ?>
          <p class="mb-2">Your request is waiting for an administrator.</p>
          <dl class="row mb-0 small">
            <dt class="col-sm-3">Name</dt><dd class="col-sm-9"><?= e($studentReq['full_name']) ?></dd>
            <dt class="col-sm-3">Registration</dt><dd class="col-sm-9"><?= e($studentReq['registration_number']) ?></dd>
            <dt class="col-sm-3">University</dt><dd class="col-sm-9"><?= e($studentReq['university']) ?></dd>
            <dt class="col-sm-3">Submitted</dt><dd class="col-sm-9 mb-0"><?= e(date('j M Y H:i', strtotime($studentReq['created_at']))) ?></dd>
          </dl>
        <?php else: ?>
          <?php if ($studentReq && $studentReq['status'] === 'rejected'): ?>
            <div class="sc-error mb-3">Rejected: <?= e($studentReq['admin_note'] ?: 'No reason was recorded.') ?></div>
          <?php endif; ?>
          <p class="meta mb-3">An administrator checks these details. Only approved requests earn the verified student badge.</p>
          <form method="post" enctype="multipart/form-data" class="row g-3">
            <?= csrf_field() ?><input type="hidden" name="action" value="student_submit">
            <div class="col-md-4">
              <label class="form-label" for="sv-name">Full name</label>
              <input class="form-control" id="sv-name" name="full_name" value="<?= e($studentReq['full_name'] ?? $user['full_name']) ?>" maxlength="100" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="sv-reg">Registration number</label>
              <input class="form-control" id="sv-reg" name="registration_number" value="<?= e($studentReq['registration_number'] ?? '') ?>" maxlength="60" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="sv-uni">University</label>
              <input class="form-control" id="sv-uni" name="university" value="<?= e($studentReq['university'] ?? '') ?>" maxlength="120" required>
            </div>
            <div class="col-md-8">
              <label class="form-label" for="sv-ev">Evidence (optional)</label>
              <input class="form-control" id="sv-ev" name="evidence" type="file" accept=".pdf,.jpg,.jpeg,.png">
              <div class="form-text">Student ID or admission letter, PDF or image up to 3MB. Never shown publicly.</div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button class="btn btn-primary w-100">Submit for review</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';