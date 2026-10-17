<?php
$f = sys_get_temp_dir() . '/smartairnet_cache/' . md5('overview_bulk') . '.json';
if (file_exists($f)) unlink($f);

$res = file_get_contents('http://localhost:8001/api/telemetry.php?action=overview_bulk');
$d = json_decode($res, true);
echo "today_rain_total: " . ($d['today_rain_total'] ?? 'not found') . "\n";
