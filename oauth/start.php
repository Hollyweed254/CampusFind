<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/oauth.php';

if (current_user()) redirect('marketplace.php');

$name = (string)($_GET['provider'] ?? '');
$provider = oauth_provider($name);
if (!$provider) {
    http_response_code(400);
    exit('Unknown sign-in provider.');
}
if (!oauth_configured($name)) {
    flash('warning', $provider['label'] . ' sign-in is not configured yet.');
    redirect('login.php');
}

$state = bin2hex(random_bytes(24));
$_SESSION['oauth_states'][$state] = ['provider' => $name, 'ts' => time()];
if (count($_SESSION['oauth_states']) > 5) {
    $_SESSION['oauth_states'] = array_slice($_SESSION['oauth_states'], -5, null, true);
}

$params = [
    'client_id'     => $provider['client_id'],
    'redirect_uri'  => oauth_callback_url(),
    'response_type' => 'code',
    'scope'         => $provider['scope'],
    'state'         => $state,
    'prompt'        => 'select_account',
];

header('Location: ' . $provider['auth_url'] . '?' . http_build_query($params));
exit;
