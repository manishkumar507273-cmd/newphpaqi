<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Find the latest file modification time across frontend directories
$base_dir = dirname(__DIR__);
$dirs = [
    $base_dir,
    $base_dir . '/pages',
    $base_dir . '/components',
    $base_dir . '/js',
    $base_dir . '/css',
    $base_dir . '/api'
];

$latest_mtime = 0;
foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $files = @scandir($dir);
    if (!$files) continue;
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === 'check_update.php') continue;
        $path = $dir . '/' . $file;
        if (is_file($path)) {
            $mtime = @filemtime($path);
            if ($mtime && $mtime > $latest_mtime) {
                $latest_mtime = $mtime;
            }
        }
    }
}

echo json_encode([
    'latest_mtime' => $latest_mtime,
    'server_time' => time()
]);
