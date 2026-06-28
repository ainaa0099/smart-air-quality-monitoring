#include <WiFi.h>
#include <PubSubClient.h>
#include <DHT.h>

#define DHT_PIN 15
#define DHT_TYPE DHT22

#define PM25_PIN 34
#define PM10_PIN 35
#define NO2_PIN 32
#define CO_PIN 33
#define O3_PIN 25
#define WIND_PIN 26
#define WIND_DIR_PIN 27

const char* WIFI_SSID = "Wokwi-GUEST";
const char* WIFI_PASS = "";

// Wokwi tidak bisa langsung publish ke localhost laptop.
// Untuk demo lokal, arahkan Node-RED ke broker publik ini juga.
const char* MQTT_HOST = "broker.hivemq.com";
const int MQTT_PORT = 1883;

const int ZONE_ID = 1;
const char* ZONE_KEY = "zone1";
const char* AIR_TOPIC = "city/zone1/airquality";
const char* WEATHER_TOPIC = "city/zone1/weather";

DHT dht(DHT_PIN, DHT_TYPE);
WiFiClient wifiClient;
PubSubClient mqtt(wifiClient);

unsigned long lastPublish = 0;
const unsigned long PUBLISH_INTERVAL_MS = 10000;

float mapFloat(int raw, float minVal, float maxVal) {
  return minVal + ((float)raw / 4095.0) * (maxVal - minVal);
}

String windDirectionFromAnalog(int raw) {
  const char* dirs[] = {"N", "NE", "E", "SE", "S", "SW", "W", "NW"};
  int idx = map(raw, 0, 4095, 0, 7);
  idx = constrain(idx, 0, 7);
  return String(dirs[idx]);
}

String isoTimestamp() {
  // Wokwi tidak wajib NTP untuk demo. Timestamp tetap valid secara format.
  unsigned long seconds = millis() / 1000;
  char buf[40];
  snprintf(buf, sizeof(buf), "2026-06-08T%02lu:%02lu:%02lu+07:00",
           (seconds / 3600) % 24,
           (seconds / 60) % 60,
           seconds % 60);
  return String(buf);
}

void connectWiFi() {
  Serial.print("Connecting WiFi");
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println();
  Serial.print("WiFi connected, IP: ");
  Serial.println(WiFi.localIP());
}

void connectMQTT() {
  while (!mqtt.connected()) {
    String clientId = "wokwi-airquality-zone1-" + String(random(1000, 9999));
    Serial.print("Connecting MQTT as ");
    Serial.println(clientId);
    if (mqtt.connect(clientId.c_str())) {
      Serial.println("MQTT connected");
    } else {
      Serial.print("MQTT failed, rc=");
      Serial.println(mqtt.state());
      delay(2000);
    }
  }
}

void publishAirQuality() {
  float pm25 = mapFloat(analogRead(PM25_PIN), 0, 300);
  float pm10 = mapFloat(analogRead(PM10_PIN), 0, 420);
  float no2 = mapFloat(analogRead(NO2_PIN), 0, 250);
  float co = mapFloat(analogRead(CO_PIN), 0.3, 8.0);
  float o3 = mapFloat(analogRead(O3_PIN), 0, 300);

  char payload[512];
  snprintf(payload, sizeof(payload),
    "{\"station_id\":101,\"zone_id\":%d,\"pm25\":%.2f,\"pm10\":%.2f,\"no2\":%.2f,\"co\":%.2f,\"o3\":%.2f,\"recorded_at\":\"%s\",\"device_id\":\"wokwi-zone1-air-01\"}",
    ZONE_ID, pm25, pm10, no2, co, o3, isoTimestamp().c_str());

  mqtt.publish(AIR_TOPIC, payload);
  Serial.print("Published airquality: ");
  Serial.println(payload);
}

void publishWeather() {
  float temperature = dht.readTemperature();
  float humidity = dht.readHumidity();
  if (isnan(temperature)) temperature = 30.0;
  if (isnan(humidity)) humidity = 70.0;

  float windSpeed = mapFloat(analogRead(WIND_PIN), 0, 30);
  String windDir = windDirectionFromAnalog(analogRead(WIND_DIR_PIN));
  float pressure = 1006.0 + mapFloat(analogRead(WIND_PIN), 0, 12);

  char payload[512];
  snprintf(payload, sizeof(payload),
    "{\"station_id\":101,\"zone_id\":%d,\"temperature\":%.2f,\"humidity\":%.2f,\"wind_speed\":%.2f,\"wind_direction\":\"%s\",\"pressure\":%.2f,\"recorded_at\":\"%s\",\"device_id\":\"wokwi-zone1-weather-01\"}",
    ZONE_ID, temperature, humidity, windSpeed, windDir.c_str(), pressure, isoTimestamp().c_str());

  mqtt.publish(WEATHER_TOPIC, payload);
  Serial.print("Published weather: ");
  Serial.println(payload);
}

void setup() {
  Serial.begin(115200);
  randomSeed(analogRead(0));
  dht.begin();
  connectWiFi();
  mqtt.setServer(MQTT_HOST, MQTT_PORT);
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    connectWiFi();
  }
  if (!mqtt.connected()) {
    connectMQTT();
  }
  mqtt.loop();

  unsigned long now = millis();
  if (now - lastPublish >= PUBLISH_INTERVAL_MS) {
    lastPublish = now;
    publishAirQuality();
    publishWeather();
  }
}
