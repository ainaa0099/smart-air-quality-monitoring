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
    # Simulasi kondisi cuaca Jakarta menggunakan Distribusi Gauss agar sesuai dataset training
    temperature = round(random.normalvariate(30.5, 2.0), 1)
    humidity = round(random.normalvariate(75.0, 6.0), 1)
    humidity = max(10.0, min(100.0, humidity))
    
    wind_speed = round(random.expovariate(1.0 / 2.0), 1) # Mean wind speed 2.0 m/s
    wind_direction = round(random.uniform(0.0, 360.0), 1)
    
    # Korelasi ilmiah cuaca terhadap polutan
    weather_modifier = (33.0 - temperature) * 2.0 + (78.0 - humidity) * 0.4 - wind_speed * 2.5
    
    pm25 = max(5, int(random.normalvariate(40, 10) - weather_modifier))
    pm10 = max(10, int(pm25 * random.uniform(1.2, 1.4)))
    no2 = max(2, int(random.normalvariate(22, 5) - weather_modifier * 0.2))
    co = max(0.1, round(random.normalvariate(0.7, 0.15) - weather_modifier * 0.01, 2))
    o3 = max(5, int(random.normalvariate(28, 8) + (temperature * 0.4)))
    
    # Injeksi Dua Jenis Anomali Terstruktur (Peluang 4% total)
    if random.random() < 0.04:
        anomaly_type = random.choice(['spike', 'zero_drop'])
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

def start_simulator():
    # Mengamankan kompatibilitas paho-mqtt v1.x dan v2.x secara dinamis
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
        print(f"Koneksi ke broker MQTT gagal: {e}. Pastikan Mosquitto aktif!")
        return

    # Realignment Zone ID agar sinkron 100% dengan rancangan database utama kelompok
    zones = [
        {"zone_id": 1, "station_id": 101},  # Jakarta Pusat
        {"zone_id": 2, "station_id": 102},  # Jakarta Utara
        {"zone_id": 3, "station_id": 103},  # Jakarta Selatan
        {"zone_id": 4, "station_id": 104},  # Jakarta Timur
        {"zone_id": 5, "station_id": 105}   # Jakarta Barat
    ]

    print("Memulai pemancaran telemetri IoT berstandar industri (Interval: 30 detik)...")
    client.loop_start()
    
    try:
        while True:
            for zone in zones:
                telemetry_data = generate_zone_reading(zone["zone_id"], zone["station_id"])
                
                # SINKRONISASI BUNGKUS PAYLOAD: Dibungkus ke dalam objek 'data' agar terbaca oleh app.py
                wrapped_payload = {
                    "event": "telemetry.recorded",
                    "data": telemetry_data
                }
                
                json_payload = json.dumps(wrapped_payload)
                client.publish(MQTT_TOPIC, json_payload, qos=1)
                print(f"Broadcast -> Zone {zone['zone_id']} | PM2.5: {telemetry_data['pm25']} | Temp: {telemetry_data['temperature']}°C")
                
            time.sleep(30)
            
    except KeyboardInterrupt:
        print("\nSimulator dihentikan oleh pengguna.")
    finally:
        client.loop_stop()
        client.disconnect()

if __name__ == "__main__":
    start_simulator()