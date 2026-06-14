import pandas as pd
import numpy as np
import os
from datetime import datetime, timedelta

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.join(BASE_DIR, 'data')
CSV_PATH = os.path.join(DATA_DIR, 'smartcity_telemetry.csv')

def generate_smart_city_data(num_rows=5500):
    np.random.seed(42)
    
    # Representasi 5 Zone ID dan Station ID sesuai database utama
    zone_ids = [1, 2, 3, 4, 5]
    station_ids = [101, 102, 103, 104, 105]
    
    base_time = datetime.now()
    timestamps = [base_time - timedelta(minutes=30 * i) for i in range(num_rows)]
    
    data = []
    for i in range(num_rows):
        idx = np.random.randint(0, 5)
        zone_id = zone_ids[idx]
        station_id = station_ids[idx]
        
        # Format ISO sesuai dengan gmdate('c') milik PHP
        recorded_at = timestamps[i].strftime('%Y-%m-%d %H:%M:%S')
        
        # Simulasi parameter cuaca Jakarta
        temperature = round(float(np.random.normal(30, 3)), 1)
        humidity = round(float(np.random.normal(75, 10)), i % 2)
        humidity = max(10, min(100, humidity))
        
        wind_speed = round(float(np.random.exponential(2.5)), 1)
        wind_direction = int(np.random.uniform(0, 360))
        
        # Korelasi logika cuaca terhadap akumulasi polutan
        weather_modifier = (35 - temperature) * 2 + (80 - humidity) * 0.5 - wind_speed * 3
        
        pm25 = max(5, int(np.random.normal(45, 15) - weather_modifier))
        pm10 = max(10, int(pm25 * np.random.uniform(1.2, 1.8)))
        no2 = max(2, int(np.random.normal(20, 8) - weather_modifier * 0.3))
        co = max(0.1, round(float(np.random.normal(0.8, 0.3) - weather_modifier * 0.01), 2))
        o3 = max(5, int(np.random.normal(30, 12) + (temperature * 0.5)))
        
        # Injeksi anomali struktural sebesar 5 persen untuk kebutuhan testing
        is_anomaly = 0
        if np.random.rand() < 0.05:
            is_anomaly = 1
            anomaly_type = np.random.choice(['spike', 'zero_drop'])
            if anomaly_type == 'spike':
                pm25 += 120
                pm10 += 150
                co += 3.5
            else:
                pm25, pm10, no2, co, o3 = 0, 0, 0, 0.0, 0
                
        data.append([
            recorded_at, station_id, zone_id, pm25, pm10, no2, co, o3, 
            temperature, humidity, wind_speed, wind_direction, is_anomaly
        ])
        
    columns = [
        'recorded_at', 'station_id', 'zone_id', 'pm25', 'pm10', 'no2', 'co', 'o3', 
        'temperature', 'humidity', 'wind_speed', 'wind_direction', 'ground_truth_anomaly'
    ]
    
    df = pd.DataFrame(data, columns=columns)
    
    # Membuat folder data secara otomatis di dalam ml-service/ jika belum ada
    os.makedirs(DATA_DIR, exist_ok=True)
    df.to_csv(CSV_PATH, index=False)
    print(f"Berhasil membuat {num_rows} baris data pada: {CSV_PATH}")

if __name__ == '__main__':
    generate_smart_city_data()