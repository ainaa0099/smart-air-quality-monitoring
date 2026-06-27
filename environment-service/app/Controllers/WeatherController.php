<?php

class WeatherController
{
    public function store()
    {
        $data = json_decode(
            file_get_contents("php://input"),
            true
        );

        $weather = new Weather();

        $weather->create($data);

        echo json_encode([
            "message" => "Weather saved"
        ]);
    }

    public function current()
    {
        $weather = new Weather();

        echo json_encode(
            $weather->getCurrent()
        );
    }
}