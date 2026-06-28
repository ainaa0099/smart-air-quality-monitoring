# API Gateway

## Smart Air Quality Monitoring

API Gateway merupakan pintu masuk utama (single entry point) pada sistem **Smart Air Quality Monitoring**. Seluruh request dari client akan melewati Gateway sebelum diteruskan ke masing-masing microservice.

Gateway juga bertanggung jawab melakukan autentikasi token, pembatasan request (rate limiting), logging, monitoring, dan routing ke seluruh service yang tersedia.

---

## Teknologi

- Node.js
- Express.js
- JWT
- HTTP Proxy Middleware
- Express Rate Limit
- Morgan
- Prometheus Client
- Axios
- dotenv

---

## Fitur

- Reverse Proxy ke seluruh microservice
- JWT Authentication Middleware
- OAuth Token Validation
- Rate Limiting per IP
- Request Logging
- Health Check Endpoint
- Metrics Endpoint untuk Prometheus
- CORS Support

---

## Struktur Folder

```
gateway/
├── src/
│   └── gateway.js
├── .env.example
├── Dockerfile
├── package.json
└── package-lock.json
```

---

## Instalasi

Install dependency:

```bash
npm install
```

Jalankan Gateway:

```bash
npm run dev
```

atau

```bash
npm start
```

---

## Environment Variable

Buat file `.env` berdasarkan `.env.example`.

Contoh konfigurasi:

```env
PORT=3000

AUTH_SERVICE_URL=http://localhost:3002
CITIZEN_SERVICE_URL=http://localhost:8000
AIRQUALITY_SERVICE_URL=http://localhost:8001
ENVIRONMENT_SERVICE_URL=http://localhost:8002
ML_SERVICE_URL=http://localhost:5000

JWT_SECRET=your-secret-key
```

---

## Endpoint

### Authentication

Semua request API akan melalui Gateway dan dilakukan validasi JWT sebelum diteruskan ke service terkait.

---

### Health Check

```
GET /health
```

Digunakan untuk mengecek status API Gateway.

---

### Metrics

```
GET /metrics
```

Digunakan oleh Prometheus untuk monitoring.

---

### Routing

Gateway meneruskan request ke beberapa service berikut.

| Service | Endpoint |
|----------|----------|
| OAuth Server | `/oauth/*` |
| Citizen Service | `/api/citizens/*` |
| Citizen Reports | `/api/reports/*` |
| Citizen Notifications | `/api/notifications/*` |
| Air Quality Service | `/api/airquality/*` |
| Environment Service | `/api/environment/*` |
| ML Service | `/predict/*`, `/detect/*` |

---

## Peran Gateway dalam Arsitektur

Gateway berfungsi sebagai penghubung antara client dan seluruh microservice.

```
Client
   │
   ▼
API Gateway
   │
   ├── OAuth Server
   ├── Citizen Service
   ├── Air Quality Service
   ├── Environment Service
   └── Python ML Service
```

---

## Monitoring

Gateway menyediakan endpoint `/metrics` yang digunakan oleh Prometheus untuk mengumpulkan metrik aplikasi. Data tersebut kemudian divisualisasikan melalui Grafana.

---

## Docker

Gateway dijalankan menggunakan Docker dan diorkestrasi melalui `docker-compose.yml` bersama seluruh komponen Smart Air Quality Monitoring.