import json, time, random, os
from datetime import datetime
import paho.mqtt.client as mqtt

BROKER = os.getenv('MQTT_HOST', 'localhost')
ZONES = {
    'zone1': {'id': 1, 'station_id': 101, 'base_pm25': 42, 'base_pm10': 65},
    'zone2': {'id': 2, 'station_id': 102, 'base_pm25': 48, 'base_pm10': 72},
    'zone3': {'id': 3, 'station_id': 103, 'base_pm25': 35, 'base_pm10': 54},
    'zone4': {'id': 4, 'station_id': 104, 'base_pm25': 45, 'base_pm10': 70},
    'zone5': {'id': 5, 'station_id': 105, 'base_pm25': 40, 'base_pm10': 62},
}

client = mqtt.Client()
MQTT_USER = os.getenv('MQTT_USER')
MQTT_PASS = os.getenv('MQTT_PASS')
if MQTT_USER:
    client.username_pw_set(MQTT_USER, MQTT_PASS or '')
client.connect(BROKER, int(os.getenv('MQTT_PORT', '1883')))

def airquality_payload(zone_key, hour):
    cfg = ZONES[zone_key]
    rush = 1.35 if 7 <= hour <= 9 or 17 <= hour <= 20 else 1.0
    extreme = random.random() < 0.02
    pm25 = cfg['base_pm25'] * rush + random.gauss(0, 8)
    if extreme:
        pm25 = 280
    return {
        'station_id': cfg['station_id'],
        'zone_id': cfg['id'],
        'pm25': round(max(5, pm25), 2),
        'pm10': round(max(10, cfg['base_pm10'] * rush + random.gauss(0, 12)), 2),
        'no2': round(random.uniform(25, 95) * rush, 2),
        'co': round(random.uniform(0.3, 4.2) * rush, 2),
        'o3': round(random.uniform(45, 135), 2),
        'recorded_at': datetime.now().isoformat()
    }

def weather_payload(zone_key):
    cfg = ZONES[zone_key]
    return {
        'zone_id': cfg['id'],
        'temperature': round(random.uniform(27, 34), 2),
        'humidity': round(random.uniform(58, 88), 2),
        'wind_speed': round(random.uniform(3, 18), 2),
        'wind_direction': random.choice([0, 45, 90, 135, 180, 225, 270, 315]),
        'pressure': round(random.uniform(1006, 1014), 2),
        'recorded_at': datetime.now().isoformat()
    }

while True:
    hour = datetime.now().hour
    for zone_key in ZONES:
        air = airquality_payload(zone_key, hour)
        weather = weather_payload(zone_key)
        client.publish(f'city/{zone_key}/airquality', json.dumps(air), qos=1)
        client.publish(f'city/{zone_key}/weather', json.dumps(weather), qos=1)
        print(f"[IoT] {zone_key}: PM2.5={air['pm25']} weather={weather['temperature']}C")
    time.sleep(30)
