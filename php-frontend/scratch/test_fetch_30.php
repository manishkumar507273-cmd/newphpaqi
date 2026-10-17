<?php
// Test fetching last 30 historical records and filtering by today IST
$env = parse_ini_file('c:/Users/deeks/OneDrive/Documents/AQi10/newphpaqi/php-frontend/.env');
$supabaseUrl = $env['VITE_SUPABASE_URL'] ?? $env['SUPABASE_URL'];
$supabaseKey = $env['VITE_SUPABASE_ANON_KEY'] ?? $env['SUPABASE_KEY'];

$url = "{$supabaseUrl}/rest/v1/WEATHER_NODE1?order=id.desc&limit=30";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . $supabaseKey,
    'Authorization: Bearer ' . $supabaseKey
]);
$res = curl_exec($ch);
curl_close($ch);

$rows = json_decode($res, true);
$today_ist = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
echo "Today IST: $today_ist\n";
echo "Rows returned: " . count($rows) . "\n";

$today_rain = 0.0;
foreach ($rows as $r) {
    $tsRaw = $r['timestamp_hour'] ?? '';
    // Convert to IST
    $dt = new DateTime($tsRaw);
    $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
    $istDate = $dt->format('Y-m-d');
    $istHour = $dt->format('Y-m-d H:i');
    $val = (float)($r['rain_gauge'] ?? 0);
    if ($istDate === $today_ist) {
        $today_rain += $val;
        echo "Match: $istHour => Rain: $val\n";
    }
}
echo "Calculated Today Rain Sum: " . round($today_rain, 2) . " mm\n";
