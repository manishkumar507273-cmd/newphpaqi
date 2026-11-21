# Node 1 - Primary Active Telemetry Station (Frontend)

This directory contains all pages, views, and configuration dedicated to **Node 1 (SMVITM Campus)**.

## Files & Views
- `config.php`: Station 1 specifications, sensor suite, GPS coordinates, and Supabase table bindings.
- `overview.php`: Primary real-time campus dashboard displaying live Air Quality Index, active sensor gauges, live thermal readings, and recent telemetry trends.
- `live.php`: Single unified Live Telemetry feed for Node 1 combining real-time AQI and Weather stream telemetry in one responsive layout.
- `historical.php`: Analytics archive for Node 1 querying `AQI_NODE1` and `WEATHER_NODE1`.
- `predictive.php`: Neural network 24-hour predictive forecast and advisory engine for Node 1 (`aqi_forecasts_24h`).
- `weather.php`: Dedicated weather view for Node 1.

## Node 1 Metadata
- **Node ID**: `1`
- **Location**: SMVITM Campus, Bantakal, Udupi (13°15'40" N, 74°47'13" E)
- **Status**: `ONLINE` / `ACTIVE`
- **Tables**: `AQI_LIVE_NODE1`, `AQI_NODE1`, `WEATHER_LIVE_NODE1`, `WEATHER_NODE1`, `aqi_forecasts_24h`
