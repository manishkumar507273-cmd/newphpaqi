
# Smart WeatherNet

A modern intelligence platform for real-time air quality index (AQI) telemetry and meteorological monitoring, powered by **PHP**, **JavaScript**, and **Supabase Cloud**.

- 🚀 **Vercel Deployment:** [https://smart-airnet-admin.vercel.app](https://smart-airnet-admin.vercel.app)
- 💻 **Local Development Server:** [http://localhost:8001](http://localhost:8001)

---

## 🌟 Tech Stack 

| Layer | Technology |
|---|---|
| **Frontend/Backend** | PHP 8.x, Vanilla JavaScript, Lucide Icons, Vanilla CSS & Tailwind-like Utility Classes |
| **Data Source** | Supabase Cloud REST API (`AQI_LIVE_NODE1`, `AQI_NODE1`, `WEATHER_LIVE_NODE1`, `WEATHER_NODE1`) |
| **Design System** | Dark Slate Theme (`#0f172a`), Space Grotesk & JetBrains Mono typography, Glassmorphism, Micro-animations |

---

## ✨ Features

- **Real-Time Air Quality Index (CPCB India Standard)**:
  - Dynamic display of CPCB AQI values across 6 risk bands (*Good*, *Satisfactory*, *Moderate*, *Poor*, *Very Poor*, *Severe*).
  - Sub-index tracking for individual pollutants: $\text{PM}_{2.5}$, $\text{PM}_{10}$, $\text{CO}$, $\text{NO}_2$, and $\text{O}_3$.
- **Air Quality Visualizations**:
  - **AQI Line & Bar Charts**: Area line and bar plots tracking overall Air Quality Index progression over 24-hour periods using Chart.js.
  - **All Pollutants Comparison**: Multi-line chart tracking $\text{PM}_{2.5}$, $\text{PM}_{10}$, $\text{CO}$, $\text{NO}_2$, and $\text{O}_3$ with toggleable chip selectors.
- **Hardware Sensor Intelligence**:
  - Interactive specifications, WHO thresholds, health guidance, and technical datasheets for physical sensors.
- **Live Weather Telemetry**:
  - Real-time tracking of Ambient Temperature (°C), Relative Humidity (%), Wind Speed (km/h), Wind Direction (°), and Precipitation Gauge (mm).
- **Historical Telemetry Archive**:
  - Historical table view and charts for past 24-hour logs.
- **Enterprise Dataset Delivery**:
  - Authenticated requests for historical datasets, dispatched directly to the requester's email via secure SMTP.

---

## 📁 Project Structure

```text
newphpaqi/
├── php-frontend/                  # Web Interface & Telemetry Application
│   │
│   ├── node 1/                    # 🟢 NODE 1: All Primary Station Pages & Config
│   │   ├── config.php             # Station 1 table bindings & metadata
│   │   ├── overview.php           # Real-time campus overview
│   │   ├── live.php               # Single unified live feed (AQI + Weather)
│   │   ├── historical.php         # 24-hour historical telemetry archive
│   │   ├── predictive.php         # 24-48h AI forecast engine
│   │   ├── weather.php            # Dedicated weather view
│   │   └── README.md              # Node 1 documentation
│   │
│   ├── node 2/                    # 🟠 NODE 2: All Secondary Station Pages & Config
│   │   ├── config.php             # Station 2 table bindings & metadata
│   │   ├── overview.php           # Station 2 atmosphere snapshot (Standby)
│   │   ├── live.php               # Unified live feed with AQI & Weather sub-tab toggles
│   │   ├── weather.php            # Direct weather view alias
│   │   ├── historical.php         # Standby historical archive
│   │   ├── predictive.php         # Standby AI forecast view
│   │   └── README.md              # Node 2 documentation
│   │
│   ├── components/                # 🌐 Shared UI Components (Layout, Header, Modals)
│   ├── api/                       # 🌐 Shared Backend Proxies & Telemetry API
│   ├── config/                    # 🌐 Central Node Registry (nodes.php)
│   ├── assets/                    # 🌐 Logos, Icons, Static Brand Assets
│   ├── css/                       # 🌐 Global Stylesheets
│   ├── js/                        # 🌐 Global Client Scripts (api.js, router.js)
│   ├── data/                      # 🌐 Research Dataset Request Proofs
│   ├── index.php                  # Main Application Entry Point & Page Router
│   ├── router.php                 # PHP Built-in Server Router
│   └── manifest.json              # Progressive Web App (PWA) Manifest
│
├── php-backend/                   # AI Forecasting & Ingestion Engines
│   │
│   ├── node 1/                    # 🟢 NODE 1: Ingestion & ML Model Runners
│   │   ├── config.php             # Node 1 backend credentials & table mappings
│   │   ├── cpcb_calculator.py     # CPCB sub-index calculator for Station 1
│   │   ├── generate_forecast.py   # Station 1 hourly neural forecast inference
│   │   ├── rainfall_model_runner.py # Wavelet-LSTM rainfall model runner
│   │   ├── index.php              # Node 1 API dispatcher
│   │   └── README.md              # Node 1 backend docs
│   │
│   ├── node 2/                    # 🟠 NODE 2: Ingestion & ML Model Runners
│   │   ├── config.php             # Node 2 backend credentials & table mappings
│   │   ├── cpcb_calculator.py     # CPCB sub-index calculator for Station 2
│   │   ├── generate_forecast.py   # Station 2 neural forecast inference
│   │   ├── rainfall_model_runner.py # Station 2 rainfall model runner
│   │   ├── index.php              # Node 2 API dispatcher
│   │   └── README.md              # Node 2 backend docs
│   │
│   ├── index.php                  # Master Node Dispatcher (?node=1 or ?node=2)
│   ├── .env                       # Backend Environment Variables
│   └── .htaccess                  # Apache Directory & Security Settings
│
├── forcast/                       # 🧠 Pre-trained Neural Models (.pt, .keras, .joblib)
│   ├── newAQIPrediction/          # SWT Res-CNN BiLSTM AQI models
│   └── rainfall_model/            # Multi-weather 24h Keras & Joblib scalers
│
├── index.php                      # Root Entry Point (Redirects to php-frontend/)
├── requirements.txt               # Python Dependencies for AI Models
├── run.bat                        # One-click Server Starter
├── vercel.json                    # Vercel Serverless Deployment Configuration
└── README.md                      # Project Documentation
```

---

## 🚀 Quick Start

### 1. Start the PHP Built-in Server

If you have PHP installed on your machine, you can run the project using the built-in development server:

```bash
cd php-frontend
php -S localhost:8001 router.php
```
- **Application URL**: [http://localhost:8001](http://localhost:8001)

Alternatively, deploy the `php-frontend` directory to any standard Apache/Nginx web server with PHP support (e.g., XAMPP, WAMP, or an Ubuntu VPS).

---

## 🔌 Core Integrations

- **Supabase Cloud**: The UI directly reads telemetry from Supabase REST APIs via `js/api.js`.
- **Chart.js**: Client-side charting renders historical progress curves based on the telemetry returned from the cloud backend.
