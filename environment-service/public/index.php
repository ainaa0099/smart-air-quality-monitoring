<?php

require_once '../app/Config/Env.php';

Env::load(__DIR__ . '/../.env');

header('Content-Type: application/json');

require_once '../app/Config/Database.php';

require_once '../app/Models/Weather.php';
require_once '../app/Models/Alert.php';
require_once '../app/Models/Zone.php';

require_once '../app/Controllers/ZoneController.php';
require_once '../app/Controllers/WeatherController.php';
require_once '../app/Controllers/AlertController.php';

$uri = parse_url(
    $_SERVER['REQUEST_URI'],
    PHP_URL_PATH
);

// Routing sederhana untuk endpoint environment-service.
if (($uri == '/api/environment/weather' || $uri == '/iot/weather')
    && $_SERVER['REQUEST_METHOD'] == 'POST') {

    (new WeatherController())->store();
    exit;
}

elseif ($uri == '/api/environment/current'
    && $_SERVER['REQUEST_METHOD'] == 'GET') {

    (new WeatherController())->current();
    exit;
}

elseif ($uri == '/api/environment/alerts'
    && $_SERVER['REQUEST_METHOD'] == 'GET') {

    (new AlertController())->index();
    exit;
}

elseif (
    $uri == '/api/environment/zones'
    && $_SERVER['REQUEST_METHOD'] == 'GET'
) {
    (new ZoneController())->index();
    exit;
}

elseif (
    preg_match('#^/api/environment/zones/(\d+)$#', $uri, $matches)
    && $_SERVER['REQUEST_METHOD'] == 'GET'
) {
    (new ZoneController())->show((int)$matches[1]);
    exit;
}

elseif ($uri == '/api/environment/notification'
    && $_SERVER['REQUEST_METHOD'] == 'GET') {

    $db = Database::connect();

    $stmt = $db->query("SELECT * FROM env_zone_status");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "code" => 200,
        "data" => $data,
        "timestamp" => date('Y-m-d H:i:s'),
        "service" => "environment"
    ]);

    exit;
}

elseif ($uri == '/health'
    && $_SERVER['REQUEST_METHOD'] == 'GET') {

    try {
        $db = Database::connect();
        $db->query("SELECT 1");

        http_response_code(200);
        echo json_encode([
            "status" => "UP",
            "database" => "connected",
            "service" => "environment",
            "timestamp" => date('Y-m-d H:i:s')
        ]);
    } catch (PDOException $e) {

        http_response_code(503);
        echo json_encode([
            "status" => "DOWN",
            "database" => "disconnected",
            "service" => "environment",
            "error" => $e->getMessage(),
            "timestamp" => date('Y-m-d H:i:s')
        ]);
    }

    exit;
}

elseif ($uri == '/metrics'
    && $_SERVER['REQUEST_METHOD'] == 'GET') {

    require __DIR__ . '/metrics.php';
    exit;
}

http_response_code(404);
echo json_encode([
    "status" => "error",
    "code" => 404,
    "message" => "Endpoint not found"
]);
