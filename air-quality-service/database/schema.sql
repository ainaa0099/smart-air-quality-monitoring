CREATE DATABASE IF NOT EXISTS smartcity CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smartcity;

CREATE TABLE IF NOT EXISTS zones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    city_district VARCHAR(100) NOT NULL,
    coordinates VARCHAR(100) NULL,
    area_km2 DECIMAL(8,2) NULL,
    UNIQUE KEY uq_zones_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS air_stations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    zone_id INT UNSIGNED NOT NULL,
    station_name VARCHAR(120) NOT NULL,
    station_code VARCHAR(40) NOT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_air_station_code (station_code),
    KEY idx_air_stations_zone (zone_id),
    CONSTRAINT fk_air_stations_zone FOREIGN KEY (zone_id) REFERENCES zones(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS air_readings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    station_id BIGINT UNSIGNED NULL,
    zone_id INT UNSIGNED NOT NULL,
    pm25 DECIMAL(10,2) NOT NULL,
    pm10 DECIMAL(10,2) NOT NULL,
    no2 DECIMAL(10,2) NOT NULL,
    co DECIMAL(10,2) NOT NULL,
    o3 DECIMAL(10,2) NOT NULL,
    aqi_value SMALLINT UNSIGNED NOT NULL,
    aqi_category VARCHAR(30) NOT NULL,
    dominant_pollutant VARCHAR(10) NOT NULL,
    recorded_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Index ini mempercepat endpoint current dan history per zona.
    KEY idx_air_readings_zone_time (zone_id, recorded_at),
    KEY idx_air_readings_station_time (station_id, recorded_at),
    CONSTRAINT fk_air_readings_zone FOREIGN KEY (zone_id) REFERENCES zones(id),
    CONSTRAINT fk_air_readings_station FOREIGN KEY (station_id) REFERENCES air_stations(id)
) ENGINE=InnoDB;