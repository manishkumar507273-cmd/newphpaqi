<?php
// Development Server Security Router for php -S
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Block sensitive files and directories mimicking .htaccess security rules
if (preg_match('#(?:^|/)(?:\.env|\.git|data/.*\.json|composer\.(?:json|lock)|README\.md)#i', $uri)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "403 Forbidden: Direct access to this file is blocked.";
    exit;
}

$filePath = __DIR__ . $uri;
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false; // Serve static file as-is
}

require_once __DIR__ . '/index.php';

