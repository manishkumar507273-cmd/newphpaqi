<?php
// Secure ID proof document viewer
session_start();

$file = isset($_GET['file']) ? basename($_GET['file']) : '';
if (empty($file)) {
    http_response_code(400);
    echo "Bad Request: No file specified.";
    exit;
}

$proofs_dir = __DIR__ . '/../data/proofs';
$target_file = $proofs_dir . '/' . $file;

if (!file_exists($target_file) || !is_file($target_file)) {
    // Check fallback in system temp directory if local data was redirected
    $fallback = sys_get_temp_dir() . '/phpAQI_proofs/' . $file;
    if (file_exists($fallback) && is_file($fallback)) {
        $target_file = $fallback;
    } else {
        http_response_code(404);
        echo "File not found: The requested proof document does not exist or has been removed.";
        exit;
    }
}

$ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
$mime_types = [
    'pdf'  => 'application/pdf',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp'
];

$content_type = isset($mime_types[$ext]) ? $mime_types[$ext] : 'application/octet-stream';

header('Content-Type: ' . $content_type);
header('Content-Length: ' . filesize($target_file));
header('Content-Disposition: inline; filename="' . basename($target_file) . '"');
header('Cache-Control: private, max-age=3600');

readfile($target_file);
exit;
