<?php

namespace App\Config;

use PDO;

// Koneksi ke database menggunakan PDO.
final class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            // Nilai default dipakai untuk lokal, sedangkan Docker dapat mengisi lewat environment.
            $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
            $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
            $name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'smartcity';
            $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
            $pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';

            self::$connection = new PDO(
                "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                [
                    // Query yang gagal langsung melempar exception agar bisa ditangani controller.
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }

        return self::$connection;
    }

    // Memeriksa koneksi ke database.
    public static function healthCheck(): bool
    {
        return (int) self::getConnection()->query('SELECT 1')->fetchColumn() === 1;
    }
}