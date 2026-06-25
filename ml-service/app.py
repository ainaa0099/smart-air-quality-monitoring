import pickle
import json
import threading
import os
import pika
from datetime import datetime
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

app = FastAPI(title="Smart City ML Service", version="1.3")

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(BASE_DIR, 'models', 'smartcity_models.pkl')

# Global variables dengan penanganan typing eksplisit demi Pylance
MODELS: dict | None = None
RABBITMQ_HOST = os.getenv("RABBITMQ_HOST", "localhost")
RABBITMQ_EXCHANGE = os.getenv("RABBITMQ_EXCHANGE", "city.events") 

@app.on_event("startup")
def load_models_and_start_consumer():
    global MODELS
    if not os.path.exists(MODEL_PATH):
        raise RuntimeError(f"File model tidak ditemukan di {MODEL_PATH}. Jalankan train_models.py terlebih dahulu.")
        
    with open(MODEL_PATH, "rb") as f:
        MODELS = pickle.load(f)
    print("SINKRONISASI SUKSES: 3 Model ML Arsitektur SOA Berhasil Dimuat.")
    
    # Menjalankan consumer di thread terpisah agar tidak memblokir event loop FastAPI
    consumer_thread = threading.Thread(target=start_rabbitmq_consumer, daemon=True)
    consumer_thread.start()

# SKEMA PYDANTIC

class AQIRequest(BaseModel):
    pm25: float
    pm10: float
    no2: float
    co: float
    o3: float
    temperature: float
    humidity: float

class PollutionRequest(BaseModel):
    hour: int
    day: int
    wind_speed: float
    wind_direction: float
    temperature: float
    humidity: float
    pm25_prev: float

class AnomalyRequest(BaseModel):
    sensor_value: float
    hour: int
    rolling_mean_1h: float
    z_score: float


# HTTP Endpoints

@app.get("/health")
def health_check():
    if MODELS is not None:
        return {"status": "UP", "models_loaded": True}
    return {"status": "DOWN", "reason": "Model artifacts are missing"}

@app.post("/predict/aqi")
def predict_aqi(data: AQIRequest):
    models = MODELS
    if models is None:
        raise HTTPException(status_code=503, detail="Model belum siap di memori")
    try:
        input_data = [[data.pm25, data.pm10, data.no2, data.co, data.o3, data.temperature, data.humidity]]
        scaled_data = models['scaler_clf'].transform(input_data)
        pred = models['aqi_classifier'].predict(scaled_data)[0]
        
        # 5 Kategori Output Resmi Indonesia sesuai spesifikasi dokumen tugas
        labels = {0: "Baik", 1: "Sedang", 2: "Tidak Sehat", 3: "Sangat Tidak Sehat", 4: "Berbahaya"}
        return {"aqi_class": int(pred), "label": labels.get(int(pred), "Tidak Diketahui")}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/predict/pollution")
def predict_pollution(data: PollutionRequest):
    models = MODELS
    if models is None:
        raise HTTPException(status_code=503, detail="Model belum siap di memori")
    try:
        input_data = [[data.hour, data.day, data.wind_speed, data.wind_direction, data.temperature, data.humidity, data.pm25_prev]]
        scaled_data = models['scaler_reg'].transform(input_data)
        pred = models['pollution_predictor'].predict(scaled_data)[0]
        return {"predicted_pm25": round(float(pred), 2)}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/detect/anomaly")
def detect_anomaly(data: AnomalyRequest):
    models = MODELS
    if models is None:
        raise HTTPException(status_code=503, detail="Model belum siap di memori")
    try:
        input_data = [[data.sensor_value, data.hour, data.rolling_mean_1h, data.z_score]]
        pred = models['anomaly_detector'].predict(input_data)[0]
        is_anom = True if pred == -1 else False
        
        # Aturan penentuan tingkatan bahaya (severity) berbasis deviasi z-score
        severity = "Normal"
        if is_anom:
            severity = "Peringatan" if data.z_score < 3.0 else "Kritis"
            
        return {"is_anomaly": is_anom, "severity": severity}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


# RabbitMQ Consumer Engine (Fully Automated Inference)

def start_rabbitmq_consumer():
    try:
        connection = pika.BlockingConnection(pika.ConnectionParameters(host=RABBITMQ_HOST))
        channel = connection.channel()
        
        channel.exchange_declare(exchange=RABBITMQ_EXCHANGE, exchange_type='topic', durable=True)
        channel.queue_declare(queue='air_quality_ml_queue', durable=True)
        channel.queue_bind(exchange=RABBITMQ_EXCHANGE, queue='air_quality_ml_queue', routing_key='air.new')
        
        def callback(ch, method, properties, body):
            models = MODELS
            if models is None:
                ch.basic_nack(delivery_tag=method.delivery_tag, requeue=True)
                return
                
            try:
                payload = json.loads(body.decode())
                data_block = payload.get('data', {})  # Representasi tabel air_readings
                
                zone_id = data_block.get('zone_id')
                pm25 = float(data_block.get('pm25', 0))
                pm10 = float(data_block.get('pm10', 0))
                no2 = float(data_block.get('no2', 0))
                co = float(data_block.get('co', 0))
                o3 = float(data_block.get('o3', 0))
                
                # Sinkronisasi Fallback Nilai Cuaca dari Database Terpisah
                temp_fallback = 30.0
                hum_fallback = 75.0
                
                # 1. Otomatisasi Klasifikasi Kategori AQI
                clf_input = [[pm25, pm10, no2, co, o3, temp_fallback, hum_fallback]]
                scaled_clf = models['scaler_clf'].transform(clf_input)
                aqi_pred = models['aqi_classifier'].predict(scaled_clf)[0]
                
                # 2. Otomatisasi Analisis Pencilan Melalui Isolation Forest
                now = datetime.now()
                # Dummy statistik instan demi kestabilan background process real-time
                anom_input = [[pm25, now.hour, pm25, 0.0]] 
                anom_pred = models['anomaly_detector'].predict(anom_input)[0]
                
                if anom_pred == -1:
                    # Skema Event Outbound resmi untuk dikonsumsi Citizen Service & Env Service
                    alert_payload = {
                        "event": "anomaly.alert",
                        "zone_id": zone_id,
                        "pollutant": "PM2.5",
                        "severity": "Peringatan" if pm25 < 150 else "Kritis",
                        "value": pm25,
                        "threshold": 100.0,
                        "created_at": now.strftime('%Y-%m-%d %H:%M:%S')
                    }
                    
                    channel.basic_publish(
                        exchange=RABBITMQ_EXCHANGE,
                        routing_key='anomaly.alert',
                        body=json.dumps(alert_payload),
                        properties=pika.BasicProperties(delivery_mode=2)
                    )
                    print(f"[AUTOMATION ALERT] Anomali terdeteksi di Zone {zone_id}! Klasifikasi Kategori AQI: {int(aqi_pred)}")
                    
            except Exception as e:
                print(f"Gagal mengeksekusi otomatisasi inferensi latar belakang: {e}")
                
            ch.basic_ack(delivery_tag=method.delivery_tag)

        channel.basic_consume(queue='air_quality_ml_queue', on_message_callback=callback)
        channel.start_consuming()
    except Exception as e:
        print(f"Koneksi RabbitMQ terputus atau gagal: {e}")

if __name__ == '__main__':
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=5000)