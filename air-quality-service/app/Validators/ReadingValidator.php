<?php

namespace App\Validators;

final class ReadingValidator
{
    // Validasi data sensor
    public static function validate(array $data): array
    {
        $errors = [];

        // Semua polutan wajib ada karena dipakai bersama-sama saat menghitung AQI.
        foreach (['zone_id', 'pm25', 'pm10', 'no2', 'co', 'o3'] as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === '') {
                $errors[$field] = "{$field} wajib diisi";
            } elseif (!is_numeric($data[$field])) {
                $errors[$field] = "{$field} harus berupa angka";
            } elseif ((float) $data[$field] < 0) {
                $errors[$field] = "{$field} tidak boleh negatif";
            }
        }
        if (isset($data['zone_id']) && (!filter_var($data['zone_id'], FILTER_VALIDATE_INT) || (int) $data['zone_id'] < 1)) {
            $errors['zone_id'] = 'zone_id harus berupa integer positif';
        }
        if (isset($data['station_id']) && $data['station_id'] !== null && !filter_var($data['station_id'], FILTER_VALIDATE_INT)) {
            $errors['station_id'] = 'station_id harus berupa integer';
        }
        if (isset($data['recorded_at']) && strtotime((string) $data['recorded_at']) === false) {
            $errors['recorded_at'] = 'recorded_at harus berupa tanggal yang valid';
        }
        return $errors;
    }
}
