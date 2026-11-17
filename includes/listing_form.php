<?php /* expects: $cats, $d (values), $errors, $existingImgs (array), $submitLabel */ 
$conds = ['new' => 'Brand new', 'like_new' => 'Like new', 'good' => 'Good', 'fair' => 'Fair'];
if ($errors): ?><div class="sc-error mb-3"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>

  <div class="form-section">
    <div class="form-section-head">
      <span class="form-section-icon"><i class="bi bi-card-text"></i></span>
      <div>
        <h2 class="form-section-title">Listing basics</h2>
      </div>
    </div>
    <div class="row g-3">
      <div class="col-md-8">
        <label class="form-label" for="lf-title">Title</label>
        <input class="form-control" id="lf-title" name="title" maxlength="120" value="<?= e($d['title']) ?>" placeholder="e.g. Casio FX-991 calculator" required>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="category">Category</label>
        <select class="form-select" name="category_id" id="category" required>
          <option value="">Choose&hellip;</option>
          <?php foreach (['product' => 'Products', 'service' => 'Services'] as $t => $label): ?>
            <optgroup label="<?= $label ?>">
              <?php foreach ($cats as $c) if ($c['listing_type'] === $t): ?>
                <option value="<?= $c['id'] ?>" data-type="<?= $t ?>" <?= (int)$d['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
              <?php endif; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label" for="lf-desc">Description</label>
        <textarea class="form-control" id="lf-desc" name="description" rows="5" maxlength="3000" placeholder="Condition, age, what's included, why you're selling&hellip;" required><?= e($d['description']) ?></textarea>
        <div class="form-text">At least 10 characters.</div>
      </div>
    </div>
  </div>

  <div class="form-section">
    <div class="form-section-head">
      <span class="form-section-icon"><i class="bi bi-cash-coin"></i></span>
      <div>
        <h2 class="form-section-title">Price &amp; condition</h2>
      </div>
    </div>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label" for="lf-price">Price (KSh)</label>
        <input class="form-control" type="number" step="0.01" min="0" id="lf-price" name="price" value="<?= e($d['price'] ?? '') ?>" placeholder="0">
        <div class="form-text" id="priceHelp">Leave blank for services to show "Contact for price".</div>
      </div>
      <div class="col-md-6" id="condBox">
        <label class="form-label" for="lf-cond">Condition</label>
        <select class="form-select" id="lf-cond" name="item_condition">
          <option value="">Choose&hellip;</option>
          <?php foreach ($conds as $k => $v): ?><option value="<?= $k ?>" <?= ($d['item_condition'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <div class="form-section">
    <div class="form-section-head">
      <span class="form-section-icon"><i class="bi bi-chat-dots"></i></span>
      <div>
        <h2 class="form-section-title">How buyers reach you</h2>
        <p class="form-section-hint">Your contact details are only shown to logged-in students.</p>
      </div>
    </div>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label" for="lf-method">Contact method</label>
        <select class="form-select" id="lf-method" name="contact_method">
          <?php foreach (['whatsapp' => 'WhatsApp', 'phone' => 'Phone call', 'email' => 'Email'] as $k => $v): ?>
            <option value="<?= $k ?>" <?= $d['contact_method'] === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-8">
        <label class="form-label" for="lf-contact">Contact details</label>
        <input class="form-control" id="lf-contact" name="contact_value" value="<?= e($d['contact_value']) ?>" placeholder="07xx xxx xxx or you@example.com">
      </div>
    </div>
  </div>

  <div class="form-section">
    <div class="form-section-head">
      <span class="form-section-icon"><i class="bi bi-images"></i></span>
      <div>
        <h2 class="form-section-title">Photos</h2>
        <p class="form-section-hint">Up to <?= MAX_IMAGES ?> images, JPG/PNG/WebP, 2MB each.</p>
      </div>
    </div>
    <div class="image-picker">
      <input class="form-control" id="lf-images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
    </div>
    <?php if (!empty($existingImgs)): ?>
      <label class="form-label mt-3">Current images</label>
      <div class="existing-images">
        <?php foreach ($existingImgs as $im): ?>
          <label class="existing-image">
            <img src="<?= e(thumb($im['image_path'])) ?>" alt="">
            <span><input type="checkbox" class="form-check-input me-1" name="delete_img[]" value="<?= $im['id'] ?>">Remove</span>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="sc-note mt-3">Listings are reviewed by an admin before they appear. Illegal, counterfeit or stolen items are not allowed.</div>  <div class="form-actions">
    <button class="btn btn-primary"><?= e($submitLabel) ?></button>
    <a class="btn btn-outline-secondary" href="<?= url('my-listings.php') ?>">Cancel</a>
  </div>
</form>