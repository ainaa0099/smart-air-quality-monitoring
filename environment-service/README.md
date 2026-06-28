# Environment Service

## Smart Air Quality Monitoring

Environment Service merupakan salah satu microservice pada sistem **Smart Air Quality Monitoring** yang bertanggung jawab mengelola informasi cuaca, peringatan lingkungan, serta data zona yang digunakan bersama oleh seluruh service.

Selain menyediakan REST API, service ini juga bertindak sebagai **RabbitMQ Consumer** yang menerima event dari Python ML Service untuk menghasilkan notifikasi kondisi lingkungan secara otomatis.

---

# Teknologi

* PHP 8.2
* MVC Architecture
* MySQL
* RabbitMQ
* Composer
* Docker

---

# Fitur

* Manajemen data cuaca
* Manajemen data peringatan lingkungan
* Manajemen data zona
* Menampilkan kondisi cuaca terkini
* Menampilkan daftar alert lingkungan
* Menyediakan daftar seluruh zona Jakarta
* RabbitMQ Consumer untuk menerima event `anomaly.alert`
* Health Check Database

---

# Struktur Folder

```
environment-service/
├── app/
│   ├── Config/
│   ├── Controllers/
│   │   ├── WeatherController.php
│   │   ├── AlertController.php
│   │   └── ZoneController.php
│   └── Models/
│       ├── Weather.php
│       ├── Alert.php
│       └── Zone.php
│
├── database/
│   ├── schema.sql
│   └── seed.sql
│
├── public/
│   └── index.php
│
├── consumer.php
├── Dockerfile
├── composer.json
└── .env.example
```

---

# Instalasi

Install dependency menggunakan Composer.

```bash
composer install
```

Jalankan web server.

```bash
php -S localhost:8002 -t public
```

Jalankan RabbitMQ Consumer.

```bash
php consumer.php
```

---

# Environment Variable

Salin file `.env.example` menjadi `.env` kemudian sesuaikan konfigurasi.

Contoh:

```env
APP_PORT=8002

DB_HOST=mysql
DB_PORT=3306
DB_NAME=smartcity
DB_USER=root
DB_PASSWORD=password

RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
```

---

# Endpoint

## Weather

| Method | Endpoint                   | Deskripsi             |
| ------ | -------------------------- | --------------------- |
| GET    | `/api/environment/weather` | Daftar data cuaca     |
| GET    | `/api/environment/current` | Kondisi cuaca terbaru |

---

## Alert

| Method | Endpoint                  | Deskripsi               |
| ------ | ------------------------- | ----------------------- |
| GET    | `/api/environment/alerts` | Daftar alert lingkungan |

---

## Zone

| Method | Endpoint                      | Deskripsi           |
| ------ | ----------------------------- | ------------------- |
| GET    | `/api/environment/zones`      | Daftar seluruh zona |
| GET    | `/api/environment/zones/{id}` | Detail zona         |

---

## Health Check

| Method | Endpoint  |
| ------ | --------- |
| GET    | `/health` |

Digunakan untuk memastikan koneksi database berjalan dengan baik.

---

# RabbitMQ Consumer

Environment Service menerima event dari Python ML Service melalui RabbitMQ.

### Queue

```
anomaly.alert
```

Consumer akan:

* menerima hasil deteksi anomali,
* membuat alert lingkungan,
* memperbarui status zona,
* menghasilkan notifikasi yang dapat ditampilkan kepada pengguna.

---

# Database

Environment Service menggunakan beberapa tabel berikut.

* env_weather
* env_alerts
* env_zone_status
* zones (shared table)

---

# Docker

Service dijalankan menggunakan Docker dan diorkestrasi melalui `docker-compose.yml`.

Build image:

```bash
docker build -t environment-service .
```

Menjalankan container:

```bash
docker compose up environment-service
```

---

# Peran dalam Arsitektur

```
Python ML Service
        │
        │ anomaly.alert
        ▼
RabbitMQ
        │
        ▼
Environment Service
        │
        ├── env_alerts
        ├── env_zone_status
        └── REST API
```

Environment Service berperan sebagai penyedia informasi lingkungan dan sebagai penerima event anomali yang dihasilkan oleh Python ML Service sehingga sistem mampu memberikan informasi kondisi lingkungan secara real-time.