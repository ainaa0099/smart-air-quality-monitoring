<?php

class Database
{
    public static function connect(): PDO
    {
        try {
            return new PDO(
                "mysql:host=" . $_ENV['DB_HOST'] .
                ";dbname=" . $_ENV['DB_NAME'] .
                ";charset=utf8",
                $_ENV['DB_USER'],
                $_ENV['DB_PASS']
            );
        } catch (PDOException $e) {
            die("Koneksi gagal: " . $e->getMessage());
        }
    }
}