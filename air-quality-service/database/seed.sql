USE smartcity;

INSERT INTO zones (id, name, city_district, coordinates, area_km2) VALUES
(1, 'Zone 1', 'Jakarta Pusat', '-6.186486,106.834091', 48.13),
(2, 'Zone 2', 'Jakarta Utara', '-6.138414,106.863983', 146.66),
(3, 'Zone 3', 'Jakarta Selatan', '-6.261493,106.810600', 141.27),
(4, 'Zone 4', 'Jakarta Timur', '-6.225014,106.900447', 188.03),
(5, 'Zone 5', 'Jakarta Barat', '-6.168329,106.758849', 129.54)
ON DUPLICATE KEY UPDATE city_district = VALUES(city_district);

INSERT INTO air_stations (zone_id, station_name, station_code, latitude, longitude) VALUES
(1, 'Stasiun Jakarta Pusat', 'AQ-JKT-PST', -6.1864860, 106.8340910),
(2, 'Stasiun Jakarta Utara', 'AQ-JKT-UTR', -6.1384140, 106.8639830),
(3, 'Stasiun Jakarta Selatan', 'AQ-JKT-SLT', -6.2614930, 106.8106000),
(4, 'Stasiun Jakarta Timur', 'AQ-JKT-TMR', -6.2250140, 106.9004470),
(5, 'Stasiun Jakarta Barat', 'AQ-JKT-BRT', -6.1683290, 106.7588490)
ON DUPLICATE KEY UPDATE station_name = VALUES(station_name), is_active = TRUE;