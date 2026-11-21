<?php
/**
 * Smart WeatherNet - Node 2 Configuration
 * Secondary Standby Telemetry Station (Field Station 2)
 * Path: php-frontend/node 2/config.php
 */

return [
    'id'          => '2',
    'code'        => 'NODE2',
    'name'        => 'Node 2 - Station 2 (Standby)',
    'short_name'  => 'Node 2',
    'station_tag' => 'Station 2 (Standby)',
    'status'      => 'STANDBY',
    'is_active'   => false,
    'has_data'    => false,

    'location'    => [
        'campus'         => 'Station 2 (Field Station)',
        'address'        => 'Secondary Telemetry Station, Sensor Grid Expansion, Karnataka, India',
        'latitude'       => null,
        'longitude'      => null,
        'coords_display' => '--',
        'elevation'      => '--',
        'placement'      => 'Agricultural & Meteorological Field Sensor Mast'
    ],

    'tables'      => [
        'aqi_live'           => 'AQI_LIVE_NODE2',
        'aqi_historical'     => 'AQI_NODE2',
        'weather_live'       => 'WEATHER_LIVE_NODE2',
        'weather_historical' => 'WEATHER_NODE2',
        'forecast_24h'       => 'aqi_forecasts_node2_24h',
    ],

    'sensors'     => [
        'soil_temperature' => 'DS18B20 Stainless Steel Subsurface Soil Temperature Probe',
        'soil_humidity'    => 'Capacitive Soil Moisture & Hydration Sensor V2.0',
        'soil_ph'          => 'Industrial Digital Soil pH Electrode Sensor (0-14 pH)',
        'wind_speed'       => '3-Cup Pulse Digital Anemometer',
        'wind_gust'        => 'Peak Wind Gust Transient Analyzer',
        'wind_direction'   => 'Precision 16-Compass Point Potentiometer Wind Vane',
        'rain_gauge'       => '0.2mm Calibrated Tipping Bucket Rain Gauge',
        'pressure'         => 'BME280 High Precision Barometric Pressure Sensor',
        'uv_index'         => 'GUVA-S12SD Photodiode Solar UV Sensor'
    ],

    'description' => 'Secondary monitoring station provisioned for grid expansion. Currently in standby mode; awaiting live sensor telemetry transmission.'
];
