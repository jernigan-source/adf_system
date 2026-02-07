<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

// Only admin can download backups
if (!$auth->hasRole('admin')) {
    http_response_code(403);
    die('Akses ditolak. Hanya admin yang bisa download backup.');
}

if (!isset($_GET['file'])) {
    http_response_code(400);
    die('File tidak ditemukan.');
}

$filename = basename($_GET['file']);
$filePath = __DIR__ . '/../backups/' . $filename;

// Security check: only allow .sql files
if (pathinfo($filename, PATHINFO_EXTENSION) !== 'sql') {
    http_response_code(403);
    die('Tipe file tidak diizinkan.');
}

if (!file_exists($filePath)) {
    http_response_code(404);
    die('File tidak ditemukan.');
}

// Force download
header('Content-Description: File Transfer');
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

// Clear output buffer
ob_clean();
flush();

// Read file and send to output
readfile($filePath);
exit;
