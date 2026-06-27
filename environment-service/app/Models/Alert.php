<?php

class Alert
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function create(array $data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO env_alerts (
                zone_id,
                event,
                alert_type,
                pollutant,
                anomaly_score,
                severity,
                value,
                threshold
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $data['zone_id'],
            $data['event'] ?? 'anomaly.alert',
            $data['alert_type'],
            $data['pollutant'] ?? null,
            $data['anomaly_score'] ?? null,
            $data['severity'],
            $data['value'],
            $data['threshold']
        ]);
    }

    public function getAll()
    {
        $stmt = $this->db->query("
            SELECT
                id,
                zone_id,
                event,
                alert_type,
                COALESCE(pollutant, '-') AS pollutant,
                anomaly_score,
                severity,
                value,
                threshold,
                resolved_at,
                created_at
            FROM env_alerts
            ORDER BY created_at DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLatestPerZone()
    {
        $stmt = $this->db->query("
            SELECT a.*
            FROM env_alerts a
            INNER JOIN (
                SELECT zone_id, MAX(created_at) AS latest_created
                FROM env_alerts
                GROUP BY zone_id
            ) latest
                ON a.zone_id = latest.zone_id
               AND a.created_at = latest.latest_created
            ORDER BY a.created_at DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
