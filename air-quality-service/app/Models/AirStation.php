<?php

namespace App\Models;

use App\Config\Database;
use PDO;

final class AirStation
{
    private PDO $db;

    // Koneksi ke database menggunakan PDO.
    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // Mendapatkan daftar stasiun
    public function findAll(?int $zoneId = null): array
    {
        $sql = 'SELECT s.*, z.name AS zone_name FROM air_stations s
                INNER JOIN zones z ON z.id = s.zone_id';
        $params = [];
        if ($zoneId !== null) {
            $sql .= ' WHERE s.zone_id = :zone_id';
            $params[':zone_id'] = $zoneId;
        }
        $sql .= ' ORDER BY s.id';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }
}
