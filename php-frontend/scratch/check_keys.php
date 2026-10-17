<?php
$res = file_get_contents('http://localhost:8001/api/telemetry.php?action=weather_history&limit=20');
$d = json_decode($res, true);
echo "Sample historical record keys:\n";
if (!empty($d['history'][0])) {
    print_r($d['history'][0]);
}
