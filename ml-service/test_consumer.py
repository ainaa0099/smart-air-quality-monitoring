import json
import pika

connection = pika.BlockingConnection(
    pika.ConnectionParameters(host="localhost")
)

channel = connection.channel()

channel.exchange_declare(
    exchange="city.events",
    exchange_type="topic",
    durable=True
)

channel.queue_declare(queue="test_queue", durable=True)

channel.queue_bind(
    exchange="city.events",
    queue="test_queue",
    routing_key="anomaly.alert"
)

print("Waiting for anomaly.alert...")

def callback(ch, method, properties, body):
    print("\nReceived:")
    print(json.dumps(json.loads(body), indent=4))
    ch.basic_ack(delivery_tag=method.delivery_tag)

channel.basic_consume(
    queue="test_queue",
    on_message_callback=callback
)

channel.start_consuming()