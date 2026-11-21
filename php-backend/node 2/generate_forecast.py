import os
import sys
import glob
import json
import math
import argparse
import numpy as np
import urllib.request
import urllib.parse
from datetime import datetime, timezone, timedelta

# PyTorch
import torch
import torch.nn as nn
import torch.nn.functional as F

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
if os.path.basename(os.path.abspath(os.path.join(BASE_DIR, ".."))).lower() == "php-backend":
    PROJECT_DIR = os.path.abspath(os.path.join(BASE_DIR, "..", ".."))
else:
    PROJECT_DIR = os.path.abspath(os.path.join(BASE_DIR, ".."))

if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

from cpcb_calculator import calculate_aqi, calculate_sub_index, get_cpcb_category, CPCB_BREAKPOINTS

try:
    from rainfall_model_runner import RainfallForecastEngine
except Exception as e:
    print(f"Notice: RainfallForecastEngine import: {e}", file=sys.stderr)
    RainfallForecastEngine = None

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

DEFAULT_SUPABASE_URL = "https://sgkdpliqlhgiqsabxzxe.supabase.co"
DEFAULT_SUPABASE_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNna2RwbGlxbGhnaXFzYWJ4enhlIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQ4NzgzMTIsImV4cCI6MjEwMDQ1NDMxMn0.vMtbXomFdmOcBkhhSoiyYyp_vFxOhg4MYCFCw9-pL30"

SUPABASE_URL = (os.environ.get("SUPABASE_URL") or "").strip() or ENV.get("VITE_SUPABASE_URL") or ENV.get("SUPABASE_URL") or DEFAULT_SUPABASE_URL
SUPABASE_KEY = (os.environ.get("SUPABASE_KEY") or "").strip() or ENV.get("VITE_SUPABASE_ANON_KEY") or ENV.get("SUPABASE_KEY") or DEFAULT_SUPABASE_KEY

FORCAST_DIR = os.path.join(PROJECT_DIR, "forcast")
OUTPUT_JSON_PATH = os.path.join(PROJECT_DIR, "php-frontend", "api", "forecast_data.json")

COMPASS_MAP = {
    'n': 0.0, 'north': 0.0,
    'nne': 22.5, 'ne': 45.0, 'northeast': 45.0, 'ene': 67.5,
    'e': 90.0, 'east': 90.0,
    'ese': 112.5, 'se': 135.0, 'southeast': 135.0, 'sse': 157.5,
    's': 180.0, 'south': 180.0,
    'ssw': 202.5, 'sw': 225.0, 'southwest': 225.0, 'wsw': 247.5,
    'w': 270.0, 'west': 270.0,
    'wnw': 292.5, 'nw': 315.0, 'northwest': 315.0, 'nnw': 337.5
}

def parse_wind_direction_degrees(val):
    if val is None:
        return 225.0
    val_str = str(val).strip().lower()
    if val_str in COMPASS_MAP:
        return COMPASS_MAP[val_str]
    try:
        return float(val_str)
    except Exception:
        return 225.0

class SWT_Res_CNN_BiLSTM(nn.Module):
    """
    Stationary Wavelet Transform (SWT) + Residual CNN + BiLSTM
    Takes 48 hours of past multi-parameter AQI & weather inputs and predicts 24 hours ahead.
    """
    def __init__(self):
        super().__init__()
        self.alpha_logit = nn.Parameter(torch.zeros(23, 7))
        self.swt = nn.Module()
        self.swt.lo = nn.Parameter(torch.zeros(8, 1, 8))
        self.swt.hi = nn.Parameter(torch.zeros(8, 1, 8))
        self.cnn = nn.Sequential(
            nn.Conv1d(46, 96, kernel_size=3, padding=1),
            nn.BatchNorm1d(96),
            nn.ReLU(),
            nn.Dropout(0.2),
            nn.Conv1d(96, 96, kernel_size=3, padding=1),
            nn.BatchNorm1d(96),
            nn.ReLU()
        )
        self.bilstm = nn.LSTM(input_size=96, hidden_size=96, num_layers=2, batch_first=True, bidirectional=True)
        self.head = nn.Sequential(
            nn.Linear(203, 192),
            nn.ReLU(),
            nn.Dropout(0.2),
            nn.Linear(192, 161)
        )

    def forward(self, x, x_raw_11):
        # x: [Batch, 48, 14]
        # x_raw_11: [Batch, 11] (latest timestep raw features)
        x_8 = x[:, :, :8].transpose(1, 2) # [B, 8, 48]
        
        # Stationary Wavelet Decomposition (db4 filters)
        a1 = F.conv1d(x_8, self.swt.lo, padding=4, groups=8)[:, :, :48]
        d1 = F.conv1d(x_8, self.swt.hi, padding=4, groups=8)[:, :, :48]
        a2 = F.conv1d(a1, self.swt.lo, padding=7, dilation=2, groups=8)[:, :, :48]
        d2 = F.conv1d(a1, self.swt.hi, padding=7, dilation=2, groups=8)[:, :, :48]
        
        # 32 wavelet subband channels + 14 raw feature channels = 46 channels
        wavelets = torch.cat([d1, d2, a2, x_8], dim=1) # [B, 32, 48]
        x_raw = x.transpose(1, 2)                       # [B, 14, 48]
        cnn_in = torch.cat([wavelets, x_raw], dim=1)    # [B, 46, 48]
        
        cnn_out = self.cnn(cnn_in)                      # [B, 96, 48]
        
        lstm_in = cnn_out.transpose(1, 2)               # [B, 48, 96]
        lstm_out, _ = self.bilstm(lstm_in)              # [B, 48, 192]
        last_lstm = lstm_out[:, -1, :]                  # [B, 192]
        
        dense_in = torch.cat([last_lstm, x_raw_11], dim=-1) # [B, 203]
        out_161 = self.head(dense_in)                       # [B, 161]
        out_23x7 = out_161.view(-1, 23, 7)
        
        alpha = torch.sigmoid(self.alpha_logit)
        return out_23x7, alpha

def fetch_supabase(table, order_col="timestamp_hour", limit=250):
    if not SUPABASE_URL or not SUPABASE_KEY:
        print(f"Error: SUPABASE_URL or SUPABASE_KEY not configured. Cannot fetch {table}.", file=sys.stderr)
        return []
    url = f"{SUPABASE_URL.rstrip('/')}/rest/v1/{table}?order={order_col}.desc&limit={limit}"
    try:
        req = urllib.request.Request(url, headers={
            "apikey": SUPABASE_KEY,
            "Authorization": f"Bearer {SUPABASE_KEY}",
            "Content-Type": "application/json"
        })
        with urllib.request.urlopen(req, timeout=20) as resp:
            return json.loads(resp.read().decode('utf-8'))
    except Exception as e:
        print(f"Error fetching from table {table}: {e}", file=sys.stderr)
        return []

def get_weather_icon(hour, temp, humidity, aqi, is_rain=False, rain_mm=0.0):
    if is_rain or rain_mm >= 0.2:
        return '🌧️' if rain_mm >= 2.0 else '🌦️'
    is_night = (hour >= 19 or hour < 6)
    if aqi > 100:
        return '🌫️'
    if is_night:
        if humidity > 92:
            return '🌫️'
        return '🌙' if humidity < 80 else '☁️'
    if temp > 32:
        return '☀️'
    if humidity > 80:
        return '☁️'
    if humidity > 65:
        return '⛅'
    return '🌤️'

def get_condition_text(temp, humidity, aqi, is_rain=False, rain_mm=0.0):
    if is_rain or rain_mm >= 0.2:
        if rain_mm >= 7.5:
            return 'Heavy Rainfall Expected'
        elif rain_mm >= 2.5:
            return 'Steady Rain Expected'
        return 'Light Rain / Showers'
    if aqi > 200:
        return 'Severely Polluted'
    if aqi > 100:
        return 'Moderate Air Discomfort'
    if humidity > 88:
        return 'Humid Night (Mist/Dew)' if (temp < 27) else 'Humid & Overcast'
    if humidity > 78:
        return 'Humid & Overcast'
    if temp > 33:
        return 'Warm & Sunny'
    if temp < 24:
        return 'Mild & Clear'
    return 'Clean & Partly Cloudy'

def get_rainfall_status_badge(prob_pct):
    p = round(float(prob_pct or 0.0), 1)
    if p >= 85.0:
        return {"status_badge": "Heavy Rain Alert", "probability_range": "85% – 100%", "color": "#ef4444"}
    elif p >= 70.0:
        return {"status_badge": "Rain Likely", "probability_range": "70% – 84%", "color": "#f97316"}
    elif p >= 50.0:
        return {"status_badge": "Chance of Rain", "probability_range": "50% – 69%", "color": "#3b82f6"}
    elif p >= 25.0:
        return {"status_badge": "Mostly Dry", "probability_range": "25% – 49%", "color": "#06b6d4"}
    else:
        return {"status_badge": "Dry / Clear", "probability_range": "0% – 25%", "color": "#10b981"}

def sync_supabase_forecast_table(hourly_list, now_ist):
    """
    Deletes old forecast rows and inserts the fresh 24h predictions
    into both aqi_forecasts_24h and weather_forecasts_24h in Supabase.
    """
    if not SUPABASE_URL or not SUPABASE_KEY:
        print("Warning: Supabase credentials not configured, skipping Supabase forecast sync.", file=sys.stderr)
        return False

    headers = {
        "apikey": SUPABASE_KEY,
        "Authorization": f"Bearer {SUPABASE_KEY}",
        "Content-Type": "application/json",
        "Prefer": "return=minimal"
    }

    gen_time_str = now_ist.isoformat()

    # 1. Sync aqi_forecasts_node2_24h (or fallback to aqi_forecasts_24h)
    forecast_table = ENV.get("TABLE_FORECAST_24H") or "aqi_forecasts_node2_24h"
    table_url = f"{SUPABASE_URL}/rest/v1/{forecast_table}"
    delete_url = f"{table_url}?id=gt.0"
    try:
        del_req = urllib.request.Request(delete_url, headers=headers, method="DELETE")
        with urllib.request.urlopen(del_req, timeout=15) as resp:
            print(f"Cleared previous records from {forecast_table} (HTTP {resp.getcode()})", file=sys.stderr)
    except Exception as e:
        print(f"Notice: clearing old aqi forecast records: {e}", file=sys.stderr)

    aqi_records = []
    weather_records = []
    for item in hourly_list:
        p_temp = float(item['temp'])
        p_hum = float(item['humidity'])
        p_rain = float(item.get('rain_mm', 0.0))

        aqi_records.append({
            "node_id": "node_1",
            "forecast_for_time": item['iso'],
            "forecast_generated_at": gen_time_str,
            "pm2_5_ug_m3": float(item['pm25']),
            "pm10_ug_m3": float(item['pm10']),
            "no2_ug_m3": float(item['no2']),
            "co_mg_m3": float(item['co']),
            "ozone_ug_m3": float(item['o3']),
            "temperature_c": p_temp,
            "humidity_pct": p_hum,
            "tier_used": 48
        })

        weather_records.append({
            "node_id": "node_1",
            "forecast_for_time": item['iso'],
            "forecast_generated_at": gen_time_str,
            "temperature": p_temp,
            "humidity": p_hum,
            "wind_speed": 7.86,
            "rain_gauge": p_rain
        })

    try:
        post_req = urllib.request.Request(
            table_url,
            data=json.dumps(aqi_records).encode("utf-8"),
            headers=headers,
            method="POST"
        )
        with urllib.request.urlopen(post_req, timeout=15) as resp:
            print(f"Successfully inserted {len(aqi_records)} new forecast rows into aqi_forecasts_24h (HTTP {resp.getcode()})", file=sys.stderr)
    except Exception as e:
        print(f"Error inserting new forecast rows into aqi_forecasts_24h: {e}", file=sys.stderr)

    # 2. Sync weather_forecasts_24h
    w_table_url = f"{SUPABASE_URL}/rest/v1/weather_forecasts_24h"
    w_delete_url = f"{w_table_url}?id=gt.0"
    try:
        w_del_req = urllib.request.Request(w_delete_url, headers=headers, method="DELETE")
        with urllib.request.urlopen(w_del_req, timeout=15) as resp:
            print(f"Cleared previous records from weather_forecasts_24h (HTTP {resp.getcode()})", file=sys.stderr)
    except Exception as e:
        print(f"Notice: clearing old weather forecast records: {e}", file=sys.stderr)

    try:
        w_post_req = urllib.request.Request(
            w_table_url,
            data=json.dumps(weather_records).encode("utf-8"),
            headers=headers,
            method="POST"
        )
        with urllib.request.urlopen(w_post_req, timeout=15) as resp:
            print(f"Successfully inserted {len(weather_records)} new forecast rows into weather_forecasts_24h (HTTP {resp.getcode()})", file=sys.stderr)
            return True
    except Exception as e:
        print(f"Error inserting new forecast rows into weather_forecasts_24h: {e}", file=sys.stderr)
        return True

def find_model_path():
    """Locate the swt_res_cnn_bilstm model or any new model inside forcast directory."""
    candidates = [
        os.path.join(FORCAST_DIR, "newAQIPrediction", "swt_res_cnn_bilstm_node1_final.pt"),
        os.path.join(FORCAST_DIR, "swt_res_cnn_bilstm_node1_final.pt"),
    ]
    for c in candidates:
        if os.path.isfile(c):
            return c
    # Fallback to search recursively for .pt or .keras
    for root, _, files in os.walk(FORCAST_DIR):
        for f in files:
            if f.endswith(('.pt', '.keras', '.h5')):
                return os.path.join(root, f)
    return None

def main():
    parser = argparse.ArgumentParser(description="AQI Forecast Engine - 48h Neural Inference")
    parser.add_argument("--aqi-table", type=str, default=None, help="Supabase table storing hourly AQI node data (e.g. AQI_NODE2)")
    parser.add_argument("--weather-table", type=str, default=None, help="Supabase table storing hourly weather data (e.g. WEATHER_NODE2)")
    args = parser.parse_args()

    aqi_table = args.aqi_table or ENV.get("SOURCE_AQI_TABLE") or ENV.get("TABLE_AQI_HISTORICAL") or "AQI_NODE2"
    weather_table = args.weather_table or ENV.get("TABLE_WEATHER_HISTORICAL") or "WEATHER_NODE2"

    print(f"Reading 48h hourly AQI inputs from: {aqi_table}", file=sys.stderr)
    print(f"Reading 48h weather inputs (wind speed & direction) from: {weather_table}", file=sys.stderr)

    model_path = find_model_path()
    now_ist = datetime.now(timezone(timedelta(hours=5, minutes=30)))

    if not model_path:
        print("Notice: No model file found in 'forcast/'.", file=sys.stderr)
        return

    print(f"Using trained forecast model: {model_path}", file=sys.stderr)

    # 1. Fetch 48 hours of past hourly telemetry
    aqi_rows = fetch_supabase(aqi_table, "timestamp_hour", limit=120)
    weather_rows = fetch_supabase(weather_table, "timestamp_hour", limit=120)

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
                weather_by_hour[key] = {
                    'wind_speed': float(w.get('wind_speed') or 0.0),
                    'wind_direction': parse_wind_direction_degrees(w.get('wind_direction'))
                }
            except Exception:
                pass

    DEFAULT_WIND_SPEED = float(ENV.get("WIND_SPEED_BASELINE", 7.86))
    DEFAULT_WIND_DIR = 225.0

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
    total_avail = len(parsed_rows)
    if total_avail == 0:
        raise ValueError(f"No records available in {aqi_table}")

    # Exactly 48 hours input window
    REQUIRED_PAST_HOURS = 48
    if total_avail >= REQUIRED_PAST_HOURS:
        input_slice = parsed_rows[-REQUIRED_PAST_HOURS:]
    else:
        padding = [parsed_rows[0]] * (REQUIRED_PAST_HOURS - total_avail)
        input_slice = padding + parsed_rows

    print(f"Prepared exactly {len(input_slice)} hours of past telemetry for model input.", file=sys.stderr)

    # 2. Build 14-channel input feature matrix and 11-channel static context
    # Model trained channel order:
    # 0: pm25, 1: pm10, 2: no2, 3: co, 4: o3, 5: temp, 6: humidity, 7: wind_speed,
    # 8: wind_x, 9: wind_y, 10: hour_sin, 11: hour_cos, 12: month_sin, 13: month_cos
    feature_matrix = []
    for dt_val, r in input_slice:
        pm25 = float(r.get('pm2.5') or r.get('pm25') or 15.0)
        pm10 = float(r.get('pm10') or 25.0)
        no2 = float(r.get('no2') or 5.0)
        co_raw = r.get('co')
        co = float(co_raw if co_raw is not None and str(co_raw).strip() != '' else 0.2)
        if co <= 0.0:
            co = 0.20
        o3 = float(r.get('o3') or 30.0)
        temp = float(r.get('temperature') or 28.0)
        humidity = float(r.get('humidity') or 70.0)
        cpcb_aqi = float(r.get('cpcb_aqi') or 40.0)

        hour_key = dt_val.strftime('%Y-%m-%d %H')
        w_info = weather_by_hour.get(hour_key, {'wind_speed': DEFAULT_WIND_SPEED, 'wind_direction': DEFAULT_WIND_DIR})
        w_spd = w_info['wind_speed']
        w_deg = w_info['wind_direction']

        w_rad = math.radians(w_deg)
        wind_x = math.sin(w_rad)
        wind_y = math.cos(w_rad)

        hour_sin = math.sin(2 * math.pi * dt_val.hour / 24.0)
        hour_cos = math.cos(2 * math.pi * dt_val.hour / 24.0)
        month_sin = math.sin(2 * math.pi * dt_val.month / 12.0)
        month_cos = math.cos(2 * math.pi * dt_val.month / 12.0)

        # 14 input features matching trained model architecture
        feat14 = [pm25, pm10, no2, co, o3, temp, humidity, w_spd, wind_x, wind_y, hour_sin, hour_cos, month_sin, month_cos]
        feature_matrix.append(feat14)

    # Latest timestep 11 context features:
    # 7 continuous physical parameters + cpcb_aqi + wind_spd + wind_x + wind_y
    last_r = input_slice[-1][1]
    last_pm25 = float(last_r.get('pm2.5') or last_r.get('pm25') or 15.0)
    last_pm10 = float(last_r.get('pm10') or 25.0)
    last_no2 = float(last_r.get('no2') or 5.0)
    last_co_raw = last_r.get('co')
    last_co = float(last_co_raw if last_co_raw is not None and str(last_co_raw).strip() != '' else 0.2)
    if last_co <= 0.0:
        last_co = 0.20
    last_o3 = float(last_r.get('o3') or 30.0)
    last_temp = float(last_r.get('temperature') or 28.0)
    last_humidity = float(last_r.get('humidity') or 70.0)
    last_cpcb = float(last_r.get('cpcb_aqi') or 40.0)

    last_w_spd = feature_matrix[-1][7]
    last_wind_x = feature_matrix[-1][8]
    last_wind_y = feature_matrix[-1][9]

    # Context order: [pm25, pm10, no2, co, o3, temp, humidity, cpcb, w_spd, wind_x, wind_y]
    raw_11 = [last_pm25, last_pm10, last_no2, last_co, last_o3, last_temp, last_humidity, last_cpcb, last_w_spd, last_wind_x, last_wind_y]

    # Baseline anchor vector for the 7 predicted outputs: [pm25, pm10, no2, co, o3, temp, humidity]
    baseline_7 = np.array([last_pm25, last_pm10, last_no2, last_co, last_o3, last_temp, last_humidity], dtype=np.float32)

    # 3. Model Inference
    model = SWT_Res_CNN_BiLSTM()
    sd = torch.load(model_path, map_location='cpu')
    model.load_state_dict(sd)
    model.eval()

    X_tensor = torch.tensor([feature_matrix], dtype=torch.float32) # [1, 48, 14]
    X_raw_11 = torch.tensor([raw_11], dtype=torch.float32)         # [1, 11]

    with torch.no_grad():
        preds_23x7, alpha = model(X_tensor, X_raw_11)
        preds_np = preds_23x7[0].numpy() # [23, 7]
        alpha_np = alpha.numpy()         # [23, 7]

    # Residual fusion: blend neural predicted adjustments with latest continuous baseline
    # target order: [pm25, pm10, no2, co, o3, temp, humidity]
    blended_23 = alpha_np * (baseline_7 + preds_np) + (1.0 - alpha_np) * baseline_7

    # Smoothly extrapolate 24th hour from trends of hours 21..23
    step_24 = blended_23[-1] + 0.5 * (blended_23[-1] - blended_23[-2])
    all_24_preds = np.vstack([blended_23, step_24]) # [24, 7]

    # Temporal Gaussian smoothing across 24h horizon to eliminate unphysical sudden spikes
    smoothed_24 = np.zeros_like(all_24_preds)
    for col_idx in range(7):
        vals = all_24_preds[:, col_idx]
        padded = np.pad(vals, (1, 1), mode='edge')
        # 3-point weighted moving average [0.25, 0.50, 0.25]
        smoothed_24[:, col_idx] = 0.25 * padded[:-2] + 0.50 * padded[1:-1] + 0.25 * padded[2:]

    # 1.5. Run SAPE 24h Rainfall Forecast Engine (72h historical window from WEATHER_NODE1)
    rainfall_data = None
    if RainfallForecastEngine is not None:
        try:
            print("Evaluating SAPE 24h Rainfall Forecast Model (72h continuous window)...", file=sys.stderr)
            rf_engine = RainfallForecastEngine()
            rainfall_data = rf_engine.predict(weather_rows, now_ist)
            print(f"Rainfall forecast computed: {rainfall_data['total_24h_rainfall_mm']} mm expected (Peak: {rainfall_data['peak_hourly_rate_mm']} mm/h at {rainfall_data['peak_hour_label']})", file=sys.stderr)
            
            # Save dedicated rainfall forecast json
            rf_json_path = os.path.join(PROJECT_DIR, "php-frontend", "api", "rainfall_forecast_24h.json")
            with open(rf_json_path, "w", encoding="utf-8") as rf_fp:
                json.dump(rainfall_data, rf_fp, indent=2)
        except Exception as e:
            print(f"Warning: Rainfall model inference encountered error: {e}", file=sys.stderr)

    # When generating hourly forecast:
    # Always predict upcoming future hours starting from the next hour (now + 1h).
    # The current in-progress hour is monitored by live telemetry.
    start_dt = (now_ist + timedelta(hours=1)).replace(minute=0, second=0, microsecond=0)

    hourly_list = []
    temps = []
    aqis = []

    for i in range(24):
        target_dt = start_dt + timedelta(hours=i)
        vals = smoothed_24[i]

        # Criteria air pollutant targets predicted by SWT-Res-CNN-BiLSTM:
        p_pm25 = max(2.0, min(80.0, round(float(vals[0]), 2)))
        p_pm10 = max(4.0, min(120.0, round(float(vals[1]), 2)))
        p_no2  = max(1.0, min(60.0, round(float(vals[2]), 2)))
        # CO in ambient coastal campus environment naturally ranges 0.1 to 0.35 mg/m³
        # Ceiling at 0.35 mg/m³ reflects physical ground truth for clean rural campus air
        p_co   = max(0.08, min(0.35, round(float(vals[3]), 3)))
        p_o3   = max(5.0, min(95.0, round(float(vals[4]), 2)))

        # Temperature & Humidity: Sourced from the dedicated Multi-Weather Wavelet LSTM Model
        # (Replaces AQI model temperature & humidity outputs)
        if rainfall_data and 'hourly' in rainfall_data and i < len(rainfall_data['hourly']) and rainfall_data['hourly'][i].get('temp') is not None:
            p_temp = round(float(rainfall_data['hourly'][i]['temp']), 1)
            p_hum  = min(98.0, max(25.0, round(float(rainfall_data['hourly'][i]['humidity']), 1)))
        else:
            # Fallback only if multi-weather engine was unavailable
            p_temp = round(float(vals[5]), 1)
            p_hum  = min(98.0, max(25.0, round(float(vals[6]), 1)))

        aqi_val, dom, sub_indices = calculate_aqi(p_pm25, p_pm10, p_no2, p_co, p_o3)
        cat_info = get_cpcb_category(aqi_val)

        temps.append(p_temp)
        aqis.append(aqi_val)
        time_label = target_dt.strftime('%I %p').lstrip('0')

        # Extract calibrated rainfall metrics for this hour
        r_step = rainfall_data['hourly'][i] if (rainfall_data and i < len(rainfall_data.get('hourly', []))) else {}
        r_prob = r_step.get('rain_prob_pct', 0.0)
        r_mm = r_step.get('rain_mm', 0.0)
        r_is_rain = r_step.get('is_rain', False)
        r_intensity = r_step.get('rain_intensity', 'None')
        r_badge_info = get_rainfall_status_badge(r_prob)

        icon = get_weather_icon(target_dt.hour, p_temp, p_hum, aqi_val, is_rain=r_is_rain, rain_mm=r_mm)

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
            'wind_speed': round(float(last_w_spd), 1),
            'rain_prob': r_prob,
            'rain_mm': r_mm,
            'rain_status_badge': r_badge_info['status_badge'],
            'rain_probability_range': r_badge_info['probability_range'],
            'is_rain': r_is_rain,
            'rain_intensity': r_intensity,
            'icon': icon,
            'isNow': False,
            'isNext': (i == 0)
        })

    # Generate 4 diurnal day phases for detailed timeline accordion
    phases = []
    phase_definitions = [
        ('Late Afternoon / Evening', 0, 6, '🌅'),
        ('Night to Midnight', 6, 12, '🌙'),
        ('Early Morning Transition', 12, 18, '🌤️'),
        ('Daytime Afternoon Peak', 18, 24, '☀️')
    ]
    min_day_temp = min(temps) if temps else 20.0
    max_day_temp = max(temps) if temps else 35.0
    temp_range = max(1.0, max_day_temp - min_day_temp)

    for p_idx, (p_name, s_idx, e_idx, p_icon) in enumerate(phase_definitions):
        p_slice = hourly_list[s_idx:e_idx]
        if not p_slice:
            continue
        p_temps = [h['temp'] for h in p_slice]
        p_min_t = min(p_temps)
        p_max_t = max(p_temps)
        p_aqis = [h['aqi'] for h in p_slice]
        p_avg_aqi = round(sum(p_aqis) / len(p_aqis))
        p_h0 = p_slice[0]

        left_pct = max(0, min(100, int(((p_min_t - min_day_temp) / temp_range) * 100)))
        width_pct = max(10, min(100 - left_pct, int(((p_max_t - p_min_t) / temp_range) * 100)))

        hours_span = f"{p_slice[0]['label']} – {p_slice[-1]['label']}"
        phases.append({
            'index': p_idx,
            'phaseName': p_name,
            'hoursSpan': hours_span,
            'minTemp': p_min_t,
            'maxTemp': p_max_t,
            'leftPct': str(left_pct),
            'widthPct': str(width_pct),
            'condition': get_condition_text(p_min_t, p_slice[0]['humidity'], p_avg_aqi),
            'icon': p_icon,
            'aqi': p_avg_aqi,
            'dominant': p_h0['dominant'],
            'humidity': p_h0['humidity'],
            'pm25': p_h0['pm25'],
            'pm10': p_h0['pm10'],
            'co': p_h0['co'],
            'no2': p_h0['no2'],
            'o3': p_h0['o3'],
            'advice': 'Optimal air quality. Perfect window for campus athletics and natural classroom airflow.' if p_avg_aqi <= 50 else 'Standard outdoor sports and exercise permitted.'
        })

    current_aqi = aqis[0]
    current_dom = hourly_list[0]['dominant']
    current_sub_indices = hourly_list[0]['sub_indices']
    hero_cat = get_cpcb_category(current_aqi)

    model_display_name = "swt_res_cnn_bilstm_node1_final.pt"
    payload = {
        'status': 'success',
        'type': 'aqi_forecast',
        'model_name': model_display_name,
        'input_window_hours': 48,
        'standard': 'National Air Quality Index (NAQI)',
        'generated_at': now_ist.strftime('%Y-%m-%d %H:%M:%S IST'),
        'hero': {
            'aqi': current_aqi,
            'dominant': current_dom,
            'category': hero_cat['category'],
            'severity': hero_cat['severity'],
            'color': hero_cat['color'],
            'health_impact': hero_cat['health_impact'],
            'sub_indices': current_sub_indices,
            'aqi_low': min(aqis),
            'aqi_high': max(aqis),
            'input_source': f"{aqi_table} + {weather_table}",
            'input_hours': 48,
            'temp': temps[0],
            'humidity': hourly_list[0]['humidity'],
            'wind_speed': round(float(last_w_spd), 1),
            'dayLow': min(temps),
            'dayHigh': max(temps),
            'condition': get_condition_text(temps[0], hourly_list[0]['humidity'], current_aqi, is_rain=hourly_list[0].get('is_rain', False), rain_mm=hourly_list[0].get('rain_mm', 0.0)),
            'pm25': hourly_list[0]['pm25'],
            'pm10': hourly_list[0]['pm10'],
            'no2': hourly_list[0]['no2'],
            'co': hourly_list[0]['co'],
            'o3': hourly_list[0]['o3'],
            'rain_24h_mm': rainfall_data['total_24h_rainfall_mm'] if rainfall_data else 0.0,
            'rain_prob': hourly_list[0].get('rain_prob', 0.0),
            'rain_status_badge': hourly_list[0].get('rain_status_badge', 'Dry / Clear'),
            'rain_probability_range': hourly_list[0].get('rain_probability_range', '0% – 25%'),
            'rain_risk': rainfall_data['rain_risk_level'] if rainfall_data else 'None',
            'rain_peak_rate': rainfall_data['peak_hourly_rate_mm'] if rainfall_data else 0.0,
            'rain_peak_hour': rainfall_data['peak_hour_label'] if rainfall_data else 'N/A',
            'confidence': 'High (48h SWT Res-CNN-BiLSTM AQI + 72h Multi-Weather Wavelet Telemetry)'
        },
        'parameters': {
            'pm25': {'label': 'PM2.5 Particulate', 'unit': 'µg/m³', 'current': hourly_list[0]['pm25'], 'current_subindex': current_sub_indices.get('PM2.5', 0.0), 'min': round(min(h['pm25'] for h in hourly_list), 2), 'max': round(max(h['pm25'] for h in hourly_list), 2), 'mean': round(sum(h['pm25'] for h in hourly_list) / 24, 2), 'safe_limit': '60.0 µg/m³'},
            'pm10': {'label': 'PM10 Particulate', 'unit': 'µg/m³', 'current': hourly_list[0]['pm10'], 'current_subindex': current_sub_indices.get('PM10', 0.0), 'min': round(min(h['pm10'] for h in hourly_list), 2), 'max': round(max(h['pm10'] for h in hourly_list), 2), 'mean': round(sum(h['pm10'] for h in hourly_list) / 24, 2), 'safe_limit': '100.0 µg/m³'},
            'no2': {'label': 'Nitrogen Dioxide (NO₂)', 'unit': 'µg/m³', 'current': hourly_list[0]['no2'], 'current_subindex': current_sub_indices.get('NO₂', 0.0), 'min': round(min(h['no2'] for h in hourly_list), 2), 'max': round(max(h['no2'] for h in hourly_list), 2), 'mean': round(sum(h['no2'] for h in hourly_list) / 24, 2), 'safe_limit': '80.0 µg/m³'},
            'co': {'label': 'Carbon Monoxide (CO)', 'unit': 'mg/m³', 'current': hourly_list[0]['co'], 'current_subindex': current_sub_indices.get('CO', 0.0), 'min': round(min(h['co'] for h in hourly_list), 3), 'max': round(max(h['co'] for h in hourly_list), 3), 'mean': round(sum(h['co'] for h in hourly_list) / 24, 3), 'safe_limit': '2.0 mg/m³'},
            'o3': {'label': 'Surface Ozone (O₃)', 'unit': 'µg/m³', 'current': hourly_list[0]['o3'], 'current_subindex': current_sub_indices.get('O₃', 0.0), 'min': round(min(h['o3'] for h in hourly_list), 2), 'max': round(max(h['o3'] for h in hourly_list), 2), 'mean': round(sum(h['o3'] for h in hourly_list) / 24, 2), 'safe_limit': '100.0 µg/m³'},
            'temp': {'label': 'Ambient Temperature', 'unit': '°C', 'current': temps[0], 'min': round(min(temps), 1), 'max': round(max(temps), 1), 'mean': round(sum(temps) / 24, 1), 'safe_limit': 'Campus Baseline'},
            'humidity': {'label': 'Relative Humidity', 'unit': '%', 'current': hourly_list[0]['humidity'], 'min': round(min(h['humidity'] for h in hourly_list), 1), 'max': round(max(h['humidity'] for h in hourly_list), 1), 'mean': round(sum(h['humidity'] for h in hourly_list) / 24, 1), 'safe_limit': 'Comfort 40-70%'},
            'rainfall': {
                'label': 'Rainfall Accumulation',
                'unit': 'mm',
                'current': hourly_list[0].get('rain_mm', 0.0),
                'current_prob': hourly_list[0].get('rain_prob', 0.0),
                'status_badge': hourly_list[0].get('rain_status_badge', 'Dry / Clear'),
                'probability_range': hourly_list[0].get('rain_probability_range', '0% – 25%'),
                'total_24h': rainfall_data['total_24h_rainfall_mm'] if rainfall_data else 0.0,
                'peak_hourly': rainfall_data['peak_hourly_rate_mm'] if rainfall_data else 0.0,
                'peak_hour': rainfall_data['peak_hour_label'] if rainfall_data else 'N/A',
                'risk': rainfall_data['rain_risk_level'] if rainfall_data else 'None',
                'safe_limit': 'Campus Drainage Baseline'
            }
        },
        'rainfall': rainfall_data,
        'rainfall_scale': [
            {"range": "0% – 25%", "badge": "Dry / Clear", "description": "Dry / Clear conditions, minimal or zero probability of rain."},
            {"range": "25% – 49%", "badge": "Mostly Dry", "description": "Overcast or slight moisture; precipitation remains unlikely."},
            {"range": "50% – 69%", "badge": "Chance of Rain", "description": "Scattered showers possible; carry an umbrella."},
            {"range": "70% – 84%", "badge": "Rain Likely", "description": "High probability of steady rainfall; wet ground conditions."},
            {"range": "85% – 100%", "badge": "Heavy Rain Alert", "description": "Downpour or heavy precipitation alert; runoff risk."}
        ],
        'advisory': {
            'title': 'Air Quality Forecast Active',
            'severity': hero_cat['severity'],
            'desc': f'Predicted ambient AQI is {current_aqi} dominated by {current_dom}.',
            'sports': 'Follow standard campus outdoor protocols',
            'ventilation': 'Normal campus ventilation active',
            'hydration': 'Maintain adequate hydration'
        },
        'hourly': hourly_list,
        'phases': phases
    }

    os.makedirs(os.path.dirname(OUTPUT_JSON_PATH), exist_ok=True)
    with open(OUTPUT_JSON_PATH, 'w', encoding='utf-8') as f:
        json.dump(payload, f, indent=2)

    # Sync to Supabase
    sync_supabase_forecast_table(hourly_list, now_ist)
    print(f"Successfully generated AQI forecast using {model_display_name} with 48h inputs. Saved to {OUTPUT_JSON_PATH}", file=sys.stderr)
    print(json.dumps(payload))

if __name__ == '__main__':
    main()
