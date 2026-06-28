<?php

class Database
{
    public static function connect(): PDO
    {
        try {
            // Nilai default dipakai untuk lokal, sedangkan Docker mengisi lewat environment.
            $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
            $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
            $name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'smartcity';
            $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
            $pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';

            return new PDO(
                "mysql:host=" . $host .
                ";port=" . $port .
                ";dbname=" . $name .
                ";charset=utf8",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            die("Koneksi gagal: " . $e->getMessage());
        }
    }
}