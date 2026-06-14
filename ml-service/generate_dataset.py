import pandas as pd
import numpy as np
import os
from datetime import datetime, timedelta

def generate_smart_city_data(num_rows=5500):
    np.random.seed(42)
    
    # 5 Jakarta Zones
    zones = ['Jakarta Pusat', 'Jakarta Utara', 'Jakarta Timur', 'Jakarta Selatan', 'Jakarta Barat']
    
    # Generate base timestamps starting from today backwards
    base_time = datetime.now()
    timestamps = [base_time - timedelta(minutes=30 * i) for i in range(num_rows)]
    
    data = []
    for i in range(num_rows):
        zone = np.random.choice(zones)
        timestamp = timestamps[i].strftime('%Y-%m-%d %H:%M:%S')
        
        # Weather variables (Suhu, Kelembaban, Angin)
        # Jakarta is hot and humid
        temperature = round(float(np.random.normal(30, 3)), 1)     # 24°C to 36°C approx
        humidity = round(float(np.random.normal(75, 10)), i % 2)   # 50% to 95% approx
        humidity = max(10, min(100, humidity))
        
        wind_speed = round(float(np.random.exponential(2.5)), 1)   # Low wind speeds are common
        wind_direction = int(np.random.uniform(0, 360))            # 0-360 degrees
        
        # Pollutant variables (Correlated with weather: low wind + high temp = high pollution)
        weather_modifier = (35 - temperature) * 2 + (80 - humidity) * 0.5 - wind_speed * 3
        
        pm25 = max(5, int(np.random.normal(45, 15) - weather_modifier))
        pm10 = max(10, int(pm25 * np.random.uniform(1.2, 1.8)))
        no2 = max(2, int(np.random.normal(20, 8) - weather_modifier * 0.3))
        co = max(0.1, round(float(np.random.normal(0.8, 0.3) - weather_modifier * 0.01), 2))
        o3 = max(5, int(np.random.normal(30, 12) + (temperature * 0.5))) # O3 increases with sunlight/temp
        
        # Inject deterministic logic for Anomaly Detection evaluation (approx 5% anomalies)
        is_anomaly = 0
        if np.random.rand() < 0.05:
            is_anomaly = 1
            # Spike random features to simulate sensor fault or extreme event
            anomaly_type = np.random.choice(['spike', 'zero_drop'])
            if anomaly_type == 'spike':
                pm25 += 120
                pm10 += 150
                co += 3.5
            else:
                pm25, pm10, no2, co, o3 = 0, 0, 0, 0.0, 0
                
        data.append([
            timestamp, zone, pm25, pm10, no2, co, o3, 
            temperature, humidity, wind_speed, wind_direction, is_anomaly
        ])
        
    columns = [
        'timestamp', 'zone', 'pm25', 'pm10', 'no2', 'co', 'o3', 
        'temperature', 'humidity', 'wind_speed', 'wind_direction', 'ground_truth_anomaly'
    ]
    
    df = pd.DataFrame(data, columns=columns)
    
    # Save output
    os.makedirs('data', exist_ok=True)
    df.to_csv('data/smartcity_telemetry.csv', index=False)
    print(f"Successfully generated {num_rows} rows of data at 'data/smartcity_telemetry.csv'")

if __name__ == '__main__':
    generate_smart_city_data()