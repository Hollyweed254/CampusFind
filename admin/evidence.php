<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$r = q('SELECT evidence_path, full_name FROM student_verifications WHERE id = ?', [$id])->fetch();
if (!$r || !$r['evidence_path']) { http_response_code(404); exit('Evidence not found.'); }
$file = dirname(__DIR__) . '/storage/verifications/' . basename($r['evidence_path']);
if (!is_file($file)) { http_response_code(404); exit('Evidence not found.'); }
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mimes = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
if (!isset($mimes[$ext])) { http_response_code(415); exit('Unsupported file.'); }

header('Content-Type: ' . $mimes[$ext]);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="student-evidence-' . $id . '.' . $ext . '"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($file);
