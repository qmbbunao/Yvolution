<?php
/**
 * Database connection (PDO singleton).
 * Update the credentials below to match your local XAMPP / phpMyAdmin setup.
 */
class Database
{
    private static ?PDO $pdo = null;

    private const HOST = '127.0.0.1';
    private const DBNAME = 'yvolution_db';
    private const USER = 'root';
    private const PASS = '';       // XAMPP default has no password
    private const CHARSET = 'utf8mb4';

    public static function connect(): PDO
    {
        if (self::$pdo === null) {
            $dsn = "mysql:host=" . self::HOST . ";dbname=" . self::DBNAME . ";charset=" . self::CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$pdo = new PDO($dsn, self::USER, self::PASS, $options);
            } catch (PDOException $e) {
                // Never leak connection details to the browser
                error_log('DB Connection failed: ' . $e->getMessage());
                die('Database connection failed. Please check your configuration or contact the system administrator.');
            }
        }

        return self::$pdo;
    }
}
