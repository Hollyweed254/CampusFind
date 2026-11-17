<?php $cur = basename($_SERVER['SCRIPT_NAME']);
$items = [
  'dashboard.php' => ['speedometer2', 'Dashboard'],
  'listings.php' => ['card-list', 'Listings'],
  'users.php' => ['people', 'Users'],
  'verifications.php' => ['patch-check', 'Verification'],
  'user-reports.php' => ['shield-exclamation', 'Abuse'],
  'transactions.php' => ['receipt', 'Transactions'],
  'payments.php' => ['phone', 'Payments'],
  'flags.php' => ['flag', 'Reported'],
  'reports.php' => ['bar-chart', 'Reports'],
  'audit-logs.php' => ['journal-text', 'Audit'],
]; ?>
<nav class="admin-tabs" aria-label="Admin sections">
  <?php foreach ($items as $f => [$ic, $label]): ?>
    <a class="nav-link <?= $cur === $f ? 'active' : '' ?>" href="<?= url('admin/' . $f) ?>"<?= $cur === $f ? ' aria-current="page"' : '' ?>><i class="bi bi-<?= $ic ?>"></i><?= $label ?></a>
  <?php endforeach; ?>
</nav>
