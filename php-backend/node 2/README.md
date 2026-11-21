# Smart WeatherNet - Node 2 Backend

## Overview
This directory contains all backend services, REST API endpoints, AI forecasting scripts, and calculation models specifically dedicated to **Node 2 (Station 2 - Standby)**.

## Node Metadata
- **Node ID**: `2`
- **Station Name**: Station 2 (Standby / Expansion Node)
- **Status**: `STANDBY` / `RESERVED`
- **Coordinates**: `--` (Deployment Ready)

## Database Tables
- **Live AQI Telemetry**: `AQI_LIVE_NODE2`
- **Historical AQI Log**: `AQI_NODE2`
- **Live Weather Telemetry**: `WEATHER_LIVE_NODE2`
- **Historical Weather Log**: `WEATHER_NODE2`
- **AI 24-Hour Forecasts**: `aqi_forecasts_node2_24h`

## File Structure
- `config.php`: Node 2 backend configuration, standby flags, table mappings, and station metadata.
- `index.php`: Dedicated REST API microservice serving Node 2 endpoints, returning clean standby payloads (`[]` / `STANDBY`) when awaiting live sensor data.
- `cpcb_calculator.py`: CPCB AQI calculation algorithms provisioned for Node 2.
- `generate_forecast.py`: Machine learning forecasting engine configured for Node 2.
- `rainfall_model_runner.py`: Weather and precipitation prediction model configured for Node 2.

## API Endpoints
- `GET /php-backend/node 2/?action=aqi&limit=50`: Standby live AQI endpoint for Node 2.
- `GET /php-backend/node 2/?action=weather&limit=50`: Standby live weather endpoint for Node 2.
- `GET /php-backend/node 2/?action=historical&type=aqi&limit=100`: Historical dataset for Node 2.
- `GET /php-backend/node 2/?action=forecast&limit=24`: AI 24h predictions for Node 2.
