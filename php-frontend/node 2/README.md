# Node 2 - Secondary Standby Telemetry Station (Frontend)

This directory contains all pages, views, and configuration dedicated to **Node 2 - Station 2 (Standby)**.

## Architecture & Distinct Features
- **Live Telemetry & Meteorological View**:
  - `live.php`: Unified real-time telemetry page with responsive toggle between `[ AQI Live ]` and `[ Weather Live ]`.
    - Features full-screen animated sunset cumulus cloud backdrop on Weather view.
    - Responsive mobile layout with 2-column parameter grid and dedicated live streaming table.
    - Zero hardcoded values; all parameters dynamically streamed from cloud and defaulted to Standby (`--`).
  - `weather.php`: Dedicated alias that routes directly to Live Weather sub-tab on Node 2.
- `config.php`: Station 2 configuration, metadata, and database table bindings (`AQI_LIVE_NODE2`, `WEATHER_LIVE_NODE2`, etc.).
- `overview.php`: Station 2 Atmosphere Snapshot displaying standby state.
- `historical.php`: Analytics Archive with dual AQI and Weather telemetry charts and tables.
- `predictive.php`: Predictive forecasting in standby mode.

## Node 2 Metadata
- **Node ID**: `2`
- **Location**: Station 2 (Standby)
- **Status**: `STANDBY` (Awaiting physical hardware streaming)
- **Tables**: `AQI_LIVE_NODE2`, `AQI_NODE2`, `WEATHER_LIVE_NODE2`, `WEATHER_NODE2`, `aqi_forecasts_node2_24h`
