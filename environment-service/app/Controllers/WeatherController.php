<?php

class WeatherController
{
    public function store()
    {
        $data = json_decode(
            file_get_contents("php://input"),
            true
        );

        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "code" => 400,
                "message" => "Payload JSON tidak valid",
                "service" => "environment"
            ]);
            return;
        }

        $data = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;

        if (isset($data['timestamp']) && !isset($data['recorded_at'])) {
            $data['recorded_at'] = $data['timestamp'];
        }

        if (isset($data['wind_direction']) && !is_numeric($data['wind_direction'])) {
            $directions = [
                'N' => 0,
                'NE' => 45,
                'E' => 90,
                'SE' => 135,
                'S' => 180,
                'SW' => 225,
                'W' => 270,
                'NW' => 315
            ];
            $key = strtoupper(trim((string) $data['wind_direction']));
            if (array_key_exists($key, $directions)) {
                $data['wind_direction'] = $directions[$key];
            }
        }

        // Data cuaca wajib lengkap supaya record yang masuk konsisten per zona.
        $requiredFields = [
            'zone_id',
            'temperature',
            'humidity',
            'wind_speed',
            'wind_direction'
        ];

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === '') {
                http_response_code(422);
                echo json_encode([
                    "status" => "error",
                    "code" => 422,
                    "message" => "Field {$field} wajib diisi",
                    "service" => "environment"
                ]);
                return;
            }
        }

        if (!is_numeric($data['zone_id'])
            || !is_numeric($data['temperature'])
            || !is_numeric($data['humidity'])
            || !is_numeric($data['wind_speed'])
            || !is_numeric($data['wind_direction'])) {

            http_response_code(422);
            echo json_encode([
                "status" => "error",
                "code" => 422,
                "message" => "Field zone_id, temperature, humidity, wind_speed, dan wind_direction harus numerik",
                "service" => "environment"
            ]);
            return;
        }

        $weather = new Weather();

        $weather->create($data);

        echo json_encode([
            "status" => "success",
            "code" => 201,
            "message" => "Weather saved",
            "timestamp" => date('Y-m-d H:i:s'),
            "service" => "environment"
        ]);
    }

    public function current()
    {
        $weather = new Weather();

        echo json_encode([
            "status" => "success",
            "code" => 200,
            "data" => $weather->getCurrent(),
            "timestamp" => date('Y-m-d H:i:s'),
            "service" => "environment"
        ]);
    }
}
