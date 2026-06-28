<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class Citizen {
    private PDO $db;
    private string $table = 'citizen_citizens';

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function findAll(): array {
        $stmt = $this->db->prepare("
            SELECT c.id, c.nik, c.name, c.email, c.phone, c.zone_id, c.role, 
                   c.is_active, c.oauth_provider, c.avatar_url, c.created_at,
                   z.name as zone_name 
            FROM {$this->table} c
            LEFT JOIN zones z ON c.zone_id = z.id
            ORDER BY c.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare("
            SELECT c.id, c.nik, c.name, c.email, c.phone, c.zone_id, c.role,
                   c.is_active, c.oauth_provider, c.avatar_url, c.created_at,
                   z.name as zone_name 
            FROM {$this->table} c
            LEFT JOIN zones z ON c.zone_id = z.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findByEmail(string $email): array|false {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} WHERE email = ?
        ");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function findByNik(string $nik): array|false {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} WHERE nik = ?
        ");
        $stmt->execute([$nik]);
        return $stmt->fetch();
    }

    public function create(array $data): array|false {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (nik, name, email, password, phone, zone_id, role, is_active, oauth_provider, created_at)
            VALUES (:nik, :name, :email, :password, :phone, :zone_id, :role, :is_active, :oauth_provider, NOW())
        ");

        $stmt->execute([
            ':nik'            => $data['nik'],
            ':name'           => $data['name'],
            ':email'          => $data['email'],
            ':password'       => $data['password'],
            ':phone'          => $data['phone'],
            ':zone_id'        => $data['zone_id'],
            ':role'           => $data['role'] ?? 'citizen',
            ':is_active'      => true,
            ':oauth_provider' => $data['oauth_provider'] ?? 'local',
        ]);

        return $this->findById((int) $this->db->lastInsertId());
    }

    public function update(int $id, array $data): array|false {
        $fields = [];
        $params = [];

        $allowed = ['name', 'email', 'phone', 'zone_id', 'avatar_url'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return $this->findById($id);
        }

        $params[':id'] = $id;
        $stmt = $this->db->prepare("
            UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id
        ");
        $stmt->execute($params);

        return $this->findById($id);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->table} WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }
}