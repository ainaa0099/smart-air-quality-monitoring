<?php

class Weather
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function create(array $data)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO env_weather
            (zone_id,temperature,humidity,wind_speed,wind_direction)
            VALUES (?,?,?,?,?)"
        );

        return $stmt->execute([
            $data['zone_id'],
            $data['temperature'],
            $data['humidity'],
            $data['wind_speed'],
            $data['wind_direction']
        ]);
    }

    public function getCurrent()
    {
        $stmt = $this->db->query(
            "SELECT *
             FROM env_weather
             ORDER BY recorded_at DESC
             LIMIT 1"
        );

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}