import time
import json
import random
import os
from datetime import datetime
import paho.mqtt.client as mqtt

# Konfigurasi MQTT Broker dari environment variable atau default localhost
MQTT_HOST = os.getenv("MQTT_HOST", "localhost")
MQTT_PORT = int(os.getenv("MQTT_PORT", 1883))
MQTT_TOPIC = "city/air/telemetry"

def on_connect(client, userdata, flags, rc, *args):
    if rc == 0:
        print(f"Terhubung ke MQTT Broker di {MQTT_HOST}:{MQTT_PORT}")
    else:
        print(f"Gagal terhubung ke MQTT Broker, kode status: {rc}")

def generate_zone_reading(zone_id, station_id):
    # Simulasi baseline parameter cuaca dan polusi DKI Jakarta secara acak terikat
    temperature = round(random.uniform(27.0, 34.0), 1)
    humidity = round(random.uniform(65.0, 85.0), 1)
    wind_speed = round(random.uniform(0.5, 5.5), 1)
    
    # Sinkronisasi tipe data: Mengubah arah angin menjadi float (0.0 - 360.0 derajat)
    wind_direction = round(random.uniform(0.0, 360.0), 1)
    
    # Perhitungan polutan dasar
    pm25 = random.randint(15, 95)
    pm10 = int(pm25 * random.uniform(1.2, 1.6))
    no2 = random.randint(10, 45)
    co = round(random.uniform(0.4, 1.9), 2)
    o3 = random.randint(15, 60)
    
    # Injeksi data anomali buatan (peluang 3%) untuk menguji Isolation Forest di app.py
    if random.random() < 0.03:
        pm25 += 140
        pm10 += 170
        co += 3.5
        print(f"[SIMULATOR ALERT] Injeksi data anomali buatan pada Zone {zone_id}")

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

def start_simulator():
    # Menggunakan getattr secara dinamis untuk mengelabui pengecekan statis Pylance
    callback_api_enum = getattr(mqtt, "CallbackAPIVersion", None)
    
    if callback_api_enum is not None:
        version_value = getattr(callback_api_enum, "VERSION1")
        client = mqtt.Client(callback_api_version=version_value)
    else:
        client = mqtt.Client()
        
    client.on_connect = on_connect
    
    try:
        client.connect(MQTT_HOST, MQTT_PORT, 60)
    except Exception as e:
        print(f"Koneksi ke broker MQTT gagal: {e}")
        print("Pastikan MQTT Broker (Mosquitto) sudah berjalan.")
        return

    # Definisi 5 Zona DKI Jakarta sesuai instruksi tugas Anggota 5
    zones = [
        {"zone_id": 1, "station_id": 101}, # Jakarta Pusat
        {"zone_id": 2, "station_id": 102}, # Jakarta Utara
        {"zone_id": 3, "station_id": 103}, # Jakarta Timur
        {"zone_id": 4, "station_id": 104}, # Jakarta Selatan
        {"zone_id": 5, "station_id": 105}  # Jakarta Barat
    ]

    print("Memulai pengiriman data telemetri IoT (Interval: 30 detik)...")
    client.loop_start()
    
    try:
        while True:
            for zone in zones:
                payload = generate_zone_reading(zone["zone_id"], zone["station_id"])
                json_payload = json.dumps(payload)
                
                client.publish(MQTT_TOPIC, json_payload, qos=1)
                print(f"Sent Telemetry -> Zone {zone['zone_id']} | PM2.5: {payload['pm25']} | Wind Dir: {payload['wind_direction']}°")
                
            time.sleep(30)
            
    except KeyboardInterrupt:
        print("\nSimulator dihentikan oleh pengguna.")
    finally:
        client.loop_stop()
        client.disconnect()

if __name__ == "__main__":
    start_simulator()