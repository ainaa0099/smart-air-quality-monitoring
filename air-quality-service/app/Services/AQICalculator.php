<?php

namespace App\Services;

final class AQICalculator
{
    // Format tiap breakpoint: batas konsentrasi bawah/atas dan indeks bawah/atas.
    private const BREAKPOINTS = [
        'pm25' => [[0, 15.5, 0, 50], [15.6, 55.4, 51, 100], [55.5, 150.4, 101, 200], [150.5, 250.4, 201, 300], [250.5, 500, 301, 500]],
        'pm10' => [[0, 50, 0, 50], [51, 150, 51, 100], [151, 350, 101, 200], [351, 420, 201, 300], [421, 600, 301, 500]],
        'no2' => [[0, 80, 0, 50], [81, 200, 51, 100], [201, 1130, 101, 200], [1131, 2260, 201, 300], [2261, 3000, 301, 500]],
        'co' => [[0, 5, 0, 50], [5.1, 10, 51, 100], [10.1, 17, 101, 200], [17.1, 34, 201, 300], [34.1, 57.5, 301, 500]],
        'o3' => [[0, 120, 0, 50], [121, 235, 51, 100], [236, 400, 101, 200], [401, 800, 201, 300], [801, 1000, 301, 500]],
    ];

    // Menghitung indeks AQI
    public static function calculate(array $reading): array
    {
        $indexes = [];
        foreach (self::BREAKPOINTS as $pollutant => $breakpoints) {
            $indexes[$pollutant] = self::subIndex((float) $reading[$pollutant], $breakpoints);
        }

        // AQI akhir mengikuti polutan dengan indeks paling tinggi.
        $value = min(500, (int) round(max($indexes)));

        return [
            'value' => $value,
            'category' => self::category($value),
            'dominant_pollutant' => array_search(max($indexes), $indexes, true),
        ];
    }

    // Mencari indeks di dalam rentang breakpoint
    private static function subIndex(float $value, array $breakpoints): float
    {
        foreach ($breakpoints as [$cLow, $cHigh, $iLow, $iHigh]) {
            if ($value <= $cHigh) {
                // Interpolasi linier untuk mencari indeks di dalam rentang breakpoint.
                return (($iHigh - $iLow) / ($cHigh - $cLow)) * ($value - $cLow) + $iLow;
            }
        }
        return 500;
    }

    // Mencari kategori berdasarkan indeks
    private static function category(int $value): string
    {
        return match (true) {
            $value <= 50 => 'Baik',
            $value <= 100 => 'Sedang',
            $value <= 200 => 'Tidak Sehat',
            $value <= 300 => 'Sangat Tidak Sehat',
            default => 'Berbahaya',
        };
    }
}
