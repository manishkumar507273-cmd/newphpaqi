<?php
/**
 * Smart WeatherNet - Node 2 Backend Configuration
 * Secondary Standby Telemetry Station (Field Station 2)
 * Path: php-backend/node 2/config.php
 */

return [
    'node_id'    => '2',
    'node_code'  => 'NODE2',
    'node_name'  => 'Node 2 - Station 2 (Standby)',
    'status'     => 'STANDBY',
    'is_active'  => false,

    'tables' => [
        'aqi_live'           => 'AQI_LIVE_NODE2',
        'aqi_historical'     => 'AQI_NODE2',
        'weather_live'       => 'WEATHER_LIVE_NODE2',
        'weather_historical' => 'WEATHER_NODE2',
        'forecast_24h'       => 'aqi_forecasts_node2_24h',
    ],

    'location' => [
        'name'      => 'Station 2 (Standby)',
        'latitude'  => null,
        'longitude' => null,
        'timezone'  => 'Asia/Kolkata',
    ]
];
