<?php
/**
 * Smart WeatherNet - Node 2 Configuration
 * Secondary Telemetry Station (Field Station 2)
 * Path: php-frontend/node 2/config.php
 */

return [
    'id'          => '2',
    'code'        => 'NODE2',
    'name'        => 'Node 2 - Field Station',
    'short_name'  => 'Node 2',
    'station_tag' => 'Field Station 2',
    'status'      => 'ONLINE',
    'is_active'   => true,
    'has_data'    => true,

    'supabase'    => [
        'url'        => getenv('SUPABASE_URL_NODE2') ?: (getenv('VITE_SUPABASE_URL_NODE2') ?: 'https://nahabhzcaeppbteuxogg.supabase.co'),
        'anon_key'   => getenv('SUPABASE_ANON_KEY_NODE2') ?: (getenv('VITE_SUPABASE_ANON_KEY_NODE2') ?: 'sb_publishable_RXM8H-D8m6qWz2Yy2xA6VQ_TjxxY3bJ'),
        'secret_key' => getenv('SUPABASE_KEY_NODE2') ?: '',
    ],

    'location'    => [
        'campus'         => 'Station 2 (Field Station)',
        'address'        => 'Secondary Telemetry Station, Sensor Grid Expansion, Karnataka, India',
        'latitude'       => 13.2350,
        'longitude'      => 74.7420,
        'coords_display' => "13°14'06\" N, 74°44'31\" E",
        'elevation'      => '28m ASL',
        'placement'      => 'Agricultural & Meteorological Field Sensor Mast'
    ],

    'tables'      => [
        'aqi_live'           => 'AQI_LIVE_NODE2',
        'aqi_historical'     => 'AQI_NODE2',
        'weather_live'       => 'SOIL_LIVE_NODE2',
        'weather_historical' => 'SOIL_NODE2',
        'forecast_24h'       => 'aqi_forecasts_node2_24h',
    ],

    'sensors'     => [
        'pm25'              => 'Laser Dust Sensor (PM2.5)',
        'pm10'              => 'Laser Dust Sensor (PM10)',
        'co'                => 'CO Gas Sensor',
        'no2'               => 'NO2 Gas Sensor',
        'o3'                => 'Ozone Gas Sensor',
        'soil_temperature'  => 'DS18B20 Subsurface Soil Temperature Sensor',
        'soil_humidity'     => 'Capacitive Soil Moisture Sensor (Volumetric %)',
        'soil_ph'           => 'Industrial Digital Soil pH Electrode Sensor (0-14 pH)',
        'soil_conductivity' => 'Soil Electrical Conductivity Probe (µS/cm)',
        'wind_speed'        => 'Digital Anemometer',
        'wind_gust'         => 'Peak Wind Gust Sensor',
        'wind_direction'    => 'Wind Vane',
        'rain_gauge'        => 'Tipping Bucket Rain Gauge',
        'pressure'          => 'BME280 Barometric Pressure Sensor',
        'uv_index'          => 'Solar UV Sensor'
    ],

    'description' => 'Secondary field telemetry node operational with real-time air quality and soil subsurface telemetry.'
];
