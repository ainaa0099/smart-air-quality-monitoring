<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

require_once __DIR__ . '/../../vendor/autoload.php';

class RabbitMQ
{
    public static function publish($queue, $data)
    {
        // Konfigurasi broker dibuat fleksibel agar bisa jalan lokal maupun di Docker Compose.
        $host = $_ENV['RABBITMQ_HOST'] ?? getenv('RABBITMQ_HOST') ?: '127.0.0.1';
        $port = (int) ($_ENV['RABBITMQ_PORT'] ?? getenv('RABBITMQ_PORT') ?: 5672);
        $user = $_ENV['RABBITMQ_USER'] ?? getenv('RABBITMQ_USER') ?: 'guest';
        $pass = $_ENV['RABBITMQ_PASS'] ?? getenv('RABBITMQ_PASS') ?: 'guest';
        $vhost = $_ENV['RABBITMQ_VHOST'] ?? getenv('RABBITMQ_VHOST') ?: '/';

        $connection = new AMQPStreamConnection(
            $host,
            $port,
            $user,
            $pass,
            $vhost
        );

        $channel = $connection->channel();

        $channel->queue_declare($queue, false, false, false, false);

        $msg = new AMQPMessage(json_encode($data));

        $channel->basic_publish($msg, '', $queue);

        $channel->close();
        $connection->close();
    }
}
