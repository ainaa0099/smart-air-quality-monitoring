-- ============================================================
-- Smart Air Quality Monitoring Platform
-- Unified Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS smartcity;
USE smartcity;

-- ============================================================
-- SHARED TABLES
-- ============================================================

CREATE TABLE zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    city_district VARCHAR(100) NOT NULL,
    coordinates VARCHAR(100),
    area_km2 DECIMAL(10,2),
    UNIQUE KEY uq_zones_name (name)
);

-- ============================================================
-- CITIZEN SERVICE (also used for authentication)
-- ============================================================

CREATE TABLE citizen_citizens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nik VARCHAR(16) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    zone_id INT NOT NULL,
    role VARCHAR(20) DEFAULT 'citizen',
    is_active BOOLEAN DEFAULT TRUE,
    oauth_provider VARCHAR(20) DEFAULT 'local',
    avatar_url VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES zones(id)
);

CREATE TABLE citizen_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    citizen_id INT NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    zone_id INT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES citizen_citizens(id),
    FOREIGN KEY (zone_id) REFERENCES zones(id)
);

CREATE TABLE citizen_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    citizen_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    body TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES citizen_citizens(id)
);

-- ============================================================
-- AUTH TABLES
-- Originally defined in services/auth/db_auth.sql as a
-- separate database. Merged into smartcity; user_id columns
-- now reference citizen_citizens(id) instead of a separate
-- users table, since registration and login are unified.
-- ============================================================

CREATE TABLE refresh_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(500) NOT NULL,
    is_revoked BOOLEAN DEFAULT FALSE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES citizen_citizens(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);

CREATE TABLE social_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    provider VARCHAR(50) NOT NULL,
    provider_user_id VARCHAR(255) NOT NULL,
    provider_email VARCHAR(255),
    avatar_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES citizen_citizens(id) ON DELETE CASCADE,
    UNIQUE KEY uk_provider_user (provider, provider_user_id),
    INDEX idx_user_id (user_id)
);

CREATE TABLE oauth_clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id VARCHAR(255) UNIQUE NOT NULL,
    client_secret VARCHAR(255) NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    redirect_uris JSON,
    grant_types JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_client_id (client_id)
);

CREATE TABLE oauth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_id VARCHAR(255) UNIQUE NOT NULL,
    client_id VARCHAR(255) NOT NULL,
    user_id INT,
    access_token VARCHAR(500) NOT NULL,
    token_type VARCHAR(50) DEFAULT 'Bearer',
    expires_at DATETIME NOT NULL,
    scope VARCHAR(255),
    is_revoked BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES citizen_citizens(id) ON DELETE SET NULL,
    INDEX idx_client_id (client_id),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);

CREATE TABLE oauth_authorization_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code_id VARCHAR(255) UNIQUE NOT NULL,
    client_id VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    redirect_uri VARCHAR(255) NOT NULL,
    scope VARCHAR(255),
    is_used BOOLEAN DEFAULT FALSE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES citizen_citizens(id) ON DELETE CASCADE,
    INDEX idx_client_id (client_id),
    INDEX idx_expires_at (expires_at)
);

CREATE TABLE login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    login_method VARCHAR(50) NOT NULL,
    provider VARCHAR(50) DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL,
    city VARCHAR(100),
    region VARCHAR(100),
    country VARCHAR(100),
    loc VARCHAR(100),
    timezone VARCHAR(100),
    org VARCHAR(255),
    user_agent TEXT,
    login_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES citizen_citizens(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_login_at (login_at)
);

-- ============================================================
-- AIR QUALITY SERVICE
-- ============================================================

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

-- ============================================================
-- ENVIRONMENT SERVICE
-- ============================================================

CREATE TABLE env_weather (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    temperature DECIMAL(5,2) NOT NULL,
    humidity DECIMAL(5,2) NOT NULL,
    wind_speed DECIMAL(5,2) NOT NULL,
    wind_direction DECIMAL(5,2) NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES zones(id)
);

CREATE TABLE env_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    event VARCHAR(50) DEFAULT 'anomaly.alert',
    alert_type VARCHAR(50) NOT NULL,
    pollutant VARCHAR(20),
    anomaly_score DECIMAL(6,4),
    severity VARCHAR(20) NOT NULL,
    value DECIMAL(6,2),
    threshold DECIMAL(6,2),
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES zones(id)
);

CREATE TABLE env_zone_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL UNIQUE,
    notification TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES zones(id)
);

-- ============================================================
-- ADDITIONAL INDEXES
-- ============================================================

CREATE INDEX idx_air_zone_recorded ON air_readings(zone_id, recorded_at);
CREATE INDEX idx_air_station_recorded ON air_readings(station_id, recorded_at);
CREATE INDEX idx_env_zone_recorded ON env_weather(zone_id, recorded_at);
CREATE INDEX idx_reports_status ON citizen_reports(status);
CREATE INDEX idx_reports_zone ON citizen_reports(zone_id);

-- ============================================================
-- DEFAULT ZONES
-- ============================================================

INSERT INTO zones (name, city_district, coordinates, area_km2) VALUES
('Zone 1', 'Jakarta Pusat', '-6.1862,106.8284', 48.13),
('Zone 2', 'Jakarta Utara', '-6.1384,106.8636', 146.66),
('Zone 3', 'Jakarta Selatan', '-6.2615,106.8106', 141.27),
('Zone 4', 'Jakarta Timur', '-6.2250,106.9004', 188.03),
('Zone 5', 'Jakarta Barat', '-6.1352,106.7621', 129.54);

-- ============================================================
-- DEFAULT AIR QUALITY STATIONS
-- One station per zone, matching the station_id convention
-- (101-105) used by the ML simulator (Anggota 5).
-- ============================================================

INSERT INTO air_stations (id, zone_id, station_name, station_code, latitude, longitude) VALUES
(101, 1, 'Stasiun Pemantau Jakarta Pusat', 'AQ-ZONE1-101', -6.1862, 106.8284),
(102, 2, 'Stasiun Pemantau Jakarta Utara', 'AQ-ZONE2-102', -6.1384, 106.8636),
(103, 3, 'Stasiun Pemantau Jakarta Selatan', 'AQ-ZONE3-103', -6.2615, 106.8106),
(104, 4, 'Stasiun Pemantau Jakarta Timur', 'AQ-ZONE4-104', -6.2250, 106.9004),
(105, 5, 'Stasiun Pemantau Jakarta Barat', 'AQ-ZONE5-105', -6.1352, 106.7621);

-- ============================================================
-- DEFAULT OAUTH TEST CLIENT
-- ============================================================

INSERT IGNORE INTO oauth_clients (client_id, client_secret, client_name, grant_types) VALUES
('test-client', 'test-secret', 'Test Client', '["client_credentials", "password", "refresh_token"]');