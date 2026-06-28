CREATE DATABASE IF NOT EXISTS smartcity CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smartcity;

CREATE TABLE IF NOT EXISTS zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    city_district VARCHAR(100) NOT NULL,
    coordinates VARCHAR(100),
    area_km2 DECIMAL(10,2),
    UNIQUE KEY uq_zones_name (name)
);

CREATE TABLE IF NOT EXISTS env_weather (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    temperature DECIMAL(5,2) NOT NULL,
    humidity DECIMAL(5,2) NOT NULL,
    wind_speed DECIMAL(5,2) NOT NULL,
    wind_direction DECIMAL(5,2) NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_env_weather_zone FOREIGN KEY (zone_id) REFERENCES zones(id),
    INDEX idx_env_weather_zone_recorded (zone_id, recorded_at)
);

CREATE TABLE IF NOT EXISTS env_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    event VARCHAR(50) DEFAULT 'anomaly.alert',
    alert_type VARCHAR(50) NOT NULL,
    pollutant VARCHAR(20),
    anomaly_score DECIMAL(6,4),
    severity VARCHAR(20) NOT NULL,
    value DECIMAL(10,2),
    threshold DECIMAL(10,2),
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_env_alerts_zone FOREIGN KEY (zone_id) REFERENCES zones(id),
    INDEX idx_env_alerts_zone_created (zone_id, created_at)
);

CREATE TABLE IF NOT EXISTS env_zone_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL UNIQUE,
    alert_type VARCHAR(50) NOT NULL DEFAULT 'status_update',
    severity VARCHAR(20) NOT NULL DEFAULT 'Normal',
    notification TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_env_zone_status_zone FOREIGN KEY (zone_id) REFERENCES zones(id)
);
