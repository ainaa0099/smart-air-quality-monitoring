<?php

class Database
{
    public static function connect(): PDO
    {
        try {
            return new PDO(
                "mysql:host=localhost;dbname=data;charset=utf8",
                "root",
                "545454"
            );
        } catch (PDOException $e) {
            die("Koneksi gagal: " . $e->getMessage());
        }
    }
}