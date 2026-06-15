<?php

require_once __DIR__ . '/../Config/Database.php';

class Item
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM tab");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM tab WHERE id = ?"
        );

        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(int $id, string $item): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO tab(id, item)
             VALUES (?, ?)"
        );

        return $stmt->execute([$id, $item]);
    }

    public function update(int $id, string $item): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE tab
             SET item = ?
             WHERE id = ?"
        );

        return $stmt->execute([$item, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM tab
             WHERE id = ?"
        );

        return $stmt->execute([$id]);
    }
}