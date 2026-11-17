<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true,
        'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

/* ---------- helpers ---------- */
function redirect(string $path): never { header('Location: ' . url($path)); exit; }

function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

/* ---------- CSRF ---------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
    if (!$ok) { http_response_code(403); exit('Invalid or expired form. Go back and try again.'); }
}
function require_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST required.'); }
    csrf_check();
}

/* ---------- authentication ---------- */
function current_user(): ?array {
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $u = q('SELECT id, full_name, email, phone, role, status FROM users WHERE id = ?', [$_SESSION['uid']])->fetch();
            if ($u && $u['status'] === 'active') $user = $u; else unset($_SESSION['uid']);
        }
    }
    return $user;
}
function is_admin(): bool { $u = current_user(); return $u && $u['role'] === 'admin'; }

function login_user(int $id): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
}
function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
function require_login(): array {
    $u = current_user();
    if (!$u) { flash('warning', 'Please log in first.'); redirect('login.php'); }
    return $u;
}
function require_admin(): array {
    $u = require_login();
    if ($u['role'] !== 'admin') { http_response_code(403); exit('403 - Admins only.'); }
    return $u;
}
