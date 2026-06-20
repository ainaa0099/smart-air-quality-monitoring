<?php

namespace App\Controllers;

use App\Core\Response;
use App\Models\AirStation;

// Controller untuk mengambil daftar stasiun
final class StationController
{
    public function index(array $query): never
    {
        $zoneId = isset($query['zone_id']) ? (int) $query['zone_id'] : null;
        Response::json((new AirStation())->findAll($zoneId), 200, 'Daftar stasiun kualitas udara');
    }
}
