USE smartcity;

INSERT INTO zones (id, name, city_district, coordinates, area_km2) VALUES
(1, 'Zona Pusat Kota', 'Central District', '-6.200000,106.816666', 12.50),
(2, 'Zona Industri', 'Industrial District', '-6.230000,106.850000', 18.20),
(3, 'Zona Permukiman Utara', 'North Residential', '-6.170000,106.820000', 15.75),
(4, 'Zona Perkantoran', 'Business District', '-6.210000,106.830000', 10.40),
(5, 'Zona Sekolah dan Publik', 'Public Facilities', '-6.190000,106.800000', 9.80)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    city_district = VALUES(city_district),
    coordinates = VALUES(coordinates),
    area_km2 = VALUES(area_km2);

INSERT INTO env_weather (zone_id, temperature, humidity, wind_speed, wind_direction, recorded_at) VALUES
(1, 31.50, 72.00, 8.20, 90.00, NOW()),
(2, 33.20, 68.00, 5.50, 120.00, NOW()),
(3, 30.80, 76.00, 7.40, 80.00, NOW()),
(4, 32.10, 70.00, 6.10, 135.00, NOW()),
(5, 29.90, 78.00, 9.00, 100.00, NOW());
