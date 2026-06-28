<?php

class ZoneController
{
    public function index()
    {
        $zone = new Zone();

        echo json_encode([
            "status" => "success",
            "code" => 200,
            "data" => $zone->getAll(),
            "timestamp" => date('Y-m-d H:i:s'),
            "service" => "environment"
        ]);

        exit;
    }

    public function show(int $id)
    {
        $zone = new Zone();

        $data = $zone->getById($id);

        if (!$data) {
            http_response_code(404);

            echo json_encode([
                "status" => "error",
                "code" => 404,
                "message" => "Zone not found"
            ]);

            exit;
        }

        echo json_encode([
            "status" => "success",
            "code" => 200,
            "data" => $data,
            "timestamp" => date('Y-m-d H:i:s'),
            "service" => "environment"
        ]);

        exit;
    }
}