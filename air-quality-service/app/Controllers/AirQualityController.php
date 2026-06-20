<?php

namespace App\Controllers;

use App\Core\Response;
use App\Models\AirReading;

// Controller untuk mengambil data kualitas udara terbaru
final class AirQualityController
{
    public function current(array $query): never
    {
        $zoneId = isset($query['zone_id']) ? (int) $query['zone_id'] : null;
        Response::json((new AirReading())->current($zoneId), 200, 'Data kualitas udara terbaru');
    }
}
