
A modern intelligence platform for real-time air quality index (AQI) telemetry and meteorological monitoring, powered by **PHP**, **JavaScript**, and **Supabase Cloud**.

🌐 **Live Deployment:** [http://localhost:8001](http://localhost:8001)

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
├── php-frontend/
│   ├── api/                       # API Handlers for telemetry and requests
│   │   ├── submit_request.php     # Dataset request processing & webhook forwarder
│   │   └── telemetry.php          # High-performance parallel telemetry engine
│   ├── components/                # Reusable PHP UI components
│   │   ├── auth-modal.php         # Authentication dialog
│   │   ├── brand-assets.php       # Official SVG/PNG brand assets
│   │   └── layout.php             # Main site shell, navigation, and mobile menu
│   ├── css/                       # Custom stylesheets
│   │   └── index.css              # Custom styling, fonts, and responsive rules
│   ├── js/                        # Client-side JavaScript
│   │   ├── api.js                 # API client fetching telemetry from Supabase REST API
│   │   └── router.js              # SPA client router with prefetching & instant page swaps
│   ├── pages/                     # Application Views
│   │   ├── historical.php         # Historical analytics, charts, dataset request modal
│   │   ├── live.php               # Live AQI telemetry dashboard
│   │   ├── overview.php           # Hardware specifications and system overview
│   │   ├── predictive.php         # 24-48h AI forecast engine & rainfall probability
│   │   └── weather.php            # Live weather telemetry dashboard
│   └── index.php                  # Application entry point and router
├── php-backend/                   # AI Forecasting and Python models
│   ├── cpcb_calculator.py         # Indian CPCB AQI sub-index calculator
│   ├── generate_forecast.py       # Hourly neural forecast inference script
│   └── rainfall_model_runner.py   # SAPE 24h Wavelet-LSTM rainfall prediction model
├── forcast/                       # Trained neural network weights (.pt, .keras, .joblib)
└── README.md                      # Project documentation
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
