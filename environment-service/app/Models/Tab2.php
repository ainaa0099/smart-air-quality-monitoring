<?php

require_once __DIR__ . '/../Config/Database.php';

class Tab2
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM tab2");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM tab2 WHERE ids = ?"
        );

        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(int $id, string $items): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO tab2(ids, items)
             VALUES (?, ?)"
        );

        return $stmt->execute([$id, $items]);
    }

    public function update(int $id, string $items): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE tab2
             SET items = ?
             WHERE ids = ?"
        );

        return $stmt->execute([$items, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM tab2
             WHERE ids = ?"
        );

        return $stmt->execute([$id]);
    }
}