<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

require_once __DIR__ . '/../../vendor/autoload.php';

class RabbitMQ
{
    public static function publish($queue, $data)
    {
        $connection = new AMQPStreamConnection(
            'localhost',
            5672,
            'guest',
            'guest'
        );

        $channel = $connection->channel();

        $channel->queue_declare($queue, false, false, false, false);

        $msg = new AMQPMessage(json_encode($data));

        $channel->basic_publish($msg, '', $queue);

        $channel->close();
        $connection->close();
    }
}