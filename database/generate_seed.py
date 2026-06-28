"""
generate_seed.py
Generates database/seed.sql with dummy data for the
Smart Air Quality Monitoring platform.

Statistical patterns (timestamp interval, distributions, and the
weather -> pollution correlation) are kept in sync with
ml-service/generate_dataset.py so that seeded historical data looks
consistent with what the ML models were trained on.

Run with:
    python generate_seed.py

Output:
    seed.sql (in the same folder this script runs from)
"""

import random
import bcrypt
from datetime import datetime, timedelta

random.seed(42)  # reproducible output

OUTPUT_FILE = "seed.sql"

ZONES = [
    ("Zone 1", "Jakarta Pusat", "-6.1862,106.8284", 48.13),
    ("Zone 2", "Jakarta Utara", "-6.1384,106.8636", 146.66),
    ("Zone 3", "Jakarta Selatan", "-6.2615,106.8106", 141.27),
    ("Zone 4", "Jakarta Timur", "-6.2250,106.9004", 188.03),
    ("Zone 5", "Jakarta Barat", "-6.1352,106.7621", 129.54),
]

# zone_id -> station_id, matching ml-service/generate_dataset.py
STATION_IDS = {1: 101, 2: 102, 3: 103, 4: 104, 5: 105}

FIRST_NAMES = [
    "Andi", "Budi", "Citra", "Dewi", "Eka", "Fajar", "Gita", "Hadi",
    "Indah", "Joko", "Kartika", "Lukman", "Maya", "Nanda", "Oki",
    "Putri", "Rendi", "Sari", "Taufik", "Umar", "Vina", "Wati",
    "Yoga", "Zaki", "Ayu",
]
LAST_NAMES = [
    "Wijaya", "Santoso", "Pratama", "Lestari", "Hidayat", "Saputra",
    "Wibowo", "Setiawan", "Permata", "Kusuma", "Nugroho", "Hartono",
    "Susanto", "Halim", "Gunawan",
]

REPORT_CATEGORIES = [
    "air_pollution", "dust", "industrial_smoke",
    "vehicle_emission", "burning", "other",
]
REPORT_STATUSES = ["pending", "in_progress", "resolved", "rejected"]
REPORT_DESCRIPTIONS = [
    "Asap kendaraan sangat tebal di area ini setiap pagi",
    "Bau menyengat dari pabrik dekat permukiman",
    "Debu konstruksi mengganggu pernapasan warga sekitar",
    "Pembakaran sampah liar terjadi hampir setiap sore",
    "Kualitas udara terasa sangat buruk sejak beberapa hari",
    "Asap hitam tebal terlihat dari cerobong pabrik",
    "Polusi dari kemacetan lalu lintas semakin parah",
    "Bau bahan kimia tercium kuat di sekitar perumahan",
]

NOTIFICATION_TEMPLATES = [
    ("Laporan Anda Diterima", "Laporan kerusakan/keluhan Anda telah diterima dan sedang ditinjau oleh petugas."),
    ("Status Laporan Diperbarui", "Status laporan Anda telah diperbarui menjadi sedang diproses."),
    ("Peringatan Kualitas Udara", "Kualitas udara di zona Anda saat ini dalam kategori Tidak Sehat. Disarankan mengurangi aktivitas luar ruangan."),
    ("Laporan Telah Selesai Ditindaklanjuti", "Laporan yang Anda ajukan telah selesai ditindaklanjuti oleh petugas terkait."),
]

ALERT_TYPES = ["aqi_threshold", "anomaly_detected"]
ALERT_POLLUTANTS = ["PM2.5", "PM10", "NO2", "CO", "O3"]
ALERT_SEVERITIES = ["Peringatan", "Kritis"]


def esc(text: str) -> str:
    """Escape single quotes for SQL string literals."""
    return text.replace("'", "\\'")


def hash_password(plain: str) -> str:
    return bcrypt.hashpw(plain.encode(), bcrypt.gensalt()).decode()


def gauss_clamped(mean: float, stdev: float, floor: float) -> float:
    """random.gauss equivalent of np.random.normal, clamped to a floor
    so values never go negative or below a physically sensible minimum."""
    return max(floor, random.gauss(mean, stdev))


def aqi_category_from_pm25(pm25: float) -> tuple[int, str]:
    if pm25 <= 25:
        return random.randint(20, 50), "Good"
    if pm25 <= 50:
        return random.randint(51, 100), "Moderate"
    if pm25 <= 90:
        return random.randint(101, 150), "Unhealthy"
    if pm25 <= 150:
        return random.randint(151, 200), "Very Unhealthy"
    return random.randint(201, 300), "Hazardous"


def dominant_pollutant_from(pm25, pm10, no2, co, o3) -> str:
    """Not present in the raw ML dataset, but derived the same way the
    Air Quality / Python ML API exposes it: whichever pollutant has the
    highest relative reading. CO is scaled up since its raw ppm values
    are much smaller than the others."""
    values = {"PM2.5": pm25, "PM10": pm10, "NO2": no2, "CO": co * 20, "O3": o3}
    return max(values, key=values.get)


def generate_zones_sql() -> str:
    lines = ["-- Zones (5 rows)", "INSERT INTO zones (name, city_district, coordinates, area_km2) VALUES"]
    rows = [f"('{n}', '{d}', '{c}', {a})" for n, d, c, a in ZONES]
    lines.append(",\n".join(rows) + ";")
    return "\n".join(lines) + "\n"


def generate_citizens_sql(count: int = 50) -> str:
    lines = [f"-- Citizens ({count} rows)",
             "INSERT INTO citizen_citizens (nik, name, email, password, phone, zone_id, role) VALUES"]
    password_hash = hash_password("password123")
    rows = []
    used_names = set()

    for i in range(1, count + 1):
        while True:
            full_name = f"{random.choice(FIRST_NAMES)} {random.choice(LAST_NAMES)}"
            if full_name not in used_names:
                used_names.add(full_name)
                break
        nik = f"3171{str(i).zfill(12)}"
        email = f"{full_name.lower().replace(' ', '.')}{i}@example.com"
        phone = f"0812{str(random.randint(10000000, 99999999))}"
        zone_id = random.randint(1, 5)
        role = "admin" if i == 1 else "citizen"
        rows.append(
            f"('{nik}', '{esc(full_name)}', '{email}', '{password_hash}', '{phone}', {zone_id}, '{role}')"
        )

    lines.append(",\n".join(rows) + ";")
    return "\n".join(lines) + "\n"


def generate_air_and_weather_sql(count: int = 200) -> tuple[str, str]:
    """Generates air_readings and env_weather together, row-by-row per
    zone and timestamp, using the same 30-minute interval, gaussian
    distributions, and weather -> pollution correlation formula as
    ml-service/generate_dataset.py. Returns both SQL blocks as a tuple
    so a single weather/pollution pair always shares the same
    underlying conditions, just like in the real pipeline."""

    air_lines = [f"-- Air Quality Readings ({count} rows)",
                 "INSERT INTO air_readings (station_id, zone_id, pm25, pm10, no2, co, o3, "
                 "aqi_value, aqi_category, dominant_pollutant, recorded_at) VALUES"]
    weather_lines = [f"-- Environment Weather Readings ({count} rows)",
                      "INSERT INTO env_weather (zone_id, temperature, humidity, wind_speed, "
                      "wind_direction, recorded_at) VALUES"]

    air_rows = []
    weather_rows = []

    base_time = datetime(2026, 6, 1, 0, 0, 0)

    for i in range(count):
        zone_id = (i % 5) + 1
        station_id = STATION_IDS[zone_id]

        # 30-minute interval, matching the ML dataset generator
        recorded_at = base_time + timedelta(minutes=30 * i)

        # Same gaussian distributions as generate_dataset.py
        temperature = round(gauss_clamped(30.5, 2.5, 18.0), 1)
        humidity = round(gauss_clamped(75.0, 8.0, 30.0), 1)
        wind_speed = round(gauss_clamped(3.0, 1.5, 0.0), 1)
        wind_direction = round(random.uniform(0, 359.9), 1)

        # Same weather -> pollution correlation formula as generate_dataset.py:
        # cooler temperature, higher humidity, and stronger wind all push
        # pollution down (wind disperses pollutants, hence the project's
        # "wind anomaly" requirement from the dosen).
        weather_modifier = (33.0 - temperature) * 2.0 + (78.0 - humidity) * 0.4 - wind_speed * 2.5

        pm25 = max(2, round(gauss_clamped(40, 12, 0) - weather_modifier, 2))
        pm10 = max(3, round(pm25 * random.uniform(1.1, 1.4), 2))
        no2 = max(1, round(gauss_clamped(22, 6, 0) - weather_modifier * 0.2, 2))
        co = max(0.1, round(gauss_clamped(0.7, 0.2, 0) - weather_modifier * 0.01, 2))
        o3 = max(2, round(gauss_clamped(28, 10, 0) + (temperature * 0.4), 2))

        aqi_value, aqi_category = aqi_category_from_pm25(pm25)
        dominant = dominant_pollutant_from(pm25, pm10, no2, co, o3)

        ts_str = recorded_at.strftime("%Y-%m-%d %H:%M:%S")

        air_rows.append(
            f"({station_id}, {zone_id}, {pm25}, {pm10}, {no2}, {co}, {o3}, {aqi_value}, "
            f"'{aqi_category}', '{dominant}', '{ts_str}')"
        )
        weather_rows.append(
            f"({zone_id}, {temperature}, {humidity}, {wind_speed}, {wind_direction}, '{ts_str}')"
        )

    air_lines.append(",\n".join(air_rows) + ";")
    weather_lines.append(",\n".join(weather_rows) + ";")

    return "\n".join(air_lines) + "\n", "\n".join(weather_lines) + "\n"


def generate_reports_sql(count: int = 20, citizen_count: int = 50) -> str:
    lines = [f"-- Citizen Reports ({count} rows)",
             "INSERT INTO citizen_reports (citizen_id, category, description, zone_id, status) VALUES"]
    rows = []

    for _ in range(count):
        citizen_id = random.randint(1, citizen_count)
        category = random.choice(REPORT_CATEGORIES)
        description = random.choice(REPORT_DESCRIPTIONS)
        zone_id = random.randint(1, 5)
        status = random.choice(REPORT_STATUSES)

        rows.append(
            f"({citizen_id}, '{category}', '{esc(description)}', {zone_id}, '{status}')"
        )

    lines.append(",\n".join(rows) + ";")
    return "\n".join(lines) + "\n"


def generate_notifications_sql(count: int = 30, citizen_count: int = 50) -> str:
    """citizen_notifications is not in the PDF's minimum data list, but
    every report status change should realistically produce one, so a
    modest sample is seeded to keep GET /api/notifications testable
    without extra manual setup."""
    lines = [f"-- Citizen Notifications ({count} rows)",
             "INSERT INTO citizen_notifications (citizen_id, title, body, is_read) VALUES"]
    rows = []

    for i in range(count):
        citizen_id = random.randint(1, citizen_count)
        title, body = random.choice(NOTIFICATION_TEMPLATES)
        is_read = 1 if i % 3 == 0 else 0
        rows.append(f"({citizen_id}, '{esc(title)}', '{esc(body)}', {is_read})")

    lines.append(",\n".join(rows) + ";")
    return "\n".join(lines) + "\n"


def generate_env_alerts_sql(count: int = 15) -> str:
    """env_alerts is populated automatically at runtime via the
    anomaly.alert RabbitMQ consumer (scenario S6), so it does not
    strictly need seed data. A small sample is included only as a
    fallback so GET /api/environment/alerts is not empty before that
    flow is demoed live."""
    lines = [f"-- Environment Alerts ({count} rows, sample data for demo fallback)",
             "INSERT INTO env_alerts (zone_id, event, alert_type, pollutant, anomaly_score, "
             "severity, value, threshold) VALUES"]
    rows = []

    for _ in range(count):
        zone_id = random.randint(1, 5)
        alert_type = random.choice(ALERT_TYPES)
        pollutant = random.choice(ALERT_POLLUTANTS)
        severity = random.choice(ALERT_SEVERITIES)
        anomaly_score = round(random.uniform(0.55, 0.98), 4)
        value = round(random.uniform(90, 220), 2)
        threshold = round(value * random.uniform(0.5, 0.8), 2)

        rows.append(
            f"({zone_id}, 'anomaly.alert', '{alert_type}', '{pollutant}', {anomaly_score}, "
            f"'{severity}', {value}, {threshold})"
        )

    lines.append(",\n".join(rows) + ";")
    return "\n".join(lines) + "\n"


def main():
    air_sql, weather_sql = generate_air_and_weather_sql(200)

    sections = [
        "-- ============================================================",
        "-- Smart Air Quality Monitoring Platform",
        "-- Seed Data (auto-generated by generate_seed.py)",
        "-- Statistical patterns kept in sync with",
        "-- ml-service/generate_dataset.py (30-min interval, gaussian",
        "-- distributions, weather -> pollution correlation formula)",
        "-- ============================================================",
        "",
        "USE smartcity;",
        "",
        generate_citizens_sql(50),
        air_sql,
        weather_sql,
        generate_reports_sql(20, citizen_count=50),
        generate_notifications_sql(30, citizen_count=50),
        generate_env_alerts_sql(15),
    ]

    content = "\n".join(sections)

    with open(OUTPUT_FILE, "w", encoding="utf-8") as f:
        f.write(content)

    print(f"Done. Generated {OUTPUT_FILE}")
    print(
        "Rows generated: 50 citizens, 200 air readings, "
        "200 weather readings, 20 reports, "
        "30 notifications, 15 env alerts"
    )


if __name__ == "__main__":
    main()