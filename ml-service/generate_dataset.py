import pandas as pd
import numpy as np
import os
from datetime import datetime, timedelta

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.join(BASE_DIR, 'data')
CSV_PATH = os.path.join(DATA_DIR, 'smartcity_telemetry.csv')

def generate_smart_city_data(num_timesteps=1100):
    # Menggunakan seed agar data sintetis yang dihasilkan selalu konsisten saat di-grade
    np.random.seed(42)
    
    # Representasi resmi 5 Zone ID dan Station ID sesuai rancangan basis data tim
    zones = [
        {"zone_id": 1, "station_id": 101},  # Jakarta Pusat
        {"zone_id": 2, "station_id": 102},  # Jakarta Utara
        {"zone_id": 3, "station_id": 103},  # Jakarta Selatan
        {"zone_id": 4, "station_id": 104},  # Jakarta Timur
        {"zone_id": 5, "station_id": 105}   # Jakarta Barat
    ]
    
    base_time = datetime.now()
    data = []
    
    print(f"Sedang memancarkan data runtun waktu paralel untuk 5 zona ({num_timesteps} langkah waktu)...")
    
    # Loop mundur berdasarkan langkah waktu
    for i in range(num_timesteps):
        current_ts = base_time - timedelta(minutes=30 * i)
        recorded_at = current_ts.strftime('%Y-%m-%d %H:%M:%S')
        
        # Loop untuk memastikan SETIAP zona mendapatkan pencatatan di setiap komponen waktu yang sama
        for zone in zones:
            zone_id = zone["zone_id"]
            station_id = zone["station_id"]
            
            # Simulasi parameter cuaca wilayah tropis DKI Jakarta
            temperature = round(float(np.random.normal(30.5, 2.5)), 1)
            humidity = round(float(np.random.normal(75.0, 8.0)), 1)
            humidity = max(10.0, min(100.0, humidity))
            
            wind_speed = round(float(np.random.exponential(2.0)), 1)
            # Sinkronisasi tipe data: Arah angin diubah menjadi float agar seragam dengan simulator
            wind_direction = round(float(np.random.uniform(0.0, 360.0)), 1)
            
            # Logika korelasi ilmiah: Cuaca panas & angin pelan memicu penumpukan polutan
            weather_modifier = (33.0 - temperature) * 2.0 + (78.0 - humidity) * 0.4 - wind_speed * 2.5
            
            pm25 = max(2, int(np.random.normal(40, 12) - weather_modifier))
            pm10 = max(5, int(pm25 * np.random.uniform(1.2, 1.5)))
            no2 = max(1, int(np.random.normal(22, 6) - weather_modifier * 0.2))
            co = max(0.1, round(float(np.random.normal(0.7, 0.2) - weather_modifier * 0.01), 2))
            o3 = max(2, int(np.random.normal(28, 10) + (temperature * 0.4)))
            
            # Injeksi anomali terstruktur 5% untuk melatih Isolation Forest di train_models.py
            is_anomaly = 0
            if np.random.rand() < 0.05:
                is_anomaly = 1
                anomaly_type = np.random.choice(['spike', 'zero_drop'])
                if anomaly_type == 'spike':
                    pm25 += int(np.random.uniform(100, 150))
                    pm10 += int(np.random.uniform(120, 180))
                    co += round(np.random.uniform(2.0, 4.0), 2)
                    print(f"[SIMULATOR ALERT] Injeksi Anomali 'Spike' pada Zone {zone_id} ({station_id})")
                elif anomaly_type == 'zero_drop':
                    pm25, pm10, no2, co, o3 = 0, 0, 0, 0.0, 0
                    print(f"[SIMULATOR ALERT] Injeksi Anomali 'Zero-Drop' (Sensor Mati) pada Zone {zone_id} ({station_id})")
                else:
                    # Kejadian anomali sensor mati / rusak total
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
    print(f"Menghasilkan total {len(df)} baris data matriks terurut di: {CSV_PATH}")

if __name__ == '__main__':
    generate_smart_city_data()