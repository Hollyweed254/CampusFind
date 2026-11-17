<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$me = require_login();
$cats = categories_all();
$d = ['title' => '', 'description' => '', 'category_id' => 0, 'price' => '', 'item_condition' => '', 'contact_method' => 'whatsapp', 'contact_value' => $me['phone'] ?? ''];
$errors = []; $existingImgs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$d, $errors] = validate_listing($cats);
    if (!$errors) {
        q('INSERT INTO listings (seller_id, category_id, title, description, price, item_condition, contact_method, contact_value) VALUES (?,?,?,?,?,?,?,?)',
          [$me['id'], $d['category_id'], $d['title'], $d['description'], $d['price'], $d['item_condition'], $d['contact_method'], $d['contact_value']]);
        $lid = (int)db()->lastInsertId();
        $upErr = handle_uploads($lid);
        flash('success', 'Listing submitted for review.');
        foreach ($upErr as $ue) flash('warning', $ue);
        redirect('my-listings.php');
    }
    if ($d['price'] === null) $d['price'] = '';
}
$pageTitle = 'Create listing'; $submitLabel = 'Submit for review';
require __DIR__ . '/includes/header.php'; ?>
<div class="form-shell">
  <div class="page-head">
    <div>
      <h1 class="page-title">Create a listing</h1>
    </div>
  </div>
  <div class="sc-card"><?php include __DIR__ . '/includes/listing_form.php'; ?></div>
</div>
<?php require __DIR__ . '/includes/footer.php';