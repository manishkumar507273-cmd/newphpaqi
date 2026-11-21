<?php
/**
 * Smart WeatherNet - Node 1 Backend Configuration
 * Primary Active Telemetry Station (SMVITM Campus)
 * Path: php-backend/node 1/config.php
 */

return [
    'node_id'    => '1',
    'node_code'  => 'NODE1',
    'node_name'  => 'Node 1 - SMVITM Campus',
    'status'     => 'ONLINE',
    'is_active'  => true,

    'tables' => [
        'aqi_live'           => 'AQI_LIVE_NODE1',
        'aqi_historical'     => 'AQI_NODE1',
        'weather_live'       => 'WEATHER_LIVE_NODE1',
        'weather_historical' => 'WEATHER_NODE1',
        'forecast_24h'       => 'aqi_forecasts_24h',
    ],

    'location' => [
        'name'      => 'SMVITM Campus',
        'latitude'  => 13.2611,
        'longitude' => 74.7869,
        'timezone'  => 'Asia/Kolkata',
    ]
];
