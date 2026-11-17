<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$me = require_login();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$l = q("SELECT id, title, seller_id FROM listings WHERE id = ? AND moderation_status = 'approved'", [$id])->fetch();
if (!$l) { http_response_code(404); exit('Listing not found.'); }
if ((int)$l['seller_id'] === (int)$me['id']) { flash('warning', 'You cannot report your own listing.'); redirect('listing.php?id=' . $id); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $reason = clean('reason'); $details = clean('details');
    if (!in_array($reason, ['scam', 'prohibited', 'misleading', 'duplicate', 'other'], true)) $errors[] = 'Choose a reason.';
    if (mb_strlen($details) > 500) $errors[] = 'Details must be 500 characters or fewer.';
    if (!$errors) {
        if (q('SELECT id FROM listing_reports WHERE listing_id = ? AND reporter_id = ?', [$id, $me['id']])->fetch()) {
            flash('info', 'You have already reported this listing.');
        } else {
            q('INSERT INTO listing_reports (listing_id, reporter_id, reason, details) VALUES (?,?,?,?)', [$id, $me['id'], $reason, $details ?: null]);
            flash('success', 'Thanks. An admin will review your report.');
        }
        redirect('listing.php?id=' . $id);
    }
}
$pageTitle = 'Report listing'; require __DIR__ . '/includes/header.php'; ?>
<div class="narrow-shell">
  <div class="sc-card">
    <div class="auth-head">
      <span class="auth-head-icon"><i class="bi bi-flag"></i></span>
      <div>
        <h1>Report listing</h1>
        <p class="text-truncate"><?= e($l['title']) ?></p>
      </div>
    </div>
    <?php if ($errors): ?>
      <div class="sc-error mb-3"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
      <div class="mb-3">
        <label class="form-label" for="rp-reason">Reason</label>
        <select class="form-select" id="rp-reason" name="reason" required>
          <option value="">Choose a reason&hellip;</option>
          <option value="scam">Suspected scam / fraud</option>
          <option value="prohibited">Prohibited item or service</option>
          <option value="misleading">Misleading information</option>
          <option value="duplicate">Duplicate listing</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div class="mb-4">
        <label class="form-label" for="rp-details">Details <span class="text-body-secondary fw-normal">(optional)</span></label>
        <textarea class="form-control" id="rp-details" name="details" rows="3" maxlength="500" placeholder="Tell us what looks wrong"></textarea>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-danger">Submit report</button>
        <a class="btn btn-outline-secondary" href="<?= url('listing.php?id=' . $id) ?>">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';