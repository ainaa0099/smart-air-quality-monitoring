# ML Service - Smart Air Quality Monitoring

## Deskripsi

ML Service merupakan layanan berbasis **Python** dan **FastAPI** yang bertugas melakukan Machine Learning pada sistem Smart Air Quality Monitoring. Service ini menerima data kualitas udara melalui RabbitMQ, melakukan prediksi menggunakan model Machine Learning, mendeteksi anomali, serta menyediakan API untuk inferensi model.

Selain itu, project ini juga menyediakan **IoT Simulator** yang mengirimkan data sensor kualitas udara dan cuaca ke MQTT Broker sebagai simulasi perangkat IoT.

---

## Teknologi

* Python 3.12
* FastAPI
* scikit-learn
* Pandas
* NumPy
* RabbitMQ (Pika)
* MQTT (Paho MQTT)

---

## Struktur Folder

```text
ml-service/
│── app.py
│── simulator.py
│── generate_dataset.py
│── train_models.py
│── requirements.txt
│── Dockerfile
│── models/
│    └── smartcity_models.pkl
│── data/
│    └── smartcity_telemetry.csv
```

---

## Model Machine Learning

Service ini menggunakan tiga model Machine Learning:

1. **AQI Classifier**

   * Algoritma: Gradient Boosting Classifier
   * Output: Kategori AQI

2. **Pollution Predictor**

   * Algoritma: Random Forest Regressor
   * Output: Prediksi nilai PM2.5

3. **Anomaly Detector**

   * Algoritma: Isolation Forest
   * Output: Status anomali, anomaly score, dan severity

---

## Endpoint API

| Method | Endpoint             | Fungsi                |
| ------ | -------------------- | --------------------- |
| GET    | `/health`            | Health Check          |
| POST   | `/predict/aqi`       | Prediksi kategori AQI |
| POST   | `/predict/pollution` | Prediksi PM2.5        |
| POST   | `/detect/anomaly`    | Deteksi anomali       |

Service berjalan pada **port 5000**.

---

## RabbitMQ

### Consumer

Routing Key:

```text
air.new
```

### Publisher

Routing Key:

```text
anomaly.alert
```

---

## MQTT Simulator

Simulator mengirimkan data sensor setiap 30 detik ke MQTT Broker menggunakan topic:

```text
city/zone1/airquality
...
city/zone5/airquality

city/zone1/weather
...
city/zone5/weather
```

---

## Menjalankan Project

### 1. Install dependency

```bash
pip install -r requirements.txt
```

### 2. Generate dataset sintetis

```bash
python generate_dataset.py
```

### 3. Latih model Machine Learning

```bash
python train_models.py
```

Proses ini akan menghasilkan file model:

```text
models/smartcity_models.pkl
```

### 4. Jalankan ML Service

```bash
python app.py
```

Service akan berjalan pada:

```text
http://localhost:5000
```

### 5. Jalankan IoT Simulator (opsional)

```bash
python simulator.py
```

Simulator akan mengirim data sensor kualitas udara dan cuaca ke MQTT Broker secara berkala.

Catatan: File models/smartcity_models.pkl sudah disertakan pada repository. Langkah generate dataset dan training model hanya diperlukan apabila ingin melatih ulang model dari awal.

## Environment Variable

```env
RABBITMQ_HOST=localhost
RABBITMQ_EXCHANGE=city.events

MQTT_HOST=localhost
MQTT_PORT=1883
```

---

## Catatan

Project ini merupakan bagian dari sistem **Smart Air Quality Monitoring** berbasis arsitektur **Microservices (SOA)** yang terintegrasi dengan API Gateway, RabbitMQ, MQTT, Node-RED, dan beberapa layanan PHP lainnya.