<?php

declare(strict_types=1);

use App\Config\Database;
use App\Controllers\AirQualityController;
use App\Controllers\ReadingController;
use App\Controllers\StationController;
use App\Core\Response;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

// File .env hanya dibaca bila tersedia, misalnya saat dijalankan secara lokal.
if (file_exists(dirname(__DIR__) . '/.env')) {
    Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
}

// Cross-Origin Resource Sharing
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Router
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim($path, '/') ?: '/';
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

try {
    // Router sederhana ini cukup untuk kebutuhan service tanpa framework tambahan.
    match (true) {
        $method === 'GET' && $path === '/health' => Response::json([
            'database' => Database::healthCheck() ? 'connected' : 'disconnected',
        ], 200, 'Air Quality Service healthy'),
        // Gateway umum dan jalur IoT memakai proses penyimpanan yang sama.
        $method === 'POST' && in_array($path, ['/api/airquality/readings', '/iot/airquality'], true) =>
            (new ReadingController())->store($input),
        $method === 'GET' && $path === '/api/airquality/current' =>
            (new AirQualityController())->current($_GET),
        $method === 'GET' && $path === '/api/airquality/history' =>
            (new ReadingController())->history($_GET),
        $method === 'GET' && $path === '/api/airquality/stations' =>
            (new StationController())->index($_GET),
        default => Response::json(null, 404, 'Route tidak ditemukan'),
    };
} catch (Throwable $error) {
    Response::json(null, 500, ($_ENV['APP_ENV'] ?? 'production') === 'development'
        ? $error->getMessage()
        : 'Internal server error');
}
