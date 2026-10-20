import os
import sys
import json
import math
import argparse
import joblib
import numpy as np
import urllib.request
import urllib.parse
from datetime import datetime, timezone, timedelta

# Force PyTorch backend for Keras 3
os.environ['KERAS_BACKEND'] = 'torch'
import keras

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_DIR = os.path.abspath(os.path.join(BASE_DIR, ".."))

if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

from cpcb_calculator import calculate_aqi, calculate_sub_index, get_cpcb_category, CPCB_BREAKPOINTS

def load_env_file():
    """Load key-value pairs from system environment and candidate .env files."""
    env_vars = dict(os.environ)
    candidate_paths = [
        os.path.join(PROJECT_DIR, ".env"),
        os.path.join(BASE_DIR, ".env"),
        os.path.abspath(os.path.join(PROJECT_DIR, "..", ".env")),
        os.path.join(PROJECT_DIR, "php-frontend", ".env")
    ]
    for p in candidate_paths:
        if os.path.exists(p):
            try:
                with open(p, "r", encoding="utf-8") as f:
                    for line in f:
                        line = line.strip()
                        if not line or line.startswith("#"):
                            continue
                        if "=" in line:
                            k, v = line.split("=", 1)
                            k = k.strip()
                            v = v.strip().strip("'\"")
                            if k not in env_vars:
                                env_vars[k] = v
            except Exception:
                pass
    return env_vars

ENV = load_env_file()

# Supabase configuration (with fallback to default)
SUPABASE_URL = os.environ.get("SUPABASE_URL") or ENV.get("VITE_SUPABASE_URL") or ENV.get("SUPABASE_URL") or "https://sgkdpliqlhgiqsabxzxe.supabase.co"
SUPABASE_KEY = os.environ.get("SUPABASE_KEY") or ENV.get("VITE_SUPABASE_ANON_KEY") or ENV.get("SUPABASE_KEY") or "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNna2RwbGlxbGhnaXFzYWJ4enhlIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQ4NzgzMTIsImV4cCI6MjEwMDQ1NDMxMn0.vMtbXomFdmOcBkhhSoiyYyp_vFxOhg4MYCFCw9-pL30"

# Search candidate paths for the forecast model directory
POSSIBLE_FORCAST_DIRS = [
    os.path.join(PROJECT_DIR, "forcast"),
    os.path.join(PROJECT_DIR, "forecast"),
    os.path.join(PROJECT_DIR, "old_AQI_forcast"),
    os.path.abspath(os.path.join(PROJECT_DIR, "..", "forcast")),
    os.path.abspath(os.path.join(PROJECT_DIR, "..", "forecast")),
    os.path.abspath(os.path.join(PROJECT_DIR, "..", "old_AQI_forcast")),
    os.path.join(PROJECT_DIR, "models")
]

FORCAST_DIR = None
for p in POSSIBLE_FORCAST_DIRS:
    if os.path.isdir(p) and os.path.exists(os.path.join(p, "scaler_X.save")):
        FORCAST_DIR = p
        break

if not FORCAST_DIR:
    FORCAST_DIR = os.path.join(PROJECT_DIR, "forcast")

SCALER_X_PATH = os.path.join(FORCAST_DIR, "scaler_X.save")
SCALER_Y_PATH = os.path.join(FORCAST_DIR, "scaler_y.save")
OUTPUT_JSON_PATH = os.path.join(PROJECT_DIR, "php-frontend", "api", "forecast_data.json")

# Multi-tier architecture: 168h down to 24h
SUPPORTED_TIERS = [168, 144, 120, 96, 72, 48, 24]

def fetch_supabase(table, order_col="timestamp_hour", limit=250):
    url = f"{SUPABASE_URL}/rest/v1/{table}?order={order_col}.desc&limit={limit}"
    req = urllib.request.Request(url, headers={
        "apikey": SUPABASE_KEY,
        "Authorization": f"Bearer {SUPABASE_KEY}",
        "Content-Type": "application/json"
    })
    try:
        with urllib.request.urlopen(req, timeout=15) as resp:
            return json.loads(resp.read().decode('utf-8'))
    except Exception as e:
        print(f"Error fetching from table {table}: {e}", file=sys.stderr)
        return []

def get_weather_icon(hour, temp, humidity, aqi):
    is_night = (hour >= 19 or hour < 6)
    if humidity > 85:
        return '🌧️' if humidity > 90 else '🌦️'
    if aqi > 100:
        return '🌫️'
    if is_night:
        return '🌙' if humidity < 75 else '☁️'
    if temp > 32:
        return '☀️'
    if humidity > 70:
        return '⛅'
    return '🌤️'

def get_condition_text(temp, humidity, aqi):
    if aqi > 200:
        return 'Severely Polluted'
    if aqi > 100:
        return 'Moderate Air Discomfort'
    if humidity > 88:
        return 'Humid with Rain Risk'
    if humidity > 78:
        return 'Humid & Overcast'
    if temp > 33:
        return 'Warm & Sunny'
    if temp < 24:
        return 'Mild & Clear'
    return 'Clean & Partly Cloudy'

def select_model_tier(available_hours, aqi_table):
    """
    Select model tier strictly based on available data points:
    - Default to the 168-hour model whenever data >= 168h.
    - Only use a different (smaller) model if the data points are less than 168h.
    """
    # If 168 or more continuous hourly data points exist, always use the 168h model
    if available_hours >= 168:
        explanation = f"Found {available_hours} consecutive hourly records in {aqi_table} (>= 168h) -> Triggered 168-Hour Tier Model"
        return 168, explanation

    # Only when data points are LESS than 168 hours, cascade down to smaller models
    for t in SUPPORTED_TIERS:
        if t == 168:
            continue
        if available_hours >= t:
            idx = SUPPORTED_TIERS.index(t)
            higher_tier = SUPPORTED_TIERS[idx - 1]
            explanation = f"Found {available_hours} hourly records in {aqi_table} (< {higher_tier}h) -> Auto-triggered smaller {t}-Hour Tier Model"
            return t, explanation

    return 24, f"Found only {available_hours} hourly records in {aqi_table} (< 24h) -> Triggered base 24-Hour Tier Model with historical padding"

def sync_supabase_forecast_table(hourly_list, chosen_tier, now_ist):
    """
    Deletes the old 24h forecast rows and inserts the fresh 24h predictions
    into table aqi_forecasts_24h in Supabase.
    """
    if not SUPABASE_URL or not SUPABASE_KEY:
        print("Warning: Supabase credentials not configured, skipping aqi_forecasts_24h sync.", file=sys.stderr)
        return False

    table_url = f"{SUPABASE_URL}/rest/v1/aqi_forecasts_24h"
    headers = {
        "apikey": SUPABASE_KEY,
        "Authorization": f"Bearer {SUPABASE_KEY}",
        "Content-Type": "application/json",
        "Prefer": "return=minimal"
    }

    # 1. Delete previous forecast records so the table only keeps the latest 24 hours
    delete_url = f"{table_url}?id=gt.0"
    try:
        del_req = urllib.request.Request(delete_url, headers=headers, method="DELETE")
        with urllib.request.urlopen(del_req, timeout=15) as resp:
            print(f"Cleared previous records from aqi_forecasts_24h (HTTP {resp.getcode()})", file=sys.stderr)
    except Exception as e:
        print(f"Notice: clearing old forecast records: {e}", file=sys.stderr)

    # 2. Insert the fresh 24 hourly forecast rows
    records = []
    gen_time_str = now_ist.isoformat()
    for item in hourly_list:
        records.append({
            "node_id": "node_1",
            "forecast_for_time": item['iso'],
            "forecast_generated_at": gen_time_str,
            "pm2_5_ug_m3": float(item['pm25']),
            "pm10_ug_m3": float(item['pm10']),
            "no2_ug_m3": float(item['no2']),
            "co_mg_m3": float(item['co']),
            "ozone_ug_m3": float(item['o3']),
            "temperature_c": float(item['temp']),
            "humidity_pct": float(item['humidity']),
            "tier_used": int(chosen_tier)
        })

    try:
        post_req = urllib.request.Request(
            table_url,
            data=json.dumps(records).encode("utf-8"),
            headers=headers,
            method="POST"
        )
        with urllib.request.urlopen(post_req, timeout=15) as resp:
            print(f"Successfully inserted {len(records)} new forecast rows into aqi_forecasts_24h (HTTP {resp.getcode()})", file=sys.stderr)
            return True
    except Exception as e:
        print(f"Error inserting new forecast rows into aqi_forecasts_24h: {e}", file=sys.stderr)
        return False

def main():
    parser = argparse.ArgumentParser(description="Official AQI multi-tier forecast generator")
    parser.add_argument("--tier", type=str, default=None, help=argparse.SUPPRESS)
    parser.add_argument("--aqi-table", type=str, default=None, help="Supabase table storing hourly AQI node data (e.g. AQI_NODE1)")
    parser.add_argument("--weather-table", type=str, default=None, help="Supabase table storing hourly weather data (e.g. WEATHER_NODE1)")
    parser.add_argument("--max-hours", type=int, default=None, help="Simulate/cap available hours to test tier fallbacks")
    args = parser.parse_args()

    # Determine tables from CLI args or .env
    aqi_table = args.aqi_table or ENV.get("SOURCE_AQI_TABLE") or ENV.get("TABLE_AQI_HISTORICAL") or "AQI_NODE1"
    weather_table = args.weather_table or ENV.get("TABLE_WEATHER_HISTORICAL") or "WEATHER_NODE1"

    print(f"Reading hourly AQI input from table: {aqi_table}", file=sys.stderr)
    print(f"Reading hourly weather input from table: {weather_table}", file=sys.stderr)
    print(f"Using forecast assets from: {FORCAST_DIR}", file=sys.stderr)

    if not os.path.exists(SCALER_X_PATH) or not os.path.exists(SCALER_Y_PATH):
        raise FileNotFoundError(f"Scaler files missing in {FORCAST_DIR}")

    feat_scaler = joblib.load(SCALER_X_PATH)
    target_scaler = joblib.load(SCALER_Y_PATH)

    # Fetch historical hourly telemetry
    aqi_rows = fetch_supabase(aqi_table, "timestamp_hour", limit=250)
    weather_rows = fetch_supabase(weather_table, "timestamp_hour", limit=250)

    # Weather lookup map by IST hour
    weather_by_hour = {}
    for w in weather_rows:
        ts_str = w.get('timestamp_hour')
        if ts_str:
            try:
                if '+00' in ts_str or ts_str.endswith('Z'):
                    dt = datetime.fromisoformat(ts_str.replace('Z', '+00:00'))
                    dt_ist = dt.astimezone(timezone(timedelta(hours=5, minutes=30)))
                    key = dt_ist.strftime('%Y-%m-%d %H')
                else:
                    key = ts_str[:13].replace('T', ' ')
                weather_by_hour[key] = float(w.get('wind_speed') or 0.0)
            except Exception:
                pass

    DEFAULT_WIND_SPEED = float(ENV.get("WIND_SPEED_BASELINE", 7.86))

    # Parse and sort chronologically
    parsed_rows = []
    for r in aqi_rows:
        ts_str = r.get('timestamp_hour', '')
        if ts_str:
            try:
                dt = datetime.fromisoformat(ts_str.replace('Z', ''))
                parsed_rows.append((dt, r))
            except Exception:
                pass

    parsed_rows.sort(key=lambda x: x[0])
    total_available_hours = len(parsed_rows)

    if total_available_hours == 0:
        raise ValueError(f"No hourly AQI telemetry records found in table {aqi_table} to generate forecast.")

    # Optional simulation of having less data
    if args.max_hours is not None and args.max_hours > 0:
        available_hours = min(total_available_hours, args.max_hours)
        parsed_rows = parsed_rows[-available_hours:]
    else:
        available_hours = total_available_hours

    # Trigger model tier strictly based on available data points:
    # 168h model wants 168h data; only if data points are less, smaller model is triggered
    chosen_tier, tier_reason = select_model_tier(available_hours, aqi_table)
    model_filename = f"model_tier_{chosen_tier}h.keras"
    model_path = os.path.join(FORCAST_DIR, model_filename)

    if not os.path.exists(model_path):
        for t in SUPPORTED_TIERS:
            candidate = os.path.join(FORCAST_DIR, f"model_tier_{t}h.keras")
            if os.path.exists(candidate):
                model_path = candidate
                chosen_tier = t
                model_filename = f"model_tier_{chosen_tier}h.keras"
                break

    print(f"{tier_reason}", file=sys.stderr)
    print(f"Loading {model_path} for inference...", file=sys.stderr)
    model = keras.models.load_model(model_path)

    # Slice EXACTLY the required chosen_tier hours from the hourly nodes table
    if len(parsed_rows) >= chosen_tier:
        aqi_tier_input = [x[1] for x in parsed_rows[-chosen_tier:]]
    else:
        oldest = parsed_rows[0][1]
        padding = [oldest] * (chosen_tier - len(parsed_rows))
        aqi_tier_input = padding + [x[1] for x in parsed_rows]

    # Build input tensor using the 12 features expected by scaler_X
    # Feature order: ['pm2_5_ug_m3', 'pm10_ug_m3', 'no2_ug_m3', 'co_mg_m3', 'ozone_ug_m3',
    #                'temperature_c', 'humidity_pct', 'wind_speed_kmh', 'hour_sin', 'hour_cos',
    #                'month_sin', 'month_cos']
    input_timesteps = []
    for row in aqi_tier_input:
        ts_str = row.get('timestamp_hour', '')
        try:
            dt = datetime.fromisoformat(ts_str.replace('Z', ''))
        except Exception:
            dt = datetime.now()

        hour = dt.hour
        month = dt.month

        pm25 = float(row.get('pm2.5') or row.get('pm25') or 15.0)
        pm10 = float(row.get('pm10') or 25.0)
        no2 = float(row.get('no2') or 5.0)
        co = float(row.get('co') or 0.2)
        o3 = float(row.get('o3') or 30.0)
        temp = float(row.get('temperature') or 28.0)
        humidity = float(row.get('humidity') or 70.0)

        hour_key = dt.strftime('%Y-%m-%d %H')
        wind_spd = weather_by_hour.get(hour_key, DEFAULT_WIND_SPEED)

        hour_sin = math.sin(2 * math.pi * hour / 24.0)
        hour_cos = math.cos(2 * math.pi * hour / 24.0)
        month_sin = math.sin(2 * math.pi * month / 12.0)
        month_cos = math.cos(2 * math.pi * month / 12.0)

        raw_12 = np.array([[pm25, pm10, no2, co, o3, temp, humidity, wind_spd, hour_sin, hour_cos, month_sin, month_cos]])
        scaled_12 = feat_scaler.transform(raw_12)[0]
        input_timesteps.append(scaled_12)

    X = np.array([input_timesteps], dtype=np.float32)

    print(f"Input tensor shape: {X.shape} (Batch: 1, Timesteps: {chosen_tier}, Features: 12)", file=sys.stderr)
    print("Running model prediction...", file=sys.stderr)
    preds = model.predict(X, verbose=0)[0]  # shape: (24, 7)
    physical_preds = target_scaler.inverse_transform(preds)

    now_ist = datetime.now(timezone(timedelta(hours=5, minutes=30)))
    # The forecast is strictly for upcoming hours: start from the next sharp clock hour
    start_dt = (now_ist + timedelta(hours=1)).replace(minute=0, second=0, microsecond=0)

    hourly_list = []
    temps = []
    aqis = []

    # Target order in scaler_y:
    # ['pm2_5_ug_m3', 'pm10_ug_m3', 'no2_ug_m3', 'co_mg_m3', 'ozone_ug_m3', 'temperature_c', 'humidity_pct']
    for i in range(24):
        target_dt = start_dt + timedelta(hours=i)
        h_vals = physical_preds[i]

        p_pm25 = max(0.5, round(float(h_vals[0]), 2))
        p_pm10 = max(1.0, round(float(h_vals[1]), 2))
        p_no2  = max(0.1, round(float(h_vals[2]), 2))
        p_co   = max(0.01, round(float(h_vals[3]), 3))
        p_o3   = max(1.0, round(float(h_vals[4]), 2))
        p_temp = round(float(h_vals[5]), 1)
        p_hum  = min(100.0, max(20.0, round(float(h_vals[6]), 1)))

        # Official CPCB AQI Calculation (cpcb_calculator.py)
        # Step 1: Piecewise linear interpolation for each criteria pollutant
        # Step 2: Maximum operator for overall AQI & dominant pollutant
        aqi_val, dom, sub_indices = calculate_aqi(p_pm25, p_pm10, p_no2, p_co, p_o3)
        cat_info = get_cpcb_category(aqi_val)

        temps.append(p_temp)
        aqis.append(aqi_val)

        time_label = target_dt.strftime('%I %p').lstrip('0')
        icon = get_weather_icon(target_dt.hour, p_temp, p_hum, aqi_val)

        hourly_list.append({
            'index': i,
            'hour': target_dt.hour,
            'label': time_label,
            'iso': target_dt.isoformat(),
            'aqi': aqi_val,
            'dominant': dom,
            'category': cat_info['category'],
            'severity': cat_info['severity'],
            'color': cat_info['color'],
            'sub_indices': sub_indices,
            'pm25': p_pm25,
            'pm10': p_pm10,
            'no2': p_no2,
            'co': p_co,
            'o3': p_o3,
            'temp': p_temp,
            'humidity': p_hum,
            'icon': icon,
            'isNow': (i == 0)
        })

    # Summary statistics
    current_aqi = aqis[0]
    current_dom = hourly_list[0]['dominant']
    current_sub_indices = hourly_list[0]['sub_indices']
    hero_cat = get_cpcb_category(current_aqi)

    aqi_low = min(aqis)
    aqi_high = max(aqis)
    current_temp = temps[0]
    current_hum = hourly_list[0]['humidity']
    day_low = min(temps)
    day_high = max(temps)

    param_stats = {
        'pm25': {
            'label': 'PM2.5 Particulate',
            'unit': 'µg/m³',
            'current': hourly_list[0]['pm25'],
            'current_subindex': current_sub_indices.get('PM2.5', 0.0),
            'min': round(min(h['pm25'] for h in hourly_list), 2),
            'max': round(max(h['pm25'] for h in hourly_list), 2),
            'mean': round(sum(h['pm25'] for h in hourly_list) / 24, 2),
            'safe_limit': '60.0 µg/m³'
        },
        'pm10': {
            'label': 'PM10 Particulate',
            'unit': 'µg/m³',
            'current': hourly_list[0]['pm10'],
            'current_subindex': current_sub_indices.get('PM10', 0.0),
            'min': round(min(h['pm10'] for h in hourly_list), 2),
            'max': round(max(h['pm10'] for h in hourly_list), 2),
            'mean': round(sum(h['pm10'] for h in hourly_list) / 24, 2),
            'safe_limit': '100.0 µg/m³'
        },
        'no2': {
            'label': 'Nitrogen Dioxide (NO₂)',
            'unit': 'µg/m³',
            'current': hourly_list[0]['no2'],
            'current_subindex': current_sub_indices.get('NO₂', 0.0),
            'min': round(min(h['no2'] for h in hourly_list), 2),
            'max': round(max(h['no2'] for h in hourly_list), 2),
            'mean': round(sum(h['no2'] for h in hourly_list) / 24, 2),
            'safe_limit': '80.0 µg/m³'
        },
        'co': {
            'label': 'Carbon Monoxide (CO)',
            'unit': 'mg/m³',
            'current': hourly_list[0]['co'],
            'current_subindex': current_sub_indices.get('CO', 0.0),
            'min': round(min(h['co'] for h in hourly_list), 3),
            'max': round(max(h['co'] for h in hourly_list), 3),
            'mean': round(sum(h['co'] for h in hourly_list) / 24, 3),
            'safe_limit': '2.0 mg/m³'
        },
        'o3': {
            'label': 'Surface Ozone (O₃)',
            'unit': 'µg/m³',
            'current': hourly_list[0]['o3'],
            'current_subindex': current_sub_indices.get('O₃', 0.0),
            'min': round(min(h['o3'] for h in hourly_list), 2),
            'max': round(max(h['o3'] for h in hourly_list), 2),
            'mean': round(sum(h['o3'] for h in hourly_list) / 24, 2),
            'safe_limit': '100.0 µg/m³'
        },
        'temp': {
            'label': 'Ambient Temperature',
            'unit': '°C',
            'current': current_temp,
            'min': round(day_low, 1),
            'max': round(day_high, 1),
            'mean': round(sum(temps) / 24, 1),
            'safe_limit': 'Campus Baseline'
        },
        'humidity': {
            'label': 'Relative Humidity',
            'unit': '%',
            'current': current_hum,
            'min': round(min(h['humidity'] for h in hourly_list), 1),
            'max': round(max(h['humidity'] for h in hourly_list), 1),
            'mean': round(sum(h['humidity'] for h in hourly_list) / 24, 1),
            'safe_limit': 'Comfort 40-70%'
        }
    }

    hero_condition = get_condition_text(current_temp, current_hum, current_aqi)

    # 24-Hour Timeline Phases: Grouping the 24 predicted hours into 4 distinct phases
    phase_definitions = [
        ('Late Afternoon / Evening', 0, 6, '🌅'),
        ('Night to Midnight', 6, 12, '🌙'),
        ('Early Morning Transition', 12, 18, '🌤️'),
        ('Daytime Afternoon Peak', 18, 24, '☀️')
    ]

    time_phases = []
    min_scale = 18.0
    max_scale = 38.0

    for idx, (p_name, s_idx, e_idx, p_icon) in enumerate(phase_definitions):
        subset = hourly_list[s_idx:e_idx]
        p_temps = [item['temp'] for item in subset]
        p_min_temp = min(p_temps)
        p_max_temp = max(p_temps)
        p_avg_aqi = int(round(sum(item['aqi'] for item in subset) / len(subset)))
        p_avg_pm25 = round(sum(item['pm25'] for item in subset) / len(subset), 1)
        p_avg_pm10 = round(sum(item['pm10'] for item in subset) / len(subset), 1)
        p_avg_co = round(sum(item['co'] for item in subset) / len(subset), 3)
        p_avg_no2 = round(sum(item['no2'] for item in subset) / len(subset), 1)
        p_avg_o3 = round(sum(item['o3'] for item in subset) / len(subset), 1)
        p_avg_hum = round(sum(item['humidity'] for item in subset) / len(subset), 1)
        p_dom = max(set(item['dominant'] for item in subset), key=[item['dominant'] for item in subset].count)

        left_pct = max(0, min(100, round(((p_min_temp - min_scale) / (max_scale - min_scale)) * 100)))
        right_pct = max(0, min(100, round(((p_max_temp - min_scale) / (max_scale - min_scale)) * 100)))
        width_pct = max(10, right_pct - left_pct)

        cond = get_condition_text((p_min_temp + p_max_temp) / 2, p_avg_hum, p_avg_aqi)

        if p_avg_aqi <= 50:
            advice = "Optimal air quality. Perfect window for campus athletics and natural classroom airflow."
        elif p_avg_aqi <= 100:
            advice = "Satisfactory atmospheric conditions. All regular campus and laboratory activities permitted."
        else:
            advice = f"Moderate air quality dominated by {p_dom}. Sensitive individuals should moderate intense outdoor exertion."

        time_phases.append({
            'index': idx,
            'phaseName': p_name,
            'hoursSpan': f"{subset[0]['label']} – {subset[-1]['label']}",
            'minTemp': p_min_temp,
            'maxTemp': p_max_temp,
            'leftPct': str(left_pct),
            'widthPct': str(width_pct),
            'condition': cond,
            'icon': p_icon,
            'aqi': p_avg_aqi,
            'dominant': p_dom,
            'humidity': p_avg_hum,
            'pm25': p_avg_pm25,
            'pm10': p_avg_pm10,
            'co': p_avg_co,
            'no2': p_avg_no2,
            'o3': p_avg_o3,
            'advice': advice
        })

    # Health Advisory
    if current_aqi <= 50:
        sev_label = 'GOOD'
        advisory_title = 'Air Quality Ideal (Good) · Minimal Health Impact'
        advisory_desc = f'Predicted ambient AQI is {current_aqi} (Dominant: {current_dom}). All criteria pollutant concentrations (PM2.5: {hourly_list[0]["pm25"]} µg/m³, PM10: {hourly_list[0]["pm10"]} µg/m³, O₃: {hourly_list[0]["o3"]} µg/m³) are well within national safe limits.'
        sports_adv = 'Optimal for athletic training and sports'
        vent_adv = 'Full campus natural cross-ventilation advised'
        hydr_adv = 'Standard hydration (2.5L/day recommended)'
    elif current_aqi <= 100:
        sev_label = 'SATISFACTORY'
        advisory_title = 'Air Quality Satisfactory · Minor Sensitive Group Impact'
        advisory_desc = f'Predicted ambient AQI is {current_aqi} (Dominant: {current_dom}, PM2.5: {hourly_list[0]["pm25"]} µg/m³). Minimal impact on healthy individuals; minor discomfort for sensitive persons.'
        sports_adv = 'Standard outdoor sports and exercise permitted'
        vent_adv = 'Normal classroom and lab ventilation'
        hydr_adv = 'Keep hydrated during warm afternoon peaks'
    elif current_aqi <= 200:
        sev_label = 'MODERATE'
        advisory_title = 'Air Quality Moderate · Breathing Discomfort with Sensitive Persons'
        advisory_desc = f'Predicted ambient AQI is {current_aqi} dominated by {current_dom} (PM2.5: {hourly_list[0]["pm25"]} µg/m³). People with respiratory sensitivities should moderate prolonged outdoor exposure.'
        sports_adv = 'Consider scheduling strenuous sports in early morning'
        vent_adv = 'Use filtered ventilation during peak hours'
        hydr_adv = 'Adequate fluid intake recommended'
    else:
        sev_label = 'POOR'
        advisory_title = f'Air Quality {hero_cat["category"]} · Health Alert'
        advisory_desc = f'Predicted ambient AQI is {current_aqi} dominated by {current_dom} (PM2.5: {hourly_list[0]["pm25"]} µg/m³). {hero_cat["health_impact"]}.'
        sports_adv = 'Avoid strenuous outdoor activities'
        vent_adv = 'Keep windows closed and utilize indoor filtration'
        hydr_adv = 'Frequent hydration and stay indoors where possible'

    payload = {
        'status': 'success',
        'type': 'aqi_forecast',
        'model_name': model_filename,
        'tier_hours': chosen_tier,
        'standard': 'National Air Quality Index (NAQI)',
        'generated_at': now_ist.strftime('%Y-%m-%d %H:%M:%S IST'),
        'tier_selection': {
            'aqi_table': aqi_table,
            'weather_table': weather_table,
            'available_hours': available_hours,
            'triggered_tier': chosen_tier,
            'model_filename': model_filename,
            'rule': '168h model requires 168h hourly data; if less, corresponding smaller model is triggered',
            'explanation': tier_reason,
            'tiers_available': SUPPORTED_TIERS
        },
        'hero': {
            'aqi': current_aqi,
            'dominant': current_dom,
            'category': hero_cat['category'],
            'severity': hero_cat['severity'],
            'color': hero_cat['color'],
            'health_impact': hero_cat['health_impact'],
            'sub_indices': current_sub_indices,
            'aqi_low': aqi_low,
            'aqi_high': aqi_high,
            'input_source': aqi_table,
            'input_hours': chosen_tier,
            'temp': current_temp,
            'humidity': current_hum,
            'dayLow': day_low,
            'dayHigh': day_high,
            'condition': hero_condition,
            'pm25': hourly_list[0]['pm25'],
            'pm10': hourly_list[0]['pm10'],
            'no2': hourly_list[0]['no2'],
            'co': hourly_list[0]['co'],
            'o3': hourly_list[0]['o3'],
            'confidence': 'High (Full 168h History)' if chosen_tier == 168 else (f'Standard ({chosen_tier}h History)')
        },
        'parameters': param_stats,
        'advisory': {
            'title': advisory_title,
            'severity': sev_label,
            'desc': advisory_desc,
            'sports': sports_adv,
            'ventilation': vent_adv,
            'hydration': hydr_adv
        },
        'hourly': hourly_list,
        'phases': time_phases
    }

    # Save to JSON file
    os.makedirs(os.path.dirname(OUTPUT_JSON_PATH), exist_ok=True)
    with open(OUTPUT_JSON_PATH, 'w', encoding='utf-8') as f:
        json.dump(payload, f, indent=2)

    # Sync to Supabase table aqi_forecasts_24h (delete old 24h, insert fresh 24h)
    sync_supabase_forecast_table(hourly_list, chosen_tier, now_ist)

    print(f"Successfully generated AQI forecast using {model_filename} (Triggered Tier: {chosen_tier}h from {aqi_table}). Saved to {OUTPUT_JSON_PATH}", file=sys.stderr)
    print(json.dumps(payload))

if __name__ == '__main__':
    main()
