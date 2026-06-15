<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

$connection = new AMQPStreamConnection(
    'localhost',
    5672,
    'guest',
    'guest'
);

$channel = $connection->channel();

$queue = "item.created";

$channel->queue_declare($queue, false, false, false, false);

echo "Waiting for messages...\n";

$callback = function ($msg) {

    $data = json_decode($msg->body, true);

    echo "Received: " . $msg->body . "\n";

    // INSERT KE TAB2
    $pdo = new PDO("mysql:host=localhost;dbname=data", "root", "545454");

    $stmt = $pdo->prepare("INSERT INTO tab2(ids, items) VALUES (?, ?)");
    $stmt->execute([$data['id'], $data['item']]);

    echo "Inserted to tab2\n";
};

$channel->basic_consume($queue, '', false, true, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}