<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class Notification {
    private PDO $db;
    private string $table = 'citizen_notifications';

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function findByCitizenId(int $citizen_id, bool $unread_only = false): array {
        $where = 'WHERE n.citizen_id = ?';
        if ($unread_only) {
            $where .= ' AND n.is_read = 0';
        }

        $stmt = $this->db->prepare("
            SELECT n.*, c.name as citizen_name
            FROM {$this->table} n
            LEFT JOIN citizen_citizens c ON n.citizen_id = c.id
            {$where}
            ORDER BY n.created_at DESC
        ");
        $stmt->execute([$citizen_id]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare("
            SELECT n.*, c.name as citizen_name
            FROM {$this->table} n
            LEFT JOIN citizen_citizens c ON n.citizen_id = c.id
            WHERE n.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function create(array $data): array|false {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (citizen_id, title, body, is_read, created_at)
            VALUES (:citizen_id, :title, :body, 0, NOW())
        ");

        $stmt->execute([
            ':citizen_id' => $data['citizen_id'],
            ':title'      => $data['title'],
            ':body'       => $data['body'],
        ]);

        return $this->findById((int) $this->db->lastInsertId());
    }

    public function markAsRead(int $id): array|false {
        $stmt = $this->db->prepare("
            UPDATE {$this->table} SET is_read = 1 WHERE id = ?
        ");
        $stmt->execute([$id]);
        return $this->findById($id);
    }

    public function markAllAsRead(int $citizen_id): bool {
        $stmt = $this->db->prepare("
            UPDATE {$this->table} SET is_read = 1 WHERE citizen_id = ?
        ");
        return $stmt->execute([$citizen_id]);
    }

    public function countUnread(int $citizen_id): int {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM {$this->table} 
            WHERE citizen_id = ? AND is_read = 0
        ");
        $stmt->execute([$citizen_id]);
        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->table} WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }
}