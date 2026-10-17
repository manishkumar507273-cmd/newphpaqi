<?php
$start = microtime(true);

// 1. Direct Supabase query (no recursive curl deadlock!)
$supabaseUrl = 'https://sgkdpliqlhgiqsabxzxe.supabase.co';
$supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNna2RwbGlxbGhnaXFzYWJ4enhlIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQ4NzgzMTIsImV4cCI6MjEwMDQ1NDMxMn0.vMtbXomFdmOcBkhhSoiyYyp_vFxOhg4MYCFCw9-pL30';
$table = 'AQI_NODE1';

$ch = curl_init("{$supabaseUrl}/rest/v1/{$table}?order=timestamp_hour.desc&limit=24");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 3,
    CURLOPT_HTTPHEADER => [
        "apikey: {$supabaseKey}",
        "Authorization: Bearer {$supabaseKey}"
    ]
]);
$raw = curl_exec($ch);
curl_close($ch);

$t1 = (microtime(true) - $start) * 1000;
echo "Supabase Fetch Time: " . round($t1) . " ms\n";
