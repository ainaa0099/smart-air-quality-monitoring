<?php

namespace App\Models;

use App\Config\Database;
use PDO;

final class AirReading
{
    private PDO $db;

    // Koneksi ke database menggunakan PDO.
    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // Menyimpan data kualitas udara
    public function create(array $data): array
    {
        $statement = $this->db->prepare(
            'INSERT INTO air_readings
            (station_id, zone_id, pm25, pm10, no2, co, o3, aqi_value, aqi_category, dominant_pollutant, recorded_at)
            VALUES (:station_id, :zone_id, :pm25, :pm10, :no2, :co, :o3, :aqi_value, :aqi_category, :dominant_pollutant, :recorded_at)'
        );
        $statement->execute([
            ':station_id' => $data['station_id'] ?? null,
            ':zone_id' => (int) $data['zone_id'],
            ':pm25' => (float) $data['pm25'],
            ':pm10' => (float) $data['pm10'],
            ':no2' => (float) $data['no2'],
            ':co' => (float) $data['co'],
            ':o3' => (float) $data['o3'],
            ':aqi_value' => $data['aqi_value'],
            ':aqi_category' => $data['aqi_category'],
            ':dominant_pollutant' => $data['dominant_pollutant'],
            ':recorded_at' => $data['recorded_at'],
        ]);

        // Ambil ulang supaya respons juga berisi nama zona dan nama stasiun.
        return $this->findById((int) $this->db->lastInsertId());
    }

    // Mendapatkan data kualitas udara berdasarkan ID
    public function findById(int $id): array
    {
        $statement = $this->db->prepare($this->baseSelect() . ' WHERE r.id = :id');
        $statement->execute([':id' => $id]);
        return $statement->fetch() ?: [];
    }

    // Mendapatkan data kualitas udara terbaru
    public function current(?int $zoneId = null): array
    {
        // Subquery mencari timestamp terakhir pada masing-masing zona.
        $sql = $this->baseSelect() . '
            INNER JOIN (
                SELECT zone_id, MAX(recorded_at) latest_at
                FROM air_readings GROUP BY zone_id
            ) latest ON latest.zone_id = r.zone_id AND latest.latest_at = r.recorded_at';
        $params = [];
        if ($zoneId !== null) {
            $sql .= ' WHERE r.zone_id = :zone_id';
            $params[':zone_id'] = $zoneId;
        }
        $sql .= ' ORDER BY r.zone_id';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    // Mendapatkan history data kualitas udara
    public function history(?int $zoneId, ?string $from, ?string $to, int $limit, int $offset): array
    {
        $conditions = [];
        $params = [];
        if ($zoneId !== null) {
            $conditions[] = 'r.zone_id = :zone_id';
            $params[':zone_id'] = $zoneId;
        }
        if ($from !== null) {
            $conditions[] = 'r.recorded_at >= :date_from';
            $params[':date_from'] = $from;
        }
        if ($to !== null) {
            $conditions[] = 'r.recorded_at <= :date_to';
            $params[':date_to'] = $to;
        }
        $sql = $this->baseSelect();
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        // Limit dan offset di-bind sebagai integer agar MySQL menerima sintaks pagination.
        $sql .= ' ORDER BY r.recorded_at DESC LIMIT :limit OFFSET :offset';
        $statement = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    // Mendapatkan history data kualitas udara
    private function baseSelect(): string
    {
        return 'SELECT r.*, z.name AS zone_name, s.station_name
                FROM air_readings r
                INNER JOIN zones z ON z.id = r.zone_id
                LEFT JOIN air_stations s ON s.id = r.station_id';
    }
}
