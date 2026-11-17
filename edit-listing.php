<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$me = require_login();
$id = (int)($_GET['id'] ?? 0);
$l = q('SELECT * FROM listings WHERE id = ?', [$id])->fetch();
// Ownership check: only the seller may edit their own listing
if (!$l || (int)$l['seller_id'] !== (int)$me['id']) { http_response_code(403); exit('403 - You can only edit your own listings.'); }
if ($l['moderation_status'] === 'removed') { flash('danger', 'This listing was removed by an administrator and cannot be edited.'); redirect('my-listings.php'); }

$cats = categories_all(); $errors = [];
$d = $l;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$d, $errors] = validate_listing($cats);
    $existing = q('SELECT id, image_path FROM listing_images WHERE listing_id = ?', [$id])->fetchAll();
    $del = array_map('intval', $_POST['delete_img'] ?? []);
    $remaining = count(array_filter($existing, fn($i) => !in_array((int)$i['id'], $del, true)));
    if (!$errors) {
        q("UPDATE listings SET category_id=?, title=?, description=?, price=?, item_condition=?, contact_method=?, contact_value=?, moderation_status='pending', inspected_at=NULL, inspected_by=NULL WHERE id=? AND seller_id=?",
          [$d['category_id'], $d['title'], $d['description'], $d['price'], $d['item_condition'], $d['contact_method'], $d['contact_value'], $id, $me['id']]);
        foreach ($existing as $im) if (in_array((int)$im['id'], $del, true)) {
            q('DELETE FROM listing_images WHERE id = ? AND listing_id = ?', [$im['id'], $id]);
            $p = __DIR__ . '/uploads/' . basename($im['image_path']); if (is_file($p)) @unlink($p);
        }
        foreach (handle_uploads($id, $remaining) as $ue) flash('warning', $ue);
        flash('success', 'Listing updated and sent for review.');
        redirect('my-listings.php');
    }
    if ($d['price'] === null) $d['price'] = '';
    $d['contact_value'] = $d['contact_value'] ?? '';
}
$existingImgs = q('SELECT id, image_path FROM listing_images WHERE listing_id = ?', [$id])->fetchAll();
$pageTitle = 'Edit listing'; $submitLabel = 'Save changes';
require __DIR__ . '/includes/header.php'; ?>
<div class="form-shell">
  <div class="page-head">
    <div>
      <h1 class="page-title d-flex align-items-center gap-2 flex-wrap">Edit listing <?= status_badge($l['moderation_status']) ?></h1>
      <p>Changes are reviewed before going live again.</p>
    </div>
  </div>
  <div class="sc-card"><?php include __DIR__ . '/includes/listing_form.php'; ?></div>
</div>
<?php require __DIR__ . '/includes/footer.php';