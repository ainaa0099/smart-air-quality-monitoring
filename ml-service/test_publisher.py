import json
import pika

connection = pika.BlockingConnection(
    pika.ConnectionParameters("localhost")
)

channel = connection.channel()

channel.exchange_declare(
    exchange="city.events",
    exchange_type="topic",
    durable=True
)

payload = {
    "event": "air.new",
    "data": {
        "zone_id": 1,
        "pm25": 220,
        "pm10": 250,
        "no2": 80,
        "co": 4.0,
        "o3": 90
    }
}

channel.basic_publish(
    exchange="city.events",
    routing_key="air.new",
    body=json.dumps(payload)
)

print("Published air.new")

connection.close()