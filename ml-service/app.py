import pickle
import json
import threading
import os
import pika
import pandas as pd
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

app = FastAPI(title="Smart City ML Service", version="1.2")

# Absolute path routing
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(BASE_DIR, 'models', 'smartcity_models.pkl')

# Global variables with Pylance type-hinting
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
    print(f"Semua model ML berhasil dimuat dari {MODEL_PATH}")
    
    # Run the background consumer
    consumer_thread = threading.Thread(target=start_rabbitmq_consumer, daemon=True)
    consumer_thread.start()
    print("Background RabbitMQ Consumer diselaraskan dan dijalankan.")


# Pydantic Schemas for HTTP Endpoints

class PredictionFeatures(BaseModel):
    no2: float
    co: float
    o3: float
    temperature: float
    humidity: float
    wind_speed: float
    wind_direction: float

class AnomalyFeatures(BaseModel):
    pm25: float
    pm10: float
    no2: float
    co: float
    o3: float
    temperature: float
    humidity: float
    wind_speed: float


# HTTP Endpoints

@app.get("/health")
def health_check():
    if MODELS is not None:
        return {"status": "UP", "database": "N/A", "models_loaded": True}
    return {"status": "DOWN", "reason": "Models not ready"}

@app.post("/predict/aqi")
def predict_aqi(data: PredictionFeatures):
    models = MODELS
    if models is None:
        raise HTTPException(status_code=503, detail="Model belum siap dimuat ke memori")
        
    try:
        input_data = [[data.no2, data.co, data.o3, data.temperature, data.humidity, data.wind_speed, data.wind_direction]]
        scaled_data = models['scaler'].transform(input_data)
        prediction = models['aqi_classifier'].predict(scaled_data)[0]
        labels = {0: "Good", 1: "Moderate", 2: "Unhealthy"}
        return {"aqi_class": int(prediction), "label": labels[int(prediction)]}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/predict/pollution")
def predict_pollution(data: PredictionFeatures):
    models = MODELS
    if models is None:
        raise HTTPException(status_code=503, detail="Model belum siap dimuat ke memori")
        
    try:
        input_data = [[data.no2, data.co, data.o3, data.temperature, data.humidity, data.wind_speed, data.wind_direction]]
        scaled_data = models['scaler'].transform(input_data)
        predicted_pm25 = models['pollution_predictor'].predict(scaled_data)[0]
        return {"predicted_pm25": round(float(predicted_pm25), 2)}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/detect/anomaly")
def detect_anomaly(data: AnomalyFeatures):
    models = MODELS
    if models is None:
        raise HTTPException(status_code=503, detail="Model belum siap dimuat ke memori")
        
    try:
        input_data = [[data.pm25, data.pm10, data.no2, data.co, data.o3, data.temperature, data.humidity, data.wind_speed]]
        prediction = models['anomaly_detector'].predict(input_data)[0]
        is_anomaly = 1 if prediction == -1 else 0
        return {"is_anomaly": is_anomaly, "status": "Anomaly Detected" if is_anomaly else "Normal"}
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
            print(f"Menerima rilis event dari Anggota 3: {body.decode()}")
            
            models = MODELS
            if models is None:
                print("Consumer menunda eksekusi: Model belum termuat sempurna.")
                ch.basic_nack(delivery_tag=method.delivery_tag, requeue=True)
                return
                
            try:
                payload = json.loads(body.decode())
                data_block = payload.get('data', {})
                
                # 1. Ekstraksi Fitur Utama
                pm25 = float(data_block.get('pm25', 0))
                pm10 = float(data_block.get('pm10', 0))
                no2 = float(data_block.get('no2', 0))
                co = float(data_block.get('co', 0))
                o3 = float(data_block.get('o3', 0))
                
                # 2. Ekstraksi Fitur Cuaca dengan Nilai Standar Baseline Jakarta
                temp = float(data_block.get('temperature', 30.0))
                hum = float(data_block.get('humidity', 75.0))
                wind_s = float(data_block.get('wind_speed', 2.5))
                wind_d = float(data_block.get('wind_direction', 180.0))
                
                # 3. Eksekusi Prediksi Otomatis 1: AQI Class & Pollution Predictor
                clf_reg_input = [[no2, co, o3, temp, hum, wind_s, wind_d]]
                scaled_input = models['scaler'].transform(clf_reg_input)
                
                predicted_class = models['aqi_classifier'].predict(scaled_input)[0]
                predicted_pm25 = models['pollution_predictor'].predict(scaled_input)[0]
                
                labels = {0: "Good", 1: "Moderate", 2: "Unhealthy"}
                print(f"[AUTOMATED INFERENCE] Station {data_block.get('station_id')} -> Predicted PM2.5 next cycle: {predicted_pm25:.2f} | AQI Status: {labels[int(predicted_class)]}")
                
                # 4. Eksekusi Prediksi Otomatis 2: Isolation Forest Anomaly Detector
                anomaly_input = [[pm25, pm10, no2, co, o3, temp, hum, wind_s]]
                anomaly_res = models['anomaly_detector'].predict(anomaly_input)[0]
                
                # Jika hasil adalah -1, data dianggap sebagai pencilan/anomali struktural
                if anomaly_res == -1:
                    print("[ML ALERT] Deteksi Anomali Struktural Terbaca!")
                    
                    alert_payload = {
                        "event": "anomaly.alert",
                        "zone_id": data_block.get('zone_id'),
                        "station_id": data_block.get('station_id'),
                        "timestamp": pd.Timestamp.now().strftime('%Y-%m-%d %H:%M:%S'),
                        "details": f"Pencilan parameter terdeteksi! PM2.5: {pm25}, PM10: {pm10}.",
                        "trigger_data": data_block
                    }
                    
                    ch.basic_publish(
                        exchange=RABBITMQ_EXCHANGE,
                        routing_key='anomaly.alert',
                        body=json.dumps(alert_payload),
                        properties=pika.BasicProperties(delivery_mode=2)
                    )
                    print("Event 'anomaly.alert' sukses disebarkan ke broker.")
                    
            except Exception as ex:
                print(f"Gagal memproses struktur payload atau melakukan inferensi: {ex}")
                
            ch.basic_ack(delivery_tag=method.delivery_tag)

        channel.basic_consume(queue='air_quality_ml_queue', on_message_callback=callback)
        print(f"Menunggu event 'air.new' pada exchange '{RABBITMQ_EXCHANGE}'...")
        channel.start_consuming()
        
    except Exception as e:
        print(f"Koneksi RabbitMQ terputus atau gagal: {e}")

if __name__ == '__main__':
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=5000)