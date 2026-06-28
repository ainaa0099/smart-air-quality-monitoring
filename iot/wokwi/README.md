# Simulasi IoT Wokwi - Smart Air Quality Monitoring

Folder ini berisi simulasi perangkat IoT berbasis **ESP32** untuk mem-publish data kualitas udara dan cuaca ke MQTT topic proyek:

```text
city/zone1/airquality
city/zone1/weather
```

## Alat yang Dibutuhkan di Wokwi

Gunakan komponen berikut:

1. **ESP32 DevKit V1**  
   Mikrokontroler utama, konek WiFi dan publish MQTT.

2. **DHT22**  
   Simulasi sensor suhu dan kelembaban.

3. **Potentiometer PM2.5**  
   Simulasi nilai PM2.5.

4. **Potentiometer PM10**  
   Simulasi nilai PM10.

5. **Potentiometer NO2**  
   Simulasi nilai NO2.

6. **Potentiometer CO**  
   Simulasi nilai CO.

7. **Potentiometer O3**  
   Simulasi nilai O3.

8. **Potentiometer Wind Speed**  
   Simulasi kecepatan angin.

9. **Slide Switch / Dip Switch arah angin**  
   Simulasi arah angin sederhana. Di kode default arah angin akan dipilih dari nilai analog/random sederhana.

## File

```text
iot/wokwi/
├── README.md
├── sketch.ino
├── diagram.json
├── libraries.txt
└── wokwi-project.txt
```

## Cara Menjalankan di Wokwi

1. Buka [https://wokwi.com](https://wokwi.com)
2. Buat project baru: **ESP32 Arduino**
3. Copy isi `sketch.ino` ke editor Wokwi
4. Copy isi `diagram.json` ke tab `diagram.json`
5. Copy isi `libraries.txt` ke tab `libraries.txt`
6. Klik **Start Simulation**
7. Buka Serial Monitor, pastikan muncul log publish MQTT

## MQTT Broker

Default kode Wokwi memakai public broker:

```text
broker.hivemq.com
port 1883
```

Alasannya: Wokwi tidak bisa langsung mengakses `localhost` laptop kamu. Kalau kamu mau data Wokwi masuk ke Node-RED proyek lokal, ada dua pilihan:

### Opsi A - Node-RED ikut subscribe Public Broker

Ubah broker Node-RED dari:

```text
mosquitto
```

menjadi:

```text
broker.hivemq.com
```

Lalu topic tetap:

```text
city/+/airquality
city/+/weather
```

### Opsi B - Expose Mosquitto Lokal

Expose port MQTT lokal `1883` ke internet dengan tool seperti ngrok atau localtunnel TCP, lalu ganti:

```cpp
const char* MQTT_HOST = "broker.hivemq.com";
```

menjadi host publik dari tunnel kamu.

## Payload yang Dikirim

Air quality:

```json
{
  "zone_id": 1,
  "pm25": 48.2,
  "pm10": 72.1,
  "no2": 44.0,
  "co": 1900.0,
  "o3": 92.0,
  "timestamp": "2026-06-08T10:00:00+07:00",
  "device_id": "wokwi-zone1-air-01"
}
```

Weather:

```json
{
  "zone_id": 1,
  "temperature": 31.2,
  "humidity": 74.0,
  "wind_speed": 12.0,
  "wind_direction": "E",
  "pressure": 1010.0,
  "timestamp": "2026-06-08T10:00:00+07:00",
  "device_id": "wokwi-zone1-weather-01"
}
```

## Koneksi ke Project

Alur demo:

```text
Wokwi ESP32
→ MQTT broker
→ Node-RED subscribe city/+/airquality dan city/+/weather
→ POST /iot/airquality dan /iot/weather
→ API Gateway
→ PHP Air Quality / PHP Environment
→ MySQL
```
