<?php
/**
 * Smart WeatherNet - Node 1 Configuration
 * Primary Active Telemetry Station (SMVITM Campus)
 * Path: php-frontend/node 1/config.php
 */

return [
    'id'          => '1',
    'code'        => 'NODE1',
    'name'        => 'Node 1 - SMVITM Campus',
    'short_name'  => 'Node 1',
    'station_tag' => 'SMVITM Campus',
    'status'      => 'ONLINE',
    'is_active'   => true,
    'has_data'    => true,

    'location'    => [
        'campus'         => 'SMVITM Campus',
        'address'        => 'Shri Madhwa Vadiraja Institute of Technology & Management, Vishwothama Nagar, Bantakal, Udupi - 574115, Karnataka, India',
        'latitude'       => 13.2611,
        'longitude'      => 74.7869,
        'coords_display' => "13°15'40\" N, 74°47'13\" E",
        'elevation'      => '32m ASL',
        'placement'      => 'Institutional Meteorological Mast (Building Roof / Ground Station)'
    ],

    'tables'      => [
        'aqi_live'           => 'AQI_LIVE_NODE1',
        'aqi_historical'     => 'AQI_NODE1',
        'weather_live'       => 'WEATHER_LIVE_NODE1',
        'weather_historical' => 'WEATHER_NODE1',
        'forecast_24h'       => 'aqi_forecasts_24h',
    ],

    'sensors'     => [
        'pm25'           => 'PMS5003 Laser Scattering Particle Counter (PM2.5)',
        'pm10'           => 'PMS5003 Laser Scattering Particle Counter (PM10)',
        'co'             => 'MQ-7 Semiconductor CO Gas Sensor',
        'no2'            => 'MiCS-2714 Metal Oxide NO2 Gas Sensor',
        'o3'             => 'MQ-131 Semiconductor Ozone Gas Sensor',
        'temperature'    => 'BME280 Digital Precision Temperature Sensor',
        'humidity'       => 'BME280 Digital Relative Humidity Sensor',
        'wind_speed'     => '3-Cup Pulse Digital Anemometer',
        'wind_gust'      => 'Peak Wind Gust Transient Analyzer',
        'wind_direction' => 'Precision 16-Compass Point Potentiometer Wind Vane',
        'rain_gauge'     => '0.2mm Calibrated Tipping Bucket Rain Gauge'
    ],

    'description' => 'Primary telemetry node operational on SMVITM institutional campus. Actively transmitting live air quality and weather telemetry to cloud.'
];
