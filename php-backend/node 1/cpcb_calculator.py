"""
Official CPCB (Central Pollution Control Board, India) Air Quality Index Calculator.

Implements the official two-step standard:
Step 1: Piecewise linear interpolation for individual pollutant sub-indices:
        I_p = [ (I_hi - I_lo) / (B_hi - B_lo) * (C_p - B_lo) ] + I_lo
Step 2: Maximum operator across the 5 predicted criteria pollutants:
        AQI = max(I_PM2.5, I_PM10, I_NO2, I_CO, I_O3)
        Dominant Pollutant = argmax(sub_indices)
"""

CPCB_BREAKPOINTS = {
    'pm25': [
        (0.0, 30.0, 0, 50),
        (30.0, 60.0, 51, 100),
        (60.0, 90.0, 101, 200),
        (90.0, 120.0, 201, 300),
        (120.0, 250.0, 301, 400),
        (250.0, 500.0, 401, 500)
    ],
    'pm10': [
        (0.0, 50.0, 0, 50),
        (50.0, 100.0, 51, 100),
        (100.0, 250.0, 101, 200),
        (250.0, 350.0, 201, 300),
        (350.0, 430.0, 301, 400),
        (430.0, 600.0, 401, 500)
    ],
    'no2': [
        (0.0, 40.0, 0, 50),
        (40.0, 80.0, 51, 100),
        (80.0, 180.0, 101, 200),
        (180.0, 280.0, 201, 300),
        (280.0, 400.0, 301, 400),
        (400.0, 500.0, 401, 500)
    ],
    'co': [
        (0.0, 1.0, 0, 50),
        (1.0, 2.0, 51, 100),
        (2.0, 10.0, 101, 200),
        (10.0, 17.0, 201, 300),
        (17.0, 34.0, 301, 400),
        (34.0, 50.0, 401, 500)
    ],
    'o3': [
        (0.0, 50.0, 0, 50),
        (50.0, 100.0, 51, 100),
        (100.0, 168.0, 101, 200),
        (168.0, 208.0, 201, 300),
        (208.0, 748.0, 301, 400),
        (748.0, 1000.0, 401, 500)
    ]
}

def calculate_sub_index(param_key: str, val: float) -> float:
    """Calculate individual pollutant sub-index via piecewise linear interpolation."""
    if val is None or val < 0:
        return 0.0

    tiers = CPCB_BREAKPOINTS.get(param_key.lower(), [])
    for c_lo, c_hi, i_lo, i_hi in tiers:
        if val <= c_hi:
            return round(max(0.0, ((i_hi - i_lo) / (c_hi - c_lo)) * (val - c_lo) + i_lo), 1)

    if tiers:
        c_lo, c_hi, i_lo, i_hi = tiers[-1]
        return min(500.0, round(((i_hi - i_lo) / (c_hi - c_lo)) * (val - c_lo) + i_lo), 1)

    return 0.0

def calculate_aqi(pm25: float, pm10: float, no2: float, co: float, o3: float):
    """
    Calculate overall AQI and identify dominant pollutant from the 5 criteria pollutants.
    
    Formula:
        AQI = max(I_PM2.5, I_PM10, I_NO2, I_CO, I_O3)
        dominant_pollutant = argmax(sub_indices)
    """
    sub_indices = {
        'PM2.5': calculate_sub_index('pm25', pm25),
        'PM10':  calculate_sub_index('pm10', pm10),
        'NO₂':   calculate_sub_index('no2', no2),
        'CO':    calculate_sub_index('co', co),
        'O₃':    calculate_sub_index('o3', o3)
    }

    dominant_key = max(sub_indices, key=lambda k: sub_indices[k])
    aqi = int(round(sub_indices[dominant_key]))

    return aqi, dominant_key, sub_indices

def get_cpcb_category(aqi: int) -> dict:
    """Return CPCB official health category and color scheme matching the Live page bar."""
    if aqi <= 50:
        return {
            'category': 'Good',
            'severity': 'GOOD',
            'color': '#16a34a',
            'bg': '#f0fdf4',
            'border': 'rgba(22, 163, 74, 0.35)',
            'health_impact': 'Minimal impact'
        }
    elif aqi <= 100:
        return {
            'category': 'Moderate',
            'severity': 'MODERATE',
            'color': '#ca8a04',
            'bg': '#fefce8',
            'border': 'rgba(202, 138, 4, 0.38)',
            'health_impact': 'Minor breathing discomfort to sensitive people'
        }
    elif aqi <= 200:
        return {
            'category': 'Poor',
            'severity': 'POOR',
            'color': '#ea580c',
            'bg': '#fff7ed',
            'border': 'rgba(234, 88, 12, 0.38)',
            'health_impact': 'Breathing discomfort to people with lungs, asthma and heart diseases'
        }
    elif aqi <= 300:
        return {
            'category': 'Unhealthy',
            'severity': 'UNHEALTHY',
            'color': '#e11d48',
            'bg': '#fff1f2',
            'border': 'rgba(225, 29, 72, 0.38)',
            'health_impact': 'Breathing discomfort to most people on prolonged exposure'
        }
    elif aqi <= 400:
        return {
            'category': 'Severe',
            'severity': 'SEVERE',
            'color': '#9333ea',
            'bg': '#faf5ff',
            'border': 'rgba(147, 51, 234, 0.38)',
            'health_impact': 'Respiratory illness on prolonged exposure'
        }
    else:
        return {
            'category': 'Hazardous',
            'severity': 'HAZARDOUS',
            'color': '#b91c1c',
            'bg': '#fef2f2',
            'border': 'rgba(185, 28, 28, 0.40)',
            'health_impact': 'Affects healthy people and seriously impacts those with existing diseases'
        }
