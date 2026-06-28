<?php

class AlertController
{
    public function index()
    {
        $alert = new Alert();

        echo json_encode([
            "status" => "success",
            "code" => 200,
            "data" => $alert->getAll(),
            "timestamp" => date('Y-m-d H:i:s'),
            "service" => "environment"
        ]);
    }
}
