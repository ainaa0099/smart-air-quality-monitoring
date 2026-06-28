# Tugas Besar UAS - Pembangunan Perangkat Lunak Orientasi Berbasis Service

## Smart Air Quality Monitoring - Air Quality Service

Repository ini dibuat untuk memenuhi Tugas Besar UAS mata kuliah **Pembangunan Perangkat Lunak Orientasi Berbasis Service**. Sistem yang dikembangkan adalah **Smart Air Quality Monitoring**, yaitu sistem pemantauan kualitas udara berbasis arsitektur microservices.

Bagian yang didokumentasikan pada repository ini adalah **Air Quality Service**. Service ini dibangun dengan PHP 8.2 untuk menerima data sensor kualitas udara, menghitung nilai AQI, menyimpan data pembacaan ke MySQL, dan mengirim event `air.new` melalui RabbitMQ untuk kebutuhan integrasi antar-layanan.

Service ini berjalan pada port `8001` dan dapat dipanggil langsung atau lewat API Gateway pada sistem gabungan.

---

## Detail Kelompok

- **Mata Kuliah:** Pembangunan Perangkat Lunak Orientasi Berbasis Service
- **Dosen Pengampu:** Muhammad Panji Muslim S.Pd., M.Com.
- **Program Studi:** S1 Informatika
- **Nama Kelompok:** Kelompok 4

## Anggota Kelompok & Pembagian Tugas

| Nama Anggota | Peran & Tanggung Jawab | Deskripsi Pekerjaan |
| --- | --- | --- |
| Muhammad Fahmi Idrus | API Gateway, OAuth Server, Monitoring, dan CI/CD | Membangun API Gateway Express.js pada port `3000`, OAuth Server pada port `3002`, JWT middleware, routing antar-service, rate limiting, request logging, health aggregator, endpoint OAuth, metrics, workflow GitHub Actions, dan konfigurasi monitoring. |
| Aina Annisa | Citizen Service dan Database Schema | Membuat Citizen Service PHP 8.2 MVC pada port `8000`, controller warga/laporan/notifikasi, model database, validasi input, publish event `report.submitted`, serta menyusun `database/schema.sql` dan `database/seed.sql` untuk data awal sistem. |
| Akbar Fitri Andhika | Air Quality Service dan Kubernetes Manifest | Membuat Air Quality Service PHP 8.2 MVC pada port `8001`, controller kualitas udara/stasiun/reading, model `AirReading` dan `AirStation`, penerimaan data IoT, penyimpanan data sensor, publish event `air.new`, health check database, serta manifest Kubernetes untuk deployment sistem. |
| Andharu Utomo | Environment Service, RabbitMQ Consumer, dan Docker Compose | Membuat Environment Service PHP 8.2 MVC pada port `8002`, controller cuaca/alert/zona, model environment, publish event `weather.new`, consumer RabbitMQ untuk `anomaly.alert`, serta `docker-compose.yml` lengkap untuk orkestrasi MySQL, RabbitMQ, Mosquitto, gateway, service PHP, Python ML, Node-RED, Prometheus, dan Grafana. |
| Rafdi Nur Zhafir Rahman | Python ML Service, IoT Simulator, dan Node-RED Bridge | Membuat dataset sintetis, notebook EDA, model AQI classifier/pollution predictor/anomaly detector, FastAPI ML service pada port `5000`, RabbitMQ consumer untuk event `air.new`, publish `anomaly.alert`, simulator IoT berbasis MQTT, serta flow Node-RED untuk bridge data air quality dan weather ke gateway. |

---

## Gambaran Sistem

Smart Air Quality Monitoring dirancang sebagai sistem monitoring kualitas udara untuk beberapa zona kota. Data sensor dikirim melalui jalur IoT atau API Gateway, lalu diproses oleh Air Quality Service untuk menghasilkan nilai AQI yang dapat digunakan oleh dashboard, ML service, atau service lain.

Alur sederhana:

```text
Sensor / IoT
   |
   v
API Gateway atau /iot/airquality
   |
   v
Air Quality Service
   |-- Simpan reading ke MySQL
   |-- Hitung AQI dan kategori udara
   `-- Publish event air.new ke RabbitMQ
          |
          v
   Service lain / ML Service / Monitoring
```

---

## Ringkasan Service

| Area | Keterangan |
| --- | --- |
| Nama service | `air-quality-service` |
| Bahasa utama | PHP 8.2 |
| Database | MySQL / MariaDB, database `smartcity` |
| Message broker | RabbitMQ topic exchange `city.events` |
| Port service | `8001` |
| Output utama | Data AQI terkini, riwayat AQI, daftar stasiun, dan event `air.new` |

Fungsi utama service:

- Menerima data sensor dari endpoint gateway atau endpoint IoT.
- Memvalidasi payload pembacaan kualitas udara.
- Menghitung AQI dari `pm25`, `pm10`, `no2`, `co`, dan `o3`.
- Menentukan kategori AQI dan polutan dominan.
- Menyimpan data zona, stasiun, dan pembacaan udara.
- Menyediakan endpoint untuk data terbaru dan histori per zona.
- Menerbitkan event RabbitMQ agar data baru dapat dipakai service lain, misalnya ML service.

---

## Komponen Pengerjaan

| Komponen | Peran dalam service |
| --- | --- |
| Controller API | Mengatur request untuk reading, current AQI, history, station list, dan health check |
| Model database | Menjalankan query ke tabel `zones`, `air_stations`, dan `air_readings` |
| AQI Calculator | Menghitung indeks AQI memakai breakpoint polutan |
| Validator | Memastikan payload sensor lengkap dan valid |
| RabbitMQ Publisher | Mengirim event `air.new` setelah data berhasil disimpan |
| Dockerfile | Menyiapkan image PHP 8.2 CLI dengan ekstensi `pdo_mysql` |

---

## Struktur Folder

```text
air-quality-service/
|-- app/                         # Source code utama service
|   |-- Config/Database.php       # Koneksi PDO ke MySQL
|   |-- Controllers/              # Endpoint handler
|   |-- Core/Response.php         # Format response JSON
|   |-- Models/                   # Query data air quality
|   |-- Services/                 # AQI calculator dan RabbitMQ publisher
|   `-- Validators/               # Validasi payload sensor
|-- database/
|   |-- schema.sql                # Tabel zones, air_stations, air_readings
|   `-- seed.sql                  # Data awal 5 zona dan 5 stasiun Jakarta
|-- public/index.php              # Entry point dan router sederhana
|-- Dockerfile                    # Build image service
|-- composer.json                 # Dependency PHP
|-- .env.example                  # Contoh konfigurasi environment
`-- README.md                     # Dokumentasi service
```

---

## Prasyarat

### Opsi 1: Docker Compose (untuk sistem gabungan)

- Docker
- Docker Compose
- File compose sistem utama yang memuat service `airquality-service`, `mysql`, dan `rabbitmq`

### Opsi 2: Docker Service Tunggal

- Docker
- File `.env`
- MySQL dan RabbitMQ yang dapat diakses dari container

### Opsi 3: Manual tanpa container

- PHP 8.2 atau lebih baru
- Composer
- MySQL atau MariaDB
- RabbitMQ, opsional untuk publish event
- Git, opsional untuk clone repository

---

## Konfigurasi Environment

Buat file `.env` dari template:

```bash
cp .env.example .env
```

Contoh konfigurasi lokal:

```env
APP_ENV=development
APP_PORT=8001

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=smartcity
DB_USER=root
DB_PASS=

RABBITMQ_HOST=127.0.0.1
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASS=guest
RABBITMQ_VHOST=/
RABBITMQ_EXCHANGE=city.events
```

Jika dijalankan lewat Docker Compose, biasanya `DB_HOST` memakai nama service `mysql` dan `RABBITMQ_HOST` memakai nama service `rabbitmq`.

---

## Cara Menjalankan Service Manual

### 1. Install dependency PHP

```bash
composer install
```

### 2. Setup database

Import schema dan seed data:

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p smartcity < database/seed.sql
```

File `seed.sql` akan mengisi 5 zona Jakarta dan 5 stasiun kualitas udara.

### 3. Jalankan server lokal

```bash
php -S 0.0.0.0:8001 -t public public/index.php
```

### 4. Cek status service

```bash
curl http://localhost:8001/health
```

---

## Menjalankan dengan Docker

Build image:

```bash
docker build -t air-quality-service .
```

Run container:

```bash
docker run --rm -p 8001:8001 --env-file .env air-quality-service
```

Untuk mode sistem penuh, service ini dapat dijalankan dari Docker Compose utama bersama `mysql`, `rabbitmq`, `api-gateway`, `python-ml`, dan service pendukung lain.

Jika berada di root project gabungan yang sudah memiliki `docker-compose.yml`, jalankan:

```bash
docker compose up -d --build
```

Untuk melihat log service:

```bash
docker compose logs -f airquality-service
```

Untuk mematikan seluruh container:

```bash
docker compose down
```

---

## Setup Database dan Seed Data

Database yang digunakan bernama `smartcity`.

| Tabel | Fungsi |
| --- | --- |
| `zones` | Master wilayah/zona kota |
| `air_stations` | Master stasiun sensor kualitas udara |
| `air_readings` | Data pembacaan sensor beserta hasil AQI |

Relasi data:

- `air_stations.zone_id` terhubung ke `zones.id`.
- `air_readings.zone_id` terhubung ke `zones.id`.
- `air_readings.station_id` terhubung ke `air_stations.id`.

Jika terjadi error foreign key saat insert reading, pastikan `database/seed.sql` sudah dijalankan.

---

## Endpoint Utama

Endpoint berikut dapat dipanggil langsung ke service pada port `8001`. Pada sistem gabungan, path `/api/airquality/*` juga dapat diteruskan melalui API Gateway.

| Method | Path | Service | Keterangan |
| --- | --- | --- | --- |
| `GET` | `/health` | air-quality-service | Mengecek koneksi database dan status service |
| `POST` | `/api/airquality/readings` | air-quality-service | Menyimpan data pembacaan kualitas udara |
| `POST` | `/iot/airquality` | air-quality-service | Endpoint alternatif untuk integrasi IoT |
| `GET` | `/api/airquality/current` | air-quality-service | Mengambil data AQI terbaru semua zona |
| `GET` | `/api/airquality/current?zone_id=1` | air-quality-service | Mengambil data AQI terbaru untuk satu zona |
| `GET` | `/api/airquality/history` | air-quality-service | Mengambil riwayat pembacaan udara |
| `GET` | `/api/airquality/stations` | air-quality-service | Mengambil daftar stasiun kualitas udara |

---

## Contoh Request

### Simpan data sensor

```bash
curl -X POST http://localhost:8001/api/airquality/readings \
  -H "Content-Type: application/json" \
  -d '{
    "station_id": 1,
    "zone_id": 1,
    "pm25": 35.4,
    "pm10": 72.1,
    "no2": 44.2,
    "co": 2.1,
    "o3": 80.0,
    "recorded_at": "2026-06-13T10:00:00+07:00"
  }'
```

Field wajib:

- `zone_id`
- `pm25`
- `pm10`
- `no2`
- `co`
- `o3`

Field opsional:

- `station_id`
- `recorded_at`

### Ambil data terbaru

```bash
curl "http://localhost:8001/api/airquality/current?zone_id=1"
```

### Ambil riwayat data

```bash
curl "http://localhost:8001/api/airquality/history?zone_id=1&from=2026-06-01&to=2026-06-30&page=1&limit=100"
```

Parameter riwayat:

| Parameter | Default | Keterangan |
| --- | --- | --- |
| `zone_id` | semua zona | Filter zona |
| `from` | kosong | Tanggal awal |
| `to` | kosong | Tanggal akhir |
| `page` | `1` | Nomor halaman |
| `limit` | `100` | Jumlah data per halaman, maksimal `500` |

---

## Format Response API

Semua response dikembalikan dalam format JSON berikut:

```json
{
  "status": "success",
  "code": 200,
  "data": {},
  "message": "Success",
  "timestamp": "2026-06-28T00:00:00+00:00",
  "service": "air-quality-service"
}
```

Pada response penyimpanan reading, data akan dilengkapi dengan:

- `aqi_value`
- `aqi_category`
- `dominant_pollutant`
- `zone_name`
- `station_name`
- `event_published`

---

## Event RabbitMQ

Setelah data sensor berhasil disimpan, service mencoba mengirim event:

| Properti | Nilai |
| --- | --- |
| Exchange | `city.events` |
| Exchange type | `topic` |
| Routing key | `air.new` |
| Content type | `application/json` |

Contoh message:

```json
{
  "event": "air.new",
  "occurred_at": "2026-06-28T00:00:00+00:00",
  "data": {
    "id": 1,
    "zone_id": 1,
    "aqi_value": 88,
    "aqi_category": "Sedang",
    "dominant_pollutant": "pm10"
  }
}
```

Jika RabbitMQ sedang tidak tersedia, data tetap tersimpan ke database dan response akan berisi `event_published: false`.

---

## Integrasi IoT dan ML

Endpoint `/iot/airquality` mendukung payload langsung maupun payload yang dibungkus dalam key `data`.

```json
{
  "data": {
    "station_id": 101,
    "zone_id": 1,
    "pm25": 35.4,
    "pm10": 72.1,
    "no2": 44.2,
    "co": 2.1,
    "o3": 80.0
  }
}
```

Untuk kompatibilitas simulator ML/IoT, `station_id` bernilai `101` sampai `105` otomatis dipetakan menjadi `1` sampai `5`.

---

## Validasi dan Troubleshooting

Aturan validasi utama:

- Nilai polutan wajib ada, harus numerik, dan tidak boleh negatif.
- `zone_id` harus integer positif.
- `station_id` harus integer jika dikirim.
- `recorded_at` harus berupa tanggal valid jika dikirim.

Masalah umum:

| Masalah | Solusi |
| --- | --- |
| `/health` menampilkan database disconnected | Periksa konfigurasi `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, dan `DB_PASS` |
| `event_published` bernilai `false` | Pastikan RabbitMQ aktif dan konfigurasi `RABBITMQ_*` benar |
| Insert reading gagal karena foreign key | Jalankan `database/schema.sql` dan `database/seed.sql` |
| Endpoint tidak ditemukan | Pastikan path memakai prefix `/api/airquality` atau `/iot/airquality` |
