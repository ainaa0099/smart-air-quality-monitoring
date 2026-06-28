<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

final class RabbitMQPublisher
{
    // Mengirim event ke RabbitMQ
    public function publishAirNew(array $reading): bool
    {
        $connection = null;
        try {
            $connection = new AMQPStreamConnection(
                $_ENV['RABBITMQ_HOST'] ?? getenv('RABBITMQ_HOST') ?: '127.0.0.1',
                (int) ($_ENV['RABBITMQ_PORT'] ?? getenv('RABBITMQ_PORT') ?: 5672),
                $_ENV['RABBITMQ_USER'] ?? getenv('RABBITMQ_USER') ?: 'guest',
                $_ENV['RABBITMQ_PASS'] ?? getenv('RABBITMQ_PASS') ?: 'guest',
                $_ENV['RABBITMQ_VHOST'] ?? getenv('RABBITMQ_VHOST') ?: '/'
            );
            $channel = $connection->channel();
            $exchange = $_ENV['RABBITMQ_EXCHANGE'] ?? getenv('RABBITMQ_EXCHANGE') ?: 'city.events';

            // Exchange dibuat durable supaya tetap tersedia setelah RabbitMQ restart.
            $channel->exchange_declare($exchange, 'topic', false, true, false);
            $message = new AMQPMessage(json_encode([
                'event' => 'air.new',
                'occurred_at' => gmdate('c'),
                'data' => $reading,
            ], JSON_UNESCAPED_SLASHES), [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]);
            $channel->basic_publish($message, $exchange, 'air.new');
            $channel->close();
            return true;
        } catch (Throwable $error) {
            // Data sensor sudah tersimpan di MySQL, jadi gangguan broker cukup dicatat.
            error_log('RabbitMQ publish failed: ' . $error->getMessage());
            return false;
        } finally {
            if ($connection !== null && $connection->isConnected()) {
                $connection->close();
            }
        }
    }
}