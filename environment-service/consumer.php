<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Config/Env.php';

Env::load(__DIR__ . '/.env');

require_once __DIR__ . '/app/Config/Database.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

$connection = new AMQPStreamConnection(
    $_ENV['RABBITMQ_HOST'] ?? getenv('RABBITMQ_HOST') ?: '127.0.0.1',
    (int) ($_ENV['RABBITMQ_PORT'] ?? getenv('RABBITMQ_PORT') ?: 5672),
    $_ENV['RABBITMQ_USER'] ?? getenv('RABBITMQ_USER') ?: 'guest',
    $_ENV['RABBITMQ_PASS'] ?? getenv('RABBITMQ_PASS') ?: 'guest',
    $_ENV['RABBITMQ_VHOST'] ?? getenv('RABBITMQ_VHOST') ?: '/'
);

$channel = $connection->channel();

// Consumer mendengar alert hasil analisis ML lewat topic exchange yang sama dengan service lain.
$channel->exchange_declare(
    $_ENV['RABBITMQ_EXCHANGE'] ?? getenv('RABBITMQ_EXCHANGE') ?: 'city.events',
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
    $_ENV['RABBITMQ_EXCHANGE'] ?? getenv('RABBITMQ_EXCHANGE') ?: 'city.events',
    'anomaly.alert'
);

echo "Waiting for anomaly.alert...\n";

$callback = function ($msg) {

    try {
        $data = json_decode($msg->body, true);

        if (!$data) {
            throw new Exception("Payload JSON tidak valid.");
        }

        $data = $data['data'] ?? $data;

        // Minimal perlu zone_id agar alert bisa ditempel ke zona kota yang benar.
        foreach (['zone_id'] as $requiredField) {
            if (!isset($data[$requiredField])) {
                throw new Exception("Field {$requiredField} wajib ada di payload anomaly.alert.");
            }
        }

        $pollutant = $data['pollutant'] ?? 'AQI';
        $value = isset($data['value']) ? (float) $data['value'] : 0.0;
        $threshold = isset($data['threshold']) ? (float) $data['threshold'] : 0.0;
        $severity = $data['severity'] ?? 'Normal';

        echo "Received: {$msg->body}" . PHP_EOL;

        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO env_alerts (
                zone_id,
                event,
                alert_type,
                pollutant,
                anomaly_score,
                severity,
                value,
                threshold,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['zone_id'],
            $data['event'] ?? 'anomaly.alert',
            $data['alert_type'] ?? 'Air Quality',
            $data['pollutant'] ?? null,
            $data['anomaly_score'] ?? null,
            $severity,
            $data['value'] ?? null,
            $data['threshold'] ?? null,
            $data['created_at'] ?? date('Y-m-d H:i:s')
        ]);

        // Notifikasi dibuat pendek supaya mudah ditampilkan oleh gateway atau dashboard.
        switch ($severity) {

            case 'Kritis':
                $notification = sprintf(
                    "KRITIS\nZona %d: %s = %.2f (batas %.2f).\nHindari aktivitas luar, gunakan masker, dan tetap di dalam ruangan.",
                    $data['zone_id'],
                    $pollutant,
                    $value,
                    $threshold
                );
                break;

            case 'Peringatan':
                $notification = sprintf(
                    "PERINGATAN\nZona %d: %s = %.2f (batas %.2f).\nKurangi aktivitas luar dan gunakan masker bila diperlukan.",
                    $data['zone_id'],
                    $pollutant,
                    $value,
                    $threshold
                );
                break;

            default:
                $notification = sprintf(
                    "NORMAL\nZona %d: %s = %.2f. Kondisi masih aman.",
                    $data['zone_id'],
                    $pollutant,
                    $value
                );
        }

        $stmtNotif = $db->prepare("
            INSERT INTO env_zone_status (
                zone_id,
                alert_type,
                severity,
                notification,
                created_at
            )
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                alert_type = VALUES(alert_type),
                severity = VALUES(severity),
                notification = VALUES(notification),
                created_at = VALUES(created_at)
        ");

        $stmtNotif->execute([
            $data['zone_id'],
            $data['alert_type'] ?? 'Air Quality',
            $severity,
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
