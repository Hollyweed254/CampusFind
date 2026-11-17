<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/oauth.php';

if (current_user()) redirect('marketplace.php');

if (!empty($_GET['error'])) {
    flash('warning', 'Sign-in was cancelled.');
    redirect('login.php');
}

$state = (string)($_GET['state'] ?? '');
$pending = $_SESSION['oauth_states'][$state] ?? null;
unset($_SESSION['oauth_states'][$state]);
if (!$pending || time() - (int)$pending['ts'] > 900) {
    flash('warning', 'Your sign-in session expired. Please try again.');
    redirect('login.php');
}

$name = (string)$pending['provider'];
$provider = oauth_provider($name);
$code = (string)($_GET['code'] ?? '');
if (!$provider || !oauth_configured($name) || $code === '') {
    flash('warning', 'That sign-in attempt could not be completed. Please try again.');
    redirect('login.php');
}

try {
    $profile = oauth_fetch_profile($name, $provider, $code);
    $user = oauth_login_or_register($name, $profile);
} catch (RuntimeException $ex) {
    flash('warning', $provider['label'] . ' sign-in failed: ' . $ex->getMessage());
    redirect('login.php');
} catch (Throwable $ex) {
    flash('warning', 'Sign-in failed. Please try again.');
    redirect('login.php');
}

login_user((int)$user['id']);
flash('success', 'Logged in with ' . $provider['label'] . '.');
redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'marketplace.php');
