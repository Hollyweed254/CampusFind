</main>
<?php if (empty($bareLayout)): ?>
<footer class="site-footer">
  <div class="container">
    <div class="footer-main">
      <div class="footer-brand-col">
        <a class="footer-brand" href="<?= url('index.php') ?>">
          <span class="sc-brand-mark"><i class="bi bi-bag-heart-fill"></i></span>
          CampusFind
        </a>
        <p class="footer-tagline">The campus marketplace for students.</p>
      </div>
      <div class="footer-col">
        <h3>Explore</h3>
        <ul>
          <li><a href="<?= url('marketplace.php') ?>">All listings</a></li>
          <li><a href="<?= url('marketplace.php?type=product') ?>">Products</a></li>
          <li><a href="<?= url('marketplace.php?type=service') ?>">Student services</a></li>
          <li><a href="<?= url('index.php#categories') ?>">Categories</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h3>Account</h3>
        <ul>
          <?php if ($me): ?>
            <li><a href="<?= url('create-listing.php') ?>">Post a listing</a></li>
            <li><a href="<?= url('my-listings.php') ?>">My listings</a></li>
            <li><a href="<?= url('profile.php') ?>">My profile</a></li>
            <?php if (is_admin()): ?><li><a href="<?= url('admin/dashboard.php') ?>">Admin dashboard</a></li><?php endif; ?>
          <?php else: ?>
            <li><a href="<?= url('login.php') ?>">Log in</a></li>
            <li><a href="<?= url('register.php') ?>">Create account</a></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &middot; <?= e(CAMPUS_NAME) ?></span>
      <span>Payments go directly between students.</span>
    </div>
  </div>
</footer>
<?php endif; ?>

<!-- Confirmation modal (used by [data-confirm]) -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content sc-modal">
  <div class="modal-body p-4">
    <h2 class="sc-modal-title" id="confirmTitle">Are you sure?</h2>
    <p class="text-body-secondary mb-4" id="confirmText"></p>
    <div class="d-flex gap-2 justify-content-end">
      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      <button type="button" class="btn btn-danger" id="confirmOk">Confirm</button>
    </div>
  </div></div></div></div>

<!-- Reason modal (admin remove / reject) -->
<div class="modal fade" id="reasonModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content sc-modal">
  <form method="post" class="modal-body p-4" id="reasonForm">
    <?= csrf_field() ?>
    <input type="hidden" name="id" id="reasonId"><input type="hidden" name="action" id="reasonAction"><input type="hidden" name="back" id="reasonBack">
    <h2 class="sc-modal-title" id="reasonTitle">Remove this listing?</h2>
    <p class="text-body-secondary" id="reasonText"></p>
    <label class="form-label fw-semibold" for="reasonNotes">Reason</label>
    <textarea class="form-control mb-4" name="notes" id="reasonNotes" rows="3" maxlength="255" required placeholder="Reason"></textarea>
    <div class="d-flex gap-2 justify-content-end">
      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-danger" id="reasonOk">Confirm</button>
    </div>
  </form></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/app.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body>
</html>