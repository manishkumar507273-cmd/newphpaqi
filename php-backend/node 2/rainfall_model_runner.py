import os
import sys
import math
import json
import urllib.request
import numpy as np
from datetime import datetime, timezone, timedelta

# Enforce PyTorch backend for Keras 3
os.environ["KERAS_BACKEND"] = "torch"
import keras
import torch
import joblib

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
if os.path.basename(os.path.abspath(os.path.join(BASE_DIR, ".."))).lower() == "php-backend":
    PROJECT_DIR = os.path.abspath(os.path.join(BASE_DIR, "..", ".."))
else:
    PROJECT_DIR = os.path.abspath(os.path.join(BASE_DIR, ".."))
MODEL_DIR = os.path.join(PROJECT_DIR, "forcast", "rainfall_model")

# Compass direction mapping to meteorological degrees
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

def parse_wind_direction(val):
    if val is None:
        return 225.0
    val_str = str(val).strip().lower()
    if val_str in COMPASS_MAP:
        return COMPASS_MAP[val_str]
    try:
        return float(val_str)
    except Exception:
        return 225.0

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

class RainfallForecastEngine:
    def __init__(self, model_dir=MODEL_DIR):
        self.model_dir = model_dir
        
        # Candidate model files (prefer new multi-weather model)
        model_candidates = [
            os.path.join(model_dir, "best_smvitm_multi_weather_24h.keras"),
            os.path.join(model_dir, "best_smvitm_wavelet_lstm_24h.keras"),
        ]
        self.model_path = next((p for p in model_candidates if os.path.isfile(p)), None)
        if not self.model_path and os.path.isdir(model_dir):
            for f in os.listdir(model_dir):
                if f.endswith(".keras"):
                    self.model_path = os.path.join(model_dir, f)
                    break

        # Candidate scalers files (prefer new multi-weather scalers)
        scalers_candidates = [
            os.path.join(model_dir, "wavelet_scalers_multi.joblib"),
            os.path.join(model_dir, "wavelet_scalers.joblib"),
        ]
        self.scalers_path = next((p for p in scalers_candidates if os.path.isfile(p)), None)
        if not self.scalers_path and os.path.isdir(model_dir):
            for f in os.listdir(model_dir):
                if "scaler" in f.lower() and f.endswith(".joblib"):
                    self.scalers_path = os.path.join(model_dir, f)
                    break

        # Candidate calibrators files
        calib_candidates = [
            os.path.join(model_dir, "horizon_calibrators_24h_v2.joblib"),
            os.path.join(model_dir, "horizon_calibrators_24h.joblib"),
        ]
        self.calibrators_path = next((p for p in calib_candidates if os.path.isfile(p)), None)
        if not self.calibrators_path and os.path.isdir(model_dir):
            for f in os.listdir(model_dir):
                if "calibrator" in f.lower() and f.endswith(".joblib"):
                    self.calibrators_path = os.path.join(model_dir, f)
                    break
        
        self.model = None
        self.scaler_slow = None
        self.scaler_fast = None
        self.scaler_temp_target = None
        self.scaler_hum_target = None
        self.optimal_taus = None
        self.optimal_betas = None
        self.optimal_gammas = None
        self._load_artifacts()

    def _load_artifacts(self):
        if not self.model_path or not os.path.isfile(self.model_path):
            raise FileNotFoundError(f"Rainfall model file not found in {self.model_dir}")
        if not self.scalers_path or not os.path.isfile(self.scalers_path):
            raise FileNotFoundError(f"Wavelet scalers file not found in {self.scalers_dir if hasattr(self, 'scalers_dir') else self.model_dir}")
        if not self.calibrators_path or not os.path.isfile(self.calibrators_path):
            raise FileNotFoundError(f"Calibrators file not found in {self.model_dir}")

        print(f"Loading SAPE Multi-Weather Model from: {self.model_path}", file=sys.stderr)
        self.model = keras.models.load_model(self.model_path, compile=False)
        
        scalers = joblib.load(self.scalers_path)
        self.scaler_slow = scalers.get("scaler_slow")
        self.scaler_fast = scalers.get("scaler_fast")
        self.scaler_temp_target = scalers.get("scaler_temp_target")
        self.scaler_hum_target = scalers.get("scaler_hum_target")

        calibrators = joblib.load(self.calibrators_path)
        self.optimal_taus = calibrators["optimal_taus"]
        self.optimal_betas = calibrators["optimal_betas"]
        self.optimal_gammas = calibrators["optimal_gammas"]
        print("Multi-weather model, scalers, and horizon calibrators successfully loaded.", file=sys.stderr)

    @staticmethod
    def compute_modwt_details(x, levels=3):
        """
        Computes 3-level Maximal Overlap Discrete Wavelet Transform (MODWT)
        detail coefficients using dyadic difference filters.
        x: [T, num_channels] -> returns [T, num_channels * levels]
        """
        N, C = x.shape
        details = []
        current = x.copy()
        for j in range(1, levels + 1):
            step = 2 ** (j - 1)
            diff = np.zeros_like(current)
            for t in range(N):
                past = current[max(0, t - step)]
                diff[t] = 0.5 * (current[t] - past)
            details.append(diff)
            
            approx = np.zeros_like(current)
            for t in range(N):
                past = current[max(0, t - step)]
                approx[t] = 0.5 * (current[t] + past)
            current = approx
        return np.hstack(details)

    def prepare_72h_window(self, weather_rows):
        """
        Sorts, cleans, and extracts continuous 72 hours of past telemetry from WEATHER_NODE1.
        Requires continuous historical window from t - 71 to current hour t.
        """
        parsed = []
        for r in weather_rows:
            ts = r.get("timestamp_hour")
            if not ts:
                continue
            try:
                dt = datetime.fromisoformat(ts.replace("Z", "+00:00"))
                dt_ist = dt.astimezone(timezone(timedelta(hours=5, minutes=30)))
                parsed.append((dt_ist, r))
            except Exception:
                pass

        if not parsed:
            raise ValueError("No valid timestamped rows found in WEATHER_NODE1 telemetry.")

        parsed.sort(key=lambda x: x[0])

        REQUIRED_STEPS = 72
        if len(parsed) >= REQUIRED_STEPS:
            window = parsed[-REQUIRED_STEPS:]
        else:
            # Pad earliest available observation if less than 72 hours
            padding = [parsed[0]] * (REQUIRED_STEPS - len(parsed))
            window = padding + parsed

        return window

    def predict(self, weather_rows, reference_time_ist=None):
        """
        Runs complete 24-hour rainfall forecasting pipeline:
        1. Prepares 72-hour continuous historical window (t-71 to t)
        2. Derives 15 synoptic features and 27 MODWT detail coefficients
        3. Scales inputs and passes through SMVITM MODWT Attention LSTM
        4. Applies horizon calibrators (tau_h, beta_h, gamma_h)
        5. Returns structured 24-hour predictions and summary metrics
        """
        window = self.prepare_72h_window(weather_rows)
        latest_dt = reference_time_ist or window[-1][0]

        slow_feats = []
        base_vars = []

        for dt, r in window:
            T = float(r.get("temperature") or 26.5)
            RH = float(r.get("humidity") or 82.0)
            W = float(r.get("wind_speed") or 5.0)
            G = float(r.get("wind_gust") or 10.0)
            R = float(r.get("rain_gauge") or 0.0)
            deg = parse_wind_direction(r.get("wind_direction"))

            rad = math.radians(deg)
            u = -W * math.sin(rad)
            v = -W * math.cos(rad)

            # Magnus formula for Dew Point (°C)
            a, b = 17.27, 237.7
            alpha = ((a * T) / (b + T)) + math.log(max(RH, 1.0) / 100.0)
            Td = (b * alpha) / (a - alpha)

            # Vapor pressure (hPa)
            es = 6.112 * math.exp((17.67 * T) / (T + 243.5))
            ea = es * (RH / 100.0)
            vpd = max(0.0, es - ea)

            # Heat index (Rothfusz equation)
            hi = (-42.379 + 2.04901523*T + 10.14333127*RH - 0.22475541*T*RH 
                  - 6.83783e-3*(T**2) - 5.481717e-2*(RH**2) + 1.22874e-3*(T**2)*RH 
                  + 8.5282e-4*T*(RH**2) - 1.99e-6*(T**2)*(RH**2))

            h = dt.hour
            doy = dt.timetuple().tm_yday
            sin_h = math.sin(2 * math.pi * h / 24.0)
            cos_h = math.cos(2 * math.pi * h / 24.0)
            sin_doy = math.sin(2 * math.pi * doy / 365.25)
            cos_doy = math.cos(2 * math.pi * doy / 365.25)

            # 15 Slow synoptic features
            row_15 = [T, RH, W, Td, G, u, v, vpd, R, ea, hi, sin_h, cos_h, sin_doy, cos_doy]
            slow_feats.append(row_15)

            # 9 Base meteorological channels for Wavelet multiscale decomposition
            base_vars.append([T, RH, W, Td, G, u, v, vpd, R])

        slow_arr = np.array(slow_feats, dtype=np.float32)       # [72, 15]
        slow_scaled = self.scaler_slow.transform(slow_arr)       # [72, 15]

        base_arr = np.array(base_vars, dtype=np.float32)         # [72, 9]
        fast_arr = self.compute_modwt_details(base_arr, levels=3)# [72, 27]
        fast_scaled = self.scaler_fast.transform(fast_arr)       # [72, 27]

        # Build Future Time Queries [24, 6]
        # [sin_hour, cos_hour, sin_dow, cos_dow, sin_doy, cos_doy]
        future_queries = []
        for h_step in range(1, 25):
            f_dt = latest_dt + timedelta(hours=h_step)
            f_h = f_dt.hour
            f_dow = f_dt.weekday()
            f_doy = f_dt.timetuple().tm_yday

            sin_fh = math.sin(2 * math.pi * f_h / 24.0)
            cos_fh = math.cos(2 * math.pi * f_h / 24.0)
            sin_dow = math.sin(2 * math.pi * f_dow / 7.0)
            cos_dow = math.cos(2 * math.pi * f_dow / 7.0)
            sin_fdoy = math.sin(2 * math.pi * f_doy / 365.25)
            cos_fdoy = math.cos(2 * math.pi * f_doy / 365.25)

            future_queries.append([sin_fh, cos_fh, sin_dow, cos_dow, sin_fdoy, cos_fdoy])

        future_arr = np.array(future_queries, dtype=np.float32)  # [24, 6]

        # Model Inference
        inputs_dict = {
            "fast_wavelet_input": torch.from_numpy(np.array([fast_scaled], dtype=np.float32)),
            "slow_synoptic_input": torch.from_numpy(np.array([slow_scaled], dtype=np.float32)),
            "future_time_queries": torch.from_numpy(np.array([future_arr], dtype=np.float32))
        }

        preds = self.model(inputs_dict)
        raw_log_mm = preds["rain_log_mm_24h"][0].detach().cpu().numpy()
        raw_prob = preds["rain_prob_24h"][0].detach().cpu().numpy()

        # Multi-Weather Model Temperature & Humidity Inference
        pred_temps = None
        pred_hums = None
        if "temp_24h" in preds and self.scaler_temp_target is not None:
            raw_temp = preds["temp_24h"][0].detach().cpu().numpy()
            unscaled_temp = self.scaler_temp_target.inverse_transform(raw_temp.reshape(-1, 1)).flatten()
            pred_temps = np.clip(unscaled_temp, 15.0, 48.0)

        if "humidity_24h" in preds and self.scaler_hum_target is not None:
            raw_hum = preds["humidity_24h"][0].detach().cpu().numpy()
            unscaled_hum = self.scaler_hum_target.inverse_transform(raw_hum.reshape(-1, 1)).flatten()
            pred_hums = np.clip(unscaled_hum, 20.0, 100.0)

        # Operational Ground-Truth Persistence Assimilation
        # Dynamically conditions neural predictions on recent rain station observations (avoids false-alarm 24h rain during dry spells)
        recent_18h_rain = sum(float(r.get("rain_gauge") or 0.0) for _, r in window[-18:])
        recent_18h_count = sum(1 for _, r in window[-18:] if float(r.get("rain_gauge") or 0.0) > 0.0)
        recent_6h_rain = sum(float(r.get("rain_gauge") or 0.0) for _, r in window[-6:])

        # Ground-Truth Assimilation for Ambient Temperature & Humidity
        # The neural model was trained on monsoon rainfall periods where overcast clouds bound temperatures to ~24°C–29°C.
        # During dry/clear regimes, intense solar radiation naturally elevates coastal daytime temperatures to 33°C–37°C.
        # We preserve the neural network's learned diurnal cycle shape and phase while assimilating the observed
        # synoptic envelope from recent station telemetry (continuous 24h window).
        if pred_temps is not None and len(pred_temps) == 24:
            recent_24 = window[-24:]
            obs_temps = [float(r.get("temperature") or 26.5) for _, r in recent_24]
            latest_t = obs_temps[-1]
            obs_t_min = min(obs_temps)
            obs_t_max = max(obs_temps)

            t_min, t_max = np.min(pred_temps), np.max(pred_temps)
            t_span = max(0.1, t_max - t_min)
            t_norm = (pred_temps - t_min) / t_span  # [0, 1] neural diurnal profile

            if recent_18h_rain >= 5.0 and recent_18h_count >= 4:
                # Active monsoon depression: overcast clouds maintain narrow cool envelope
                target_min = t_min
                target_max = t_max
            else:
                # Dry / fair weather: align diurnal amplitude with ground truth station observations
                target_min = min(obs_t_min, t_min)
                target_max = max(obs_t_max, t_max, 32.0)  # Ensure daytime ceiling reflects sunny conditions

            scaled_temps = target_min + t_norm * (target_max - target_min)

            # Smoothly transition from latest observation at t=0 into calibrated profile
            weights = np.array([0.65, 0.40, 0.18, 0.0] + [0.0] * 20)
            calib_temps = []
            for i in range(24):
                if i < 4:
                    blended = (1.0 - weights[i]) * scaled_temps[i] + weights[i] * (latest_t + (scaled_temps[i] - scaled_temps[0]))
                    calib_temps.append(round(float(blended), 1))
                else:
                    calib_temps.append(round(float(scaled_temps[i]), 1))
            pred_temps = np.clip(np.array(calib_temps, dtype=np.float32), 15.0, 48.0)

        if pred_hums is not None and len(pred_hums) == 24:
            recent_24 = window[-24:]
            obs_hums = [float(r.get("humidity") or 75.0) for _, r in recent_24]
            latest_h = obs_hums[-1]
            obs_h_min = min(obs_hums)
            obs_h_max = max(obs_hums)

            h_min, h_max = np.min(pred_hums), np.max(pred_hums)
            h_span = max(0.1, h_max - h_min)
            h_norm = (pred_hums - h_min) / h_span  # [0, 1] neural humidity profile

            if recent_18h_rain >= 5.0 and recent_18h_count >= 4:
                target_h_min = h_min
                target_h_max = h_max
            else:
                target_h_min = min(obs_h_min, h_min)
                target_h_max = max(obs_h_max, h_max)

            scaled_hums = target_h_min + h_norm * (target_h_max - target_h_min)
            weights_h = np.array([0.65, 0.40, 0.18, 0.0] + [0.0] * 20)
            calib_hums = []
            for i in range(24):
                if i < 4:
                    blended = (1.0 - weights_h[i]) * scaled_hums[i] + weights_h[i] * (latest_h + (scaled_hums[i] - scaled_hums[0]))
                    calib_hums.append(round(float(blended), 1))
                else:
                    calib_hums.append(round(float(scaled_hums[i]), 1))
            pred_hums = np.clip(np.array(calib_hums, dtype=np.float32), 20.0, 100.0)

        if recent_18h_rain >= 5.0 and recent_18h_count >= 4:
            # Regime A: Active continuous monsoon depression (keep 100% full model prediction)
            regime_weight = 1.0
        elif recent_18h_rain >= 1.0 or recent_18h_count >= 2:
            # Regime B: Intermittent / scattered showers
            regime_weight = 0.50 + 0.50 * min(1.0, recent_18h_rain / 5.0)
        else:
            # Regime C: Dry spell / Fair weather (<= 1 isolated event in past 18 hours)
            regime_weight = 0.15

        hourly_rainfall = []
        total_24h_mm = 0.0
        peak_rate_mm = 0.0
        peak_hour_label = ""
        rain_hours_count = 0

        for i in range(24):
            f_dt = latest_dt + timedelta(hours=i+1)
            f_hour = f_dt.hour
            p = float(raw_prob[i])
            tau = float(self.optimal_taus[i])
            beta = float(self.optimal_betas[i])
            gamma = float(self.optimal_gammas[i])

            # Convective solar heating window for coastal Karnataka (2 PM to 6 PM IST)
            is_convective_window = (14 <= f_hour <= 18)
            convective_boost = 0.25 if is_convective_window else 0.0
            pers_factor = min(1.0, regime_weight + convective_boost)

            # Condition occurrence probability on recent ground truth
            p_calibrated = p * pers_factor
            is_rain = bool(p_calibrated >= tau)

            log_val = float(raw_log_mm[i])
            base_mm = max(0.0, math.expm1(log_val) if log_val > 0 else 0.0)

            # Intensity calibration with beta, gamma, and regime persistence factor
            if is_rain:
                calib_mm = round(float(gamma * (base_mm ** beta) * pers_factor), 2)
            else:
                calib_mm = 0.0

            if is_rain:
                rain_hours_count += 1
            total_24h_mm += calib_mm

            if calib_mm > peak_rate_mm:
                peak_rate_mm = calib_mm
                peak_hour_label = f_dt.strftime('%I %p').lstrip('0')

            prob_pct = round(p_calibrated * 100, 1)
            status_meta = get_rainfall_status_badge(prob_pct)

            h_temp = round(float(pred_temps[i]), 1) if pred_temps is not None else None
            h_hum = round(float(pred_hums[i]), 1) if pred_hums is not None else None

            hourly_rainfall.append({
                "step": i + 1,
                "hour": f_dt.hour,
                "label": f_dt.strftime('%I %p').lstrip('0'),
                "iso": f_dt.isoformat(),
                "temp": h_temp,
                "humidity": h_hum,
                "rain_prob_pct": prob_pct,
                "status_badge": status_meta["status_badge"],
                "probability_range": status_meta["probability_range"],
                "threshold_tau": round(tau, 3),
                "is_rain": is_rain,
                "rain_mm": calib_mm,
                "rain_intensity": "Heavy" if calib_mm >= 7.5 else ("Moderate" if calib_mm >= 2.5 else ("Light" if calib_mm > 0 else "None"))
            })

        total_24h_mm = round(total_24h_mm, 2)
        peak_rate_mm = round(peak_rate_mm, 2)

        # Categorize 24h Rain Risk
        if total_24h_mm >= 30.0 or peak_rate_mm >= 7.5:
            risk_level = "High"
            risk_color = "#ef4444"
            risk_summary = f"Heavy Rainfall Expected ({total_24h_mm} mm total)"
        elif total_24h_mm >= 10.0 or peak_rate_mm >= 2.5:
            risk_level = "Moderate"
            risk_color = "#f59e0b"
            risk_summary = f"Moderate Showers Anticipated ({total_24h_mm} mm total)"
        elif total_24h_mm > 0.5:
            risk_level = "Low"
            risk_color = "#00bfa5"
            risk_summary = f"Light / Scattered Showers ({total_24h_mm} mm total)"
        else:
            risk_level = "None"
            risk_color = "#64748b"
            risk_summary = "Dry Conditions Expected (No Significant Rain)"

        return {
            "model_name": "SMVITM Multi-Weather Wavelet Attention LSTM (24h Engine)",
            "historical_window_hours": 72,
            "forecast_generated_at": datetime.now(timezone(timedelta(hours=5, minutes=30))).isoformat(),
            "window_start": window[0][0].isoformat(),
            "window_end": window[-1][0].isoformat(),
            "total_24h_rainfall_mm": total_24h_mm,
            "peak_hourly_rate_mm": peak_rate_mm,
            "peak_hour_label": peak_hour_label or "N/A",
            "rain_hours_count": rain_hours_count,
            "rain_risk_level": risk_level,
            "rain_risk_color": risk_color,
            "rain_risk_summary": risk_summary,
            "temperatures": [round(float(t), 1) for t in pred_temps] if pred_temps is not None else [],
            "humidities": [round(float(h), 1) for h in pred_hums] if pred_hums is not None else [],
            "rainfall_scale": [
                {"range": "0% – 25%", "badge": "Dry / Clear", "description": "Dry / Clear conditions, minimal or zero probability of rain."},
                {"range": "25% – 49%", "badge": "Mostly Dry", "description": "Overcast or slight moisture; precipitation remains unlikely."},
                {"range": "50% – 69%", "badge": "Chance of Rain", "description": "Scattered showers possible; carry an umbrella."},
                {"range": "70% – 84%", "badge": "Rain Likely", "description": "High probability of steady rainfall; wet ground conditions."},
                {"range": "85% – 100%", "badge": "Heavy Rain Alert", "description": "Downpour or heavy precipitation alert; runoff risk."}
            ],
            "hourly": hourly_rainfall
        }

if __name__ == "__main__":
    def load_env_vars():
        env_vars = {}
        for candidate in [os.path.join(PROJECT_DIR, ".env"), os.path.join(BASE_DIR, ".env"), os.path.join(PROJECT_DIR, "php-frontend", ".env")]:
            if os.path.exists(candidate):
                try:
                    with open(candidate, "r", encoding="utf-8") as f:
                        for line in f:
                            line = line.strip()
                            if line and not line.startswith("#") and "=" in line:
                                k, v = line.split("=", 1)
                                env_vars[k.strip()] = v.strip()
                except Exception:
                    pass
        return env_vars

    DEFAULT_SUPABASE_URL = "https://sgkdpliqlhgiqsabxzxe.supabase.co"
    DEFAULT_SUPABASE_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InNna2RwbGlxbGhnaXFzYWJ4enhlIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQ4NzgzMTIsImV4cCI6MjEwMDQ1NDMxMn0.vMtbXomFdmOcBkhhSoiyYyp_vFxOhg4MYCFCw9-pL30"

    _env = load_env_vars()
    SUPABASE_URL = (os.environ.get("SUPABASE_URL") or "").strip() or _env.get("VITE_SUPABASE_URL") or _env.get("SUPABASE_URL") or DEFAULT_SUPABASE_URL
    SUPABASE_KEY = (os.environ.get("SUPABASE_KEY") or "").strip() or _env.get("VITE_SUPABASE_ANON_KEY") or _env.get("SUPABASE_KEY") or DEFAULT_SUPABASE_KEY

    print("Fetching past 100 rows from WEATHER_NODE1...", file=sys.stderr)
    url = f"{SUPABASE_URL}/rest/v1/WEATHER_NODE1?order=timestamp_hour.desc&limit=100"
    req = urllib.request.Request(url, headers={"apikey": SUPABASE_KEY, "Authorization": f"Bearer {SUPABASE_KEY}"})
    with urllib.request.urlopen(req, timeout=15) as resp:
        weather_rows = json.loads(resp.read().decode())

    engine = RainfallForecastEngine()
    result = engine.predict(weather_rows)

    out_file = os.path.join(PROJECT_DIR, "php-frontend", "api", "rainfall_forecast_24h.json")
    with open(out_file, "w", encoding="utf-8") as f:
        json.dump(result, f, indent=2)

    print(f"Multi-Weather & Rainfall forecast generated successfully -> {out_file}", file=sys.stderr)
    print(f"24h Total Rain: {result['total_24h_rainfall_mm']} mm | Peak: {result['peak_hourly_rate_mm']} mm/h ({result['peak_hour_label']}) | Risk: {result['rain_risk_level']}", file=sys.stderr)
    if result.get("temperatures"):
        print(f"Multi-Weather Temp Range: {min(result['temperatures'])}°C – {max(result['temperatures'])}°C | Humidity Range: {min(result['humidities'])}% – {max(result['humidities'])}%", file=sys.stderr)
