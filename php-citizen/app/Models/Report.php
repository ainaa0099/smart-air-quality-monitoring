<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class Report {
    private PDO $db;
    private string $table = 'citizen_reports';

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function findAll(array $filters = []): array {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'r.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['zone_id'])) {
            $where[] = 'r.zone_id = :zone_id';
            $params[':zone_id'] = $filters['zone_id'];
        }

        if (!empty($filters['citizen_id'])) {
            $where[] = 'r.citizen_id = :citizen_id';
            $params[':citizen_id'] = $filters['citizen_id'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->db->prepare("
            SELECT r.*, 
                   c.name as citizen_name, 
                   z.name as zone_name
            FROM {$this->table} r
            LEFT JOIN citizen_citizens c ON r.citizen_id = c.id
            LEFT JOIN zones z ON r.zone_id = z.id
            {$whereClause}
            ORDER BY r.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare("
            SELECT r.*,
                   c.name as citizen_name,
                   z.name as zone_name
            FROM {$this->table} r
            LEFT JOIN citizen_citizens c ON r.citizen_id = c.id
            LEFT JOIN zones z ON r.zone_id = z.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findByCitizenId(int $citizen_id): array {
        $stmt = $this->db->prepare("
            SELECT r.*, z.name as zone_name
            FROM {$this->table} r
            LEFT JOIN zones z ON r.zone_id = z.id
            WHERE r.citizen_id = ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$citizen_id]);
        return $stmt->fetchAll();
    }

    public function create(array $data): array|false {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (citizen_id, category, description, zone_id, status, created_at)
            VALUES (:citizen_id, :category, :description, :zone_id, :status, NOW())
        ");

        $stmt->execute([
            ':citizen_id'  => $data['citizen_id'],
            ':category'    => $data['category'],
            ':description' => $data['description'],
            ':zone_id'     => $data['zone_id'],
            ':status'      => 'pending',
        ]);

        return $this->findById((int) $this->db->lastInsertId());
    }

    public function updateStatus(int $id, string $status): array|false {
        $allowed = ['pending', 'in_progress', 'resolved', 'rejected'];
        if (!in_array($status, $allowed)) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE {$this->table} SET status = ? WHERE id = ?
        ");
        $stmt->execute([$status, $id]);

        return $this->findById($id);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->table} WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }
}