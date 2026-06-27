<?php

require_once '../app/Config/Env.php';

Env::load(__DIR__ . '/../.env');

require_once '../app/Config/Database.php';

require_once '../app/Models/Weather.php';
require_once '../app/Models/Alert.php';

require_once '../app/Controllers/WeatherController.php';
require_once '../app/Controllers/AlertController.php';

$uri = parse_url(
    $_SERVER['REQUEST_URI'],
    PHP_URL_PATH
);

if ($uri == '/api/environment/weather'
    && $_SERVER['REQUEST_METHOD'] == 'POST') {

    (new WeatherController())->store();
}

elseif ($uri == '/api/environment/current'
    && $_SERVER['REQUEST_METHOD'] == 'GET') {

    (new WeatherController())->current();
}

elseif ($uri == '/api/environment/alerts'
    && $_SERVER['REQUEST_METHOD'] == 'GET') {

    (new AlertController())->index();
}

elseif ($uri == '/api/environment/notification'
    && $_SERVER['REQUEST_METHOD'] == 'GET') {

    $db = Database::connect();

    $stmt = $db->query("SELECT * FROM list_notification");
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
