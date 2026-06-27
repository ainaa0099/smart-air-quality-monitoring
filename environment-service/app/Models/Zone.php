<?php

class Zone
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function getAll()
    {
        $stmt = $this->db->query(
            "SELECT * FROM zones"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}