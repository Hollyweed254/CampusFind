<?php
// Sets the (non-sensitive) theme preference cookie, then returns to the previous local page.
$t = ($_GET['t'] ?? '') === 'dark' ? 'dark' : 'light';
setcookie('cf_theme', $t, ['expires' => time() + 60*60*24*180, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true, 'secure' => !empty($_SERVER['HTTPS'])]);
$back = $_GET['back'] ?? '/';
if (!preg_match('#^/[^/\\\\]#', $back) && $back !== '/') $back = '/';
header('Location: ' . $back);
exit;
