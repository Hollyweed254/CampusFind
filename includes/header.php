<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/chat.php';
$me = current_user();
$navUnread = $me ? chat_unread_count((int)$me['id']) : 0;
$theme = (($_COOKIE['cf_theme'] ?? '') === 'dark') ? 'dark' : 'light';   // cookie preference
$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . APP_NAME : APP_NAME;
$bareLayout = !empty($bareLayout);   // page opts out of navbar/footer
?><!DOCTYPE html>
<html lang="en" data-bs-theme="<?= e($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<?php if (!empty($ogImage)): ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(APP_NAME) ?>">
<meta property="og:title" content="<?= e($ogTitle ?? $pageTitle) ?>">
<?php if (!empty($ogDescription)): ?><meta property="og:description" content="<?= e($ogDescription) ?>">
<?php endif; ?>
<?php if (!empty($ogUrl)): ?><meta property="og:url" content="<?= e($ogUrl) ?>">
<?php endif; ?>
<meta property="og:image" content="<?= e($ogImage) ?>">
<?php if (!empty($ogImageAlt)): ?><meta property="og:image:alt" content="<?= e($ogImageAlt) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= url('assets/css/style.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
<?php
// ---- Header state: active page, user initials, theme link --------------------------------
$script   = basename($_SERVER['SCRIPT_NAME'] ?? '');
$inAdmin  = strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/admin/') !== false;
$navActive = static function (array $files) use ($script, $inAdmin): string {
    return (!$inAdmin && in_array($script, $files, true)) ? ' active" aria-current="page' : '';
};
$themeHref = url('theme.php?t=' . ($theme === 'dark' ? 'light' : 'dark') . '&back=' . urlencode($_SERVER['REQUEST_URI']));
$initials = '';
$firstName = '';
if ($me) {
    $parts = preg_split('/\s+/', trim($me['full_name'])) ?: [];
    $firstName = $parts[0] ?? '';
    $initials = strtoupper(mb_substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
}
?>
<?php if (!$bareLayout): ?>
<a class="visually-hidden-focusable sc-skip" href="#main">Skip to content</a>
<nav class="navbar navbar-expand-lg sc-nav sticky-top" aria-label="Main navigation">
  <div class="container">

    <!-- Left: brand -->
    <a class="sc-brand" href="<?= url('index.php') ?>" aria-label="<?= e(APP_NAME) ?> home">
      <span class="sc-brand-mark"><i class="bi bi-bag-heart-fill"></i></span>
      <span class="sc-brand-text">Campus<span>Find</span></span>
    </a>

    <button class="navbar-toggler sc-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"
            aria-controls="nav" aria-expanded="false" aria-label="Toggle menu">
      <i class="bi bi-list"></i>
    </button>

    <div id="nav" class="collapse navbar-collapse">

      <!-- Centre: main navigation -->
      <ul class="navbar-nav sc-menu-links mx-lg-auto">
        <li class="nav-item"><a class="nav-link<?= $navActive(['marketplace.php', 'listing.php', 'report-listing.php']) ?>" href="<?= url('marketplace.php') ?>"><i class="bi bi-shop"></i>Marketplace</a></li>
        <?php if ($me): ?>
          <li class="nav-item"><a class="nav-link<?= $navActive(['my-listings.php', 'edit-listing.php', 'create-listing.php']) ?>" href="<?= url('my-listings.php') ?>"><i class="bi bi-card-list"></i>My listings</a></li>
          <li class="nav-item"><a class="nav-link<?= $navActive(['messages.php']) ?>" href="<?= url('messages.php') ?>"><i class="bi bi-chat-dots"></i>Messages<?php if ($navUnread): ?><span class="nav-count"><?= $navUnread > 99 ? '99+' : $navUnread ?></span><?php endif; ?></a></li>
        <?php endif; ?>
        <?php if (is_admin()): ?>
          <li class="nav-item"><a class="nav-link<?= $inAdmin ? ' active" aria-current="page' : '' ?>" href="<?= url('admin/dashboard.php') ?>"><i class="bi bi-shield-check"></i>Admin</a></li>
        <?php endif; ?>
      </ul>

      <!-- Right: search, theme, account -->
      <div class="sc-actions">
        <form class="sc-search" method="get" action="<?= url('marketplace.php') ?>" role="search">
          <button type="submit" aria-label="Search"><i class="bi bi-search"></i></button>
          <input type="search" name="q" placeholder="Search listings" aria-label="Search listings" maxlength="80">
        </form>

        <a class="sc-nav-icon" href="<?= e($themeHref) ?>" title="Switch to <?= $theme === 'dark' ? 'light' : 'dark' ?> theme" aria-label="Switch to <?= $theme === 'dark' ? 'light' : 'dark' ?> theme">
          <i class="bi bi-<?= $theme === 'dark' ? 'sun' : 'moon-stars' ?>"></i>
        </a>

        <?php if ($me): ?>
          <!-- Desktop: profile dropdown -->
          <div class="dropdown d-none d-lg-block">
            <button class="sc-user" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
              <span class="sc-avatar"><?= e($initials) ?></span>
              <span class="sc-user-name"><?= e($firstName) ?></span>
              <i class="bi bi-chevron-down sc-caret"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end sc-dropdown">
              <li class="sc-dd-head">
                <div class="fw-semibold text-truncate"><?= e($me['full_name']) ?></div>
                <div class="meta text-truncate"><?= e($me['email']) ?></div>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('profile.php') ?>"><i class="bi bi-person"></i>My profile</a></li>
              <li><a class="dropdown-item" href="<?= url('transactions.php') ?>"><i class="bi bi-receipt"></i>Transactions</a></li>
              <li><a class="dropdown-item" href="<?= url('verification.php') ?>"><i class="bi bi-patch-check"></i>Verification</a></li>
              <li><a class="dropdown-item" href="<?= url('messages.php') ?>"><i class="bi bi-chat-dots"></i>Messages<?php if ($navUnread): ?> <span class="badge text-bg-primary"><?= $navUnread > 99 ? '99+' : $navUnread ?></span><?php endif; ?></a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item sc-dd-danger" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right"></i>Log out</a></li>
            </ul>
          </div>

          <!-- Mobile / tablet: account links shown inline in the menu -->
          <div class="sc-mobile-account d-lg-none">
            <a class="sc-mobile-user" href="<?= url('profile.php') ?>">
              <span class="sc-avatar"><?= e($initials) ?></span>
              <span class="text-truncate"><?= e($me['full_name']) ?></span>
            </a>
            <a class="sc-btn-ghost" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right"></i>Log out</a>
          </div>
        <?php else: ?>
          <div class="sc-auth">
            <a class="sc-btn-ghost" href="<?= url('login.php') ?>">Log in</a>
            <a class="sc-btn-lime" href="<?= url('register.php') ?>">Create account</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
<?php endif; ?>
<main id="main" class="<?= $bareLayout ? 'flex-grow-1 d-flex flex-column' : 'container py-4 py-lg-5 flex-grow-1' ?>">
<?php $flashItems = flashes(); if ($flashItems): ?>
<div class="cf-flash-stack" role="status" aria-live="polite">
  <?php foreach ($flashItems as [$t, $m]):
    $flashIcon = ['success' => 'bi-check-circle-fill', 'danger' => 'bi-exclamation-octagon-fill',
                  'warning' => 'bi-exclamation-triangle-fill'][$t] ?? 'bi-info-circle-fill'; ?>
    <div class="alert alert-<?= e($t) ?> alert-dismissible fade show cf-flash" role="alert">
      <i class="bi <?= $flashIcon ?> cf-flash-icon" aria-hidden="true"></i>
      <div class="cf-flash-text"><?= e($m) ?></div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>