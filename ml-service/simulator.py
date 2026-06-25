import time
import json
import random
import os
from datetime import datetime
import paho.mqtt.client as mqtt

# Konfigurasi MQTT Broker dari environment variable atau default localhost
MQTT_HOST = os.getenv("MQTT_HOST", "localhost")
MQTT_PORT = int(os.getenv("MQTT_PORT", 1883))

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
    
    wind_speed = round(random.expovariate(1.0 / 2.0), 1)
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
            pm25 += int(random.uniform(100, 150))
            pm10 += int(random.uniform(120, 180))
            co += round(random.uniform(2.0, 4.0), 2)
            print(f"[SIMULATOR ALERT] Injeksi Anomali 'Spike' pada Zone {zone_id} ({station_id})")
        else:
            pm25, pm10, no2, co, o3 = 0, 0, 0, 0.0, 0
            print(f"[SIMULATOR ALERT] Injeksi Anomali 'Zero-Drop' (Sensor Mati) pada Zone {zone_id} ({station_id})")

    recorded_at = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    # SPLIT: Air Quality Payload
    air_payload = {
        "station_id": station_id,
        "zone_id": zone_id,
        "pm25": pm25,
        "pm10": pm10,
        "no2": no2,
        "co": co,
        "o3": o3,
        "recorded_at": recorded_at
    }

    # SPLIT: Weather Payload
    weather_payload = {
        "station_id": station_id,
        "zone_id": zone_id,
        "temperature": temperature,
        "humidity": humidity,
        "wind_speed": wind_speed,
        "wind_direction": wind_direction,
        "recorded_at": recorded_at
    }

    return air_payload, weather_payload

def start_simulator():
    callback_api_enum = getattr(mqtt, "CallbackAPIVersion", None)
    if callback_api_enum is not None:
        client = mqtt.Client(callback_api_version=getattr(callback_api_enum, "VERSION1"))
    else:
        client = mqtt.Client()
        
    client.on_connect = on_connect
    
    try:
        client.connect(MQTT_HOST, MQTT_PORT, 60)
    except Exception as e:
        print(f"Koneksi ke broker MQTT gagal: {e}. Pastikan Mosquitto aktif di {MQTT_HOST}:{MQTT_PORT}.")
        return

    zones = [
        {"zone_id": 1, "station_id": 101},  # Jakarta Pusat
        {"zone_id": 2, "station_id": 102},  # Jakarta Utara
        {"zone_id": 3, "station_id": 103},  # Jakarta Selatan
        {"zone_id": 4, "station_id": 104},  # Jakarta Timur
        {"zone_id": 5, "station_id": 105}   # Jakarta Barat
    ]

    print("Memulai pemancaran telemetri IoT di 5 zona (Interval: 30 detik)...")
    client.loop_start()
    
    try:
        while True:
            for zone in zones:
                air_data, weather_data = generate_zone_reading(zone["zone_id"], zone["station_id"])
                
                # Dynamic Topic Routing
                air_topic = f"city/zone{zone['zone_id']}/airquality"
                weather_topic = f"city/zone{zone['zone_id']}/weather"
                
                client.publish(air_topic, json.dumps(air_data), qos=1)
                client.publish(weather_topic, json.dumps(weather_data), qos=1)
                
                print(f"Broadcast -> Zone {zone['zone_id']} | PM2.5: {air_data['pm25']} | Temp: {weather_data['temperature']}°C")
                
            time.sleep(30)
            
    except KeyboardInterrupt:
        print("\nSimulator dihentikan oleh pengguna.")
    finally:
        client.loop_stop()
        client.disconnect()

if __name__ == "__main__":
    start_simulator()