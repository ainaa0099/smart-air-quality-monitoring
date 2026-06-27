<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Config/Env.php';

Env::load(__DIR__ . '/.env');

require_once __DIR__ . '/app/Config/Database.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

$connection = new AMQPStreamConnection(
    $_ENV['RABBITMQ_HOST'],
    (int) $_ENV['RABBITMQ_PORT'],
    $_ENV['RABBITMQ_USER'],
    $_ENV['RABBITMQ_PASS']
);

$channel = $connection->channel();

$channel->exchange_declare(
    'city.events',
    'topic',
    false,
    true,
    false
);

$channel->queue_declare(
    'anomaly.alert',
    false,
    false,
    false,
    false
);

$channel->queue_bind(
    'anomaly.alert',
    'city.events',
    'anomaly.alert'
);

echo "Waiting for anomaly.alert...\n";

$callback = function ($msg) {

    try {
        $data = json_decode($msg->body, true);

        if (!$data) {
            throw new Exception("Payload JSON tidak valid.");
        }

        echo "Received: {$msg->body}" . PHP_EOL;

        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO env_alerts (
                zone_id,
                event,
                pollutant,
                anomaly_score,
                severity,
                value,
                threshold,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['zone_id'] ?? null,
            $data['event'] ?? null,
            $data['pollutant'] ?? null,
            $data['anomaly_score'] ?? null,
            $data['severity'] ?? null,
            $data['value'] ?? null,
            $data['threshold'] ?? null,
            $data['created_at'] ?? date('Y-m-d H:i:s')
        ]);

        switch ($data['severity'] ?? 'Normal') {

            case 'Kritis':
                $notification = sprintf(
                    "KRITIS\nZona %d: %s = %.2f (batas %.2f).\nHindari aktivitas luar, gunakan masker, dan tetap di dalam ruangan.",
                    $data['zone_id'],
                    $data['pollutant'],
                    $data['value'],
                    $data['threshold']
                );
                break;

            case 'Peringatan':
                $notification = sprintf(
                    "PERINGATAN\nZona %d: %s = %.2f (batas %.2f).\nKurangi aktivitas luar dan gunakan masker bila diperlukan.",
                    $data['zone_id'],
                    $data['pollutant'],
                    $data['value'],
                    $data['threshold']
                );
                break;

            default:
                $notification = sprintf(
                    "NORMAL\nZona %d: %s = %.2f. Kondisi masih aman.",
                    $data['zone_id'],
                    $data['pollutant'],
                    $data['value']
                );
        }

        $stmtNotif = $db->prepare("
            INSERT INTO env_zone_status (
                zone_id,
                alert_type,
                notification,
                created_at
            )
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                alert_type = VALUES(alert_type),
                notification = VALUES(notification),
                created_at = VALUES(created_at)
        ");

        $stmtNotif->execute([
            $data['zone_id'],
            $data['severity'] ?? 'Normal', // atau $data['alert_type'] jika memang dikirim publisher
            $notification,
            $data['created_at'] ?? date('Y-m-d H:i:s')
        ]);

        echo "[SUCCESS] Data berhasil disimpan.\n";

    } catch (PDOException $e) {

        echo "[DATABASE ERROR]\n";
        echo $e->getMessage() . PHP_EOL;

    } catch (Exception $e) {

        echo "[ERROR]\n";
        echo $e->getMessage() . PHP_EOL;

    } finally {

        $msg->ack();
    }
};

$channel->basic_consume(
    'anomaly.alert',
    '',
    false,
    false,
    false,
    false,
    $callback
);

try {

    while ($channel->is_consuming()) {
        $channel->wait();
    }

} catch (Throwable $e) {

    echo "[FATAL] " . $e->getMessage() . PHP_EOL;

} finally {

    $channel->close();
    $connection->close();
}
