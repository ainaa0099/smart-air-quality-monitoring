<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\CitizenController;
use App\Controllers\ReportController;
use App\Controllers\NotifController;
use App\Config\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path   = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$query  = $_GET;

$citizenController = new CitizenController();
$reportController  = new ReportController();
$notifController   = new NotifController();

$routes = [
    'GET'    => [
        '#^/health$#'                       => fn() => healthCheck(),
        '#^/api/citizens/(\d+)$#'           => fn($id) => $citizenController->show((int) $id),
        '#^/api/reports$#'                  => fn() => $reportController->index([
            'status'     => $query['status'] ?? null,
            'zone_id'    => $query['zone_id'] ?? null,
            'citizen_id' => $query['citizen_id'] ?? null,
        ]),
        '#^/api/reports/(\d+)$#'            => fn($id) => $reportController->show((int) $id),
        '#^/api/notifications$#'            => fn() => $notifController->index(
            (int) ($query['citizen_id'] ?? 0),
            ($query['unread'] ?? null) === 'true'
        ),
        '#^/api/notifications/(\d+)$#'      => fn($id) => $notifController->show((int) $id),
    ],
    'POST'   => [
        '#^/api/citizens$#'                 => fn() => $citizenController->store($body),
        '#^/api/reports$#'                  => fn() => $reportController->store($body),
        '#^/api/notifications$#'            => fn() => $notifController->store($body),
    ],
    'PUT'    => [
        '#^/api/citizens/(\d+)$#'           => fn($id) => $citizenController->update((int) $id, $body),
    ],
    'PATCH'  => [
        '#^/api/reports/(\d+)/status$#'     => fn($id) => $reportController->updateStatus((int) $id, $body),
        '#^/api/notifications/(\d+)/read$#' => fn($id) => $notifController->markAsRead((int) $id),
        '#^/api/notifications/read-all$#'   => fn() => $notifController->markAllAsRead((int) ($body['citizen_id'] ?? 0)),
    ],
    'DELETE' => [
        '#^/api/citizens/(\d+)$#'           => fn($id) => $citizenController->destroy((int) $id),
        '#^/api/reports/(\d+)$#'            => fn($id) => $reportController->destroy((int) $id),
        '#^/api/notifications/(\d+)$#'      => fn($id) => $notifController->destroy((int) $id),
    ],
];

function healthCheck(): void {
    try {
        Database::getConnection();
        respond(200, 'success', 'Citizen service is healthy', ['database' => 'connected']);
    } catch (\Exception $e) {
        respond(503, 'error', 'Database connection failed', null, 503);
    }
}

function respond(int $code, string $status, string $message, mixed $data = null, int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode([
        'status'    => $status,
        'code'      => $code,
        'data'      => $data,
        'message'   => $message,
        'timestamp' => date('c'),
        'service'   => 'citizen-service',
    ]);
    exit;
}

foreach ($routes[$method] ?? [] as $pattern => $handler) {
    if (preg_match($pattern, $path, $matches)) {
        $handler(...array_slice($matches, 1));
        exit;
    }
}

respond(404, 'error', 'Route not found', null, 404);