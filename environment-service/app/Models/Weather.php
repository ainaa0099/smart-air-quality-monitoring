<?php

class Weather
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO env_weather
            (
                zone_id,
                temperature,
                humidity,
                wind_speed,
                wind_direction
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $data['zone_id'],
            $data['temperature'],
            $data['humidity'],
            $data['wind_speed'],
            $data['wind_direction']
        ]);
    }

    public function getCurrent(): array
    {
        // Ambil satu data cuaca terbaru untuk setiap zona.
        $stmt = $this->db->query("
            SELECT ew.*
            FROM env_weather ew
            INNER JOIN (
                SELECT zone_id, MAX(recorded_at) AS latest
                FROM env_weather
                GROUP BY zone_id
            ) latest_weather
            ON ew.zone_id = latest_weather.zone_id
            AND ew.recorded_at = latest_weather.latest
            ORDER BY ew.zone_id
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCurrentByZone(int $zoneId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM env_weather
            WHERE zone_id = ?
            ORDER BY recorded_at DESC
            LIMIT 1
        ");

        $stmt->execute([$zoneId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
