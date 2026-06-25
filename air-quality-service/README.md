# Air Quality Service

Microservice PHP 8.2 MVC pada port `8001`. Service memakai database bersama
`smartcity`, menghubungkan data melalui `zone_id`, dan menerbitkan event
RabbitMQ `air.new` ke topic exchange `city.events`.

## Endpoint

- `POST /api/airquality/readings`
- `GET /api/airquality/current?zone_id=1`
- `GET /api/airquality/history?zone_id=1&from=2026-06-01&to=2026-06-30&page=1&limit=100`
- `GET /api/airquality/stations`
- `POST /iot/airquality`
- `GET /health`

Contoh payload pembacaan:

```json
{
  "station_id": 1,
  "zone_id": 1,
  "pm25": 35.4,
  "pm10": 72.1,
  "no2": 44.2,
  "co": 2.1,
  "o3": 80.0,
  "recorded_at": "2026-06-13T10:00:00+07:00"
}
```

Jalankan `composer install`, salin `.env.example` menjadi `.env`, impor
`database/schema.sql` dan `database/seed.sql`, lalu:

```bash
php -S 0.0.0.0:8001 -t public public/index.php
```
