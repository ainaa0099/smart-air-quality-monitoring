<?php

namespace App\Controllers;

use App\Core\Response;
use App\Models\AirReading;
use App\Services\AQICalculator;
use App\Services\RabbitMQPublisher;
use App\Validators\ReadingValidator;
use Throwable;

// Controller untuk menyimpan data kualitas udara
final class ReadingController
{
    public function store(array $input): never
    {
        // Beberapa flow IoT membungkus payload sensor di dalam key "data".
        $input = isset($input['data']) && is_array($input['data']) ? $input['data'] : $input;

        if (isset($input['timestamp']) && !isset($input['recorded_at'])) {
            $input['recorded_at'] = $input['timestamp'];
        }

        // Simulator anggota ML
        if (isset($input['station_id'])) { $input['station_id'] = (int) $input['station_id'];
        }

        $errors = ReadingValidator::validate($input);
        
        // Validasi gagal
        if ($errors) {
            Response::json(['errors' => $errors], 422, 'Validasi data sensor gagal');
        }

        try {
            // Nilai AQI dihitung di service supaya perangkat IoT cukup mengirim data mentah.
            $aqi = AQICalculator::calculate($input);
            $input['aqi_value'] = $aqi['value'];
            $input['aqi_category'] = $aqi['category'];
            $input['dominant_pollutant'] = $aqi['dominant_pollutant'];
            $input['recorded_at'] = date('Y-m-d H:i:s', isset($input['recorded_at']) ? strtotime($input['recorded_at']) : time());
            $reading = (new AirReading())->create($input);

            // Event ini nantinya dikonsumsi oleh Python ML Service.
            $published = (new RabbitMQPublisher())->publishAirNew($reading);
            $reading['event_published'] = $published;
            Response::json($reading, 201, $published
                ? 'Pembacaan tersimpan dan event air.new dipublikasikan'
                : 'Pembacaan tersimpan, tetapi RabbitMQ sedang tidak tersedia');
        } catch (Throwable $error) {
            Response::json(null, 500, $error->getMessage());
        }
    }

    // Controller untuk mengambil history data kualitas udara
    public function history(array $query): never
    {
        $zoneId = isset($query['zone_id']) ? (int) $query['zone_id'] : null;
        $limit = min(500, max(1, (int) ($query['limit'] ?? 100)));
        $page = max(1, (int) ($query['page'] ?? 1));

        // Filter tanggal bersifat opsional agar endpoint tetap bisa dipakai tanpa parameter.
        $from = isset($query['from']) ? date('Y-m-d H:i:s', strtotime($query['from'])) : null;
        $to = isset($query['to']) ? date('Y-m-d H:i:s', strtotime($query['to'])) : null;
        $rows = (new AirReading())->history($zoneId, $from, $to, $limit, ($page - 1) * $limit);
        Response::json(['items' => $rows, 'page' => $page, 'limit' => $limit]);
    }
}
