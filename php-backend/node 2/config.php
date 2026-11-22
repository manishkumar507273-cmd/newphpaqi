<?php
/**
 * Smart WeatherNet - Node 2 Backend Configuration
 * Secondary Telemetry Station (Field Station 2)
 * Path: php-backend/node 2/config.php
 */

return [
    'node_id'    => '2',
    'node_code'  => 'NODE2',
    'node_name'  => 'Node 2 - Field Station',
    'status'     => 'ONLINE',
    'is_active'  => true,

    'supabase'   => [
        'url'        => getenv('SUPABASE_URL_NODE2') ?: 'https://nahabhzcaeppbteuxogg.supabase.co',
        'anon_key'   => getenv('SUPABASE_ANON_KEY_NODE2') ?: (getenv('VITE_SUPABASE_ANON_KEY_NODE2') ?: 'sb_publishable_RXM8H-D8m6qWz2Yy2xA6VQ_TjxxY3bJ'),
        'secret_key' => getenv('SUPABASE_KEY_NODE2') ?: '',
    ],

    'tables' => [
        'aqi_live'           => 'AQI_LIVE_NODE2',
        'aqi_historical'     => 'AQI_NODE2',
        'weather_live'       => 'SOIL_LIVE_NODE2',
        'weather_historical' => 'SOIL_NODE2',
        'forecast_24h'       => 'aqi_forecasts_node2_24h',
    ],

    'location' => [
        'name'      => 'Station 2 (Field Station)',
        'latitude'  => null,
        'longitude' => null,
        'timezone'  => 'Asia/Kolkata',
    ]
];
