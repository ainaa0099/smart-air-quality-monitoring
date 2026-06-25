import time
import json
import random
import os
from datetime import datetime
import paho.mqtt.client as mqtt
import requests
from requests.exceptions import RequestException
import numpy as np

# Konfigurasi MQTT Broker dari environment variable atau default localhost
MQTT_HOST = os.getenv("MQTT_HOST", "localhost")
MQTT_PORT = int(os.getenv("MQTT_PORT", 1883))
MQTT_TOPIC = "city/air/telemetry"

# Konfigurasi untuk koneksi ke Gateway & Auth Service
GATEWAY_URL = os.getenv("GATEWAY_URL", "http://localhost:3000")
SIMULATOR_CLIENT_ID = os.getenv("SIMULATOR_CLIENT_ID")
SIMULATOR_CLIENT_SECRET = os.getenv("SIMULATOR_CLIENT_SECRET")

m2m_token = None

def on_connect(client, userdata, flags, reason_code, properties):
    if reason_code.is_success:
        print(f"Terhubung ke MQTT Broker di {MQTT_HOST}:{MQTT_PORT}")
    else:
        print(f"Gagal terhubung ke MQTT Broker, kode status: {reason_code}")

def generate_zone_reading(zone_id, station_id):
    # Simulasi kondisi cuaca Jakarta menggunakan Distribusi Gauss agar sesuai dataset training
    temperature = round(float(np.random.normal(30.5, 2.0)), 1)
    humidity = round(float(np.random.normal(75.0, 6.0)), 1)
    humidity = max(10.0, min(100.0, humidity))
    
    wind_speed = round(float(np.random.exponential(2.0)), 1)
    wind_direction = round(float(np.random.uniform(0.0, 360.0)), 1)
    
    # Korelasi ilmiah cuaca terhadap polutan
    weather_modifier = (33.0 - temperature) * 2.0 + (78.0 - humidity) * 0.4 - wind_speed * 2.5
    pm25 = max(5, int(np.random.normal(40, 10) - weather_modifier))
    pm10 = max(10, int(pm25 * np.random.uniform(1.2, 1.4)))
    no2 = max(2, int(np.random.normal(22, 5) - weather_modifier * 0.2))
    co = max(0.1, round(float(np.random.normal(0.7, 0.15) - weather_modifier * 0.01), 2))
    o3 = max(5, int(np.random.normal(28, 8) + (temperature * 0.4)))
    
    if np.random.rand() < 0.04:
        anomaly_type = np.random.choice(['spike', 'zero_drop'])
        if anomaly_type == 'spike':
            pm25 += 130
            pm10 += 160
            co += 3.0
            print(f"[SIMULATOR ALERT] Injeksi Anomali 'Spike' pada Zone {zone_id} ({station_id})")
        else:
            # Kegagalan hardware / sensor offline mendadak
            pm25, pm10, no2, co, o3 = 0, 0, 0, 0.0, 0
            print(f"[SIMULATOR ALERT] Injeksi Anomali 'Zero-Drop' (Sensor Mati) pada Zone {zone_id} ({station_id})")

    return {
        "station_id": station_id,
        "zone_id": zone_id,
        "pm25": pm25,
        "pm10": pm10,
        "no2": no2,
        "co": co,
        "o3": o3,
        "temperature": temperature,
        "humidity": humidity,
        "wind_speed": wind_speed,
        "wind_direction": wind_direction,
        "recorded_at": datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    }

def get_m2m_token():
    """
    Mengautentikasi simulator dengan Auth Service menggunakan Client Credentials
    untuk mendapatkan token akses machine-to-machine (M2M).
    """
    global m2m_token
    
    if not SIMULATOR_CLIENT_ID or not SIMULATOR_CLIENT_SECRET:
        print("[AUTH_ERROR] SIMULATOR_CLIENT_ID dan SIMULATOR_CLIENT_SECRET belum di-set di environment!")
        return None

    token_url = f"{GATEWAY_URL}/oauth/token"
    payload = {
        'grant_type': 'client_credentials',
        'client_id': SIMULATOR_CLIENT_ID,
        'client_secret': SIMULATOR_CLIENT_SECRET,
        'scope': 'iot:write' 
    }
    
    try:
        print(f"Mencoba mendapatkan token M2M dari {token_url}...")
        response = requests.post(token_url, data=payload, timeout=10)
        response.raise_for_status()
        
        token_data = response.json()
        m2m_token = token_data.get('data', {}).get('access_token')
        
        if m2m_token:
            print("[AUTH_SUCCESS] Berhasil mendapatkan token M2M untuk simulator.")
        else:
            print(f"[AUTH_ERROR] Gagal mendapatkan token. Respons: {token_data}")
            
        return m2m_token
    except RequestException as e:
        print(f"[AUTH_ERROR] Gagal menghubungi Auth Service di {token_url}: {e}")
        return None

def post_telemetry_via_gateway(token, telemetry_data):
    """
    Mengirim data telemetri ke endpoint yang dilindungi di API Gateway.
    """
    if not token:
        print("[HTTP_ERROR] Tidak ada token, pengiriman data via Gateway dibatalkan.")
        return

    # Endpoint ini harus ada di gateway dan di-proxy ke service yang sesuai
    api_url = f"{GATEWAY_URL}/iot/air"
    headers = {
        'Authorization': f'Bearer {token}',
        'Content-Type': 'application/json'
    }
    
    try:
        response = requests.post(api_url, headers=headers, data=json.dumps(telemetry_data), timeout=5)
        response.raise_for_status()
        print(f"HTTP POST -> Zone {telemetry_data['zone_id']} | Status: {response.status_code}")
    except RequestException as e:
        print(f"[HTTP_ERROR] Gagal mengirim data ke Gateway di {api_url}: {e}")

def start_simulator():
    client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2)
    client.on_connect = on_connect
    
    try:
        client.connect(MQTT_HOST, MQTT_PORT, 60)
    except Exception as e:
        print(f"Koneksi ke broker MQTT gagal: {e}. Pastikan Mosquitto aktif!")
        return

    token = get_m2m_token()

    # Realignment Zone ID agar sinkron 100% dengan rancangan database utama kelompok
    zones = [
        {"zone_id": 1, "station_id": 101},  # Jakarta Pusat
        {"zone_id": 2, "station_id": 102},  # Jakarta Utara
        {"zone_id": 3, "station_id": 103},  # Jakarta Selatan
        {"zone_id": 4, "station_id": 104},  # Jakarta Timur
        {"zone_id": 5, "station_id": 105}   # Jakarta Barat
    ]

    print("\nMemulai pemancaran telemetri IoT (Interval: 15 detik)...")
    client.loop_start()
    
    try:
        while True:
            for zone in zones:
                telemetry_data = generate_zone_reading(zone["zone_id"], zone["station_id"])
                
                # --- ALUR UTAMA (TIDAK DIUBAH): Kirim via MQTT (Mode Asinkron, Real-time) ---
                wrapped_payload = {
                    "event": "telemetry.recorded",
                    "data": telemetry_data
                }
                json_payload = json.dumps(wrapped_payload)
                client.publish(MQTT_TOPIC, json_payload, qos=1)
                print(f"MQTT Publish -> Zone {zone['zone_id']} | PM2.5: {telemetry_data['pm25']}")
                
            time.sleep(30) 
            
    except KeyboardInterrupt:
        print("\nSimulator dihentikan oleh pengguna.")
    finally:
        client.loop_stop()
        client.disconnect()

if __name__ == "__main__":
    start_simulator()