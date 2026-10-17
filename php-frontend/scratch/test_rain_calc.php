<?php
$res = file_get_contents('http://localhost:8001/api/telemetry.php?action=weather_history&limit=50');
$data = json_decode($res, true);
$history = $data['history'] ?? [];
$today_str = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
echo "Today IST: $today_str\n";

$today_rain = 0.0;
$count = 0;
foreach ($history as $h) {
    $ts = $h['timestamp'] ?? '';
    if (strpos($ts, $today_str) === 0) {
        $count++;
        $r = (float)($h['rain_gauge'] ?? $h['rain'] ?? 0);
        $today_rain += $r;
        echo "Hour: $ts, Rain: $r\n";
    }
}
echo "Total today records: $count, Total Rain Sum: $today_rain mm\n";
