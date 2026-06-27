CREATE DATABASE IF NOT EXISTS smartcity CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smartcity;

CREATE TABLE zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    city_district VARCHAR(100) NOT NULL,
    coordinates VARCHAR(100),
    area_km2 DECIMAL(10,2),
    UNIQUE KEY uq_zones_name (name)
);

CREATE TABLE air_stations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    station_name VARCHAR(120) NOT NULL,
    station_code VARCHAR(40) NOT NULL,
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_air_station_code (station_code),
    FOREIGN KEY (zone_id) REFERENCES zones(id)
);

CREATE TABLE air_readings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    station_id BIGINT UNSIGNED NULL,
    zone_id INT NOT NULL,
    pm25 DECIMAL(10,2) NOT NULL,
    pm10 DECIMAL(10,2) NOT NULL,
    no2 DECIMAL(10,2) NOT NULL,
    co DECIMAL(10,2) NOT NULL,
    o3 DECIMAL(10,2) NOT NULL,
    aqi_value SMALLINT UNSIGNED,
    aqi_category VARCHAR(30),
    dominant_pollutant VARCHAR(10),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES zones(id),
    FOREIGN KEY (station_id) REFERENCES air_stations(id)
);

CREATE TABLE env_zone_status (
    zone_id INT UNSIGNED PRIMARY KEY,
    alert_type VARCHAR(20) NOT NULL,
    notification TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
