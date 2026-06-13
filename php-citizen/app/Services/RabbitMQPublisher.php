<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher {
    private AMQPStreamConnection $connection;
    private \PhpAmqpLib\Channel\AMQPChannel $channel;
    private string $exchange = 'city.events';

    public function __construct() {
        $host     = $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq';
        $port     = $_ENV['RABBITMQ_PORT'] ?? 5672;
        $user     = $_ENV['RABBITMQ_USER'] ?? 'guest';
        $password = $_ENV['RABBITMQ_PASS'] ?? 'guest';

        $this->connection = new AMQPStreamConnection($host, $port, $user, $password);
        $this->channel    = $this->connection->channel();

        $this->channel->exchange_declare(
            $this->exchange,
            'topic',
            false,
            true,
            false
        );
    }

    public function publish(string $routingKey, array $data): void {
        $payload = json_encode([
            'routing_key' => $routingKey,
            'data'        => $data,
            'timestamp'   => date('c'),
            'service'     => 'citizen-service',
        ]);

        $message = new AMQPMessage($payload, [
            'content_type'  => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        $this->channel->basic_publish($message, $this->exchange, $routingKey);
    }

    public function close(): void {
        $this->channel->close();
        $this->connection->close();
    }

    public function __destruct() {
        try {
            $this->close();
        } catch (\Exception) {
            // connection mungkin sudah tertutup
        }
    }
}