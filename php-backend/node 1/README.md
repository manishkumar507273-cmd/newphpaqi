# Smart WeatherNet - Node 1 Backend

## Overview
This directory contains all backend services, REST API endpoints, AI forecasting scripts, and calculation models specifically dedicated to **Node 1 (SMVITM Campus)**.

## Node Metadata
- **Node ID**: `1`
- **Station Name**: SMVITM Campus (Primary Station)
- **Status**: `ONLINE` / `ACTIVE`
- **Coordinates**: 13°15'40" N, 74°47'13" E (Elevation: 32m ASL)

## Database Tables
- **Live AQI Telemetry**: `AQI_LIVE_NODE1`
- **Historical AQI Log**: `AQI_NODE1`
- **Live Weather Telemetry**: `WEATHER_LIVE_NODE1`
- **Historical Weather Log**: `WEATHER_NODE1`
- **AI 24-Hour Forecasts**: `aqi_forecasts_24h`

## File Structure
- `config.php`: Node 1 backend configuration, table mappings, and station metadata.
- `index.php`: Dedicated REST API microservice serving Node 1 live telemetry, historical analytics, and forecasts.
- `cpcb_calculator.py`: Central Pollution Control Board (CPCB) Indian National AQI calculation model for Node 1.
- `generate_forecast.py`: Prophet / ML forecasting engine generating 24-hour predictions for Node 1.
- `rainfall_model_runner.py`: Weather and precipitation prediction model for Node 1.

## API Endpoints
- `GET /php-backend/node 1/?action=aqi&limit=50`: Fetch latest live AQI records for Node 1.
- `GET /php-backend/node 1/?action=weather&limit=50`: Fetch latest live weather records for Node 1.
- `GET /php-backend/node 1/?action=historical&type=aqi&limit=100`: Historical AQI dataset for Node 1.
- `GET /php-backend/node 1/?action=forecast&limit=24`: AI 24h predictions for Node 1.
