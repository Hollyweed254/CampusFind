<?php
// Setup check. DELETE THIS FILE before deploying publicly.
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain');
echo "PHP " . PHP_VERSION . "\n";
try {
    $n = db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    echo "Database OK. Users: $n\n";
    echo "Categories: " . db()->query('SELECT COUNT(*) FROM categories')->fetchColumn() . "\n";
} catch (Throwable $e) { echo "Database FAILED: " . $e->getMessage() . "\n"; }
echo "uploads/ writable: " . (is_writable(__DIR__ . '/uploads') ? 'yes' : 'NO') . "\n";
echo "fileinfo extension: " . (extension_loaded('fileinfo') ? 'yes' : 'NO (enable it in php.ini)') . "\n";
echo "GD/getimagesize: " . (function_exists('getimagesize') ? 'yes' : 'NO') . "\n";
