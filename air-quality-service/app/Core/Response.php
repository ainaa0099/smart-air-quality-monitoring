<?php

namespace App\Core;

final class Response
{
    // Membuat response JSON
    public static function json(mixed $data, int $code = 200, string $message = 'Success'): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => $code < 400 ? 'success' : 'error',
            'code' => $code,
            'data' => $data,
            'message' => $message,
            'timestamp' => gmdate('c'),
            'service' => 'air-quality-service',
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
}
