<?php
/**
 * Database Configuration
 */
class Database {
    private $host;
    private $dbName;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        $this->host = getenv('DB_HOST') ?: 'db';
        $this->dbName = getenv('DB_NAME') ?: 'library_db';
        $this->username = getenv('DB_USER') ?: 'library_user';
        $this->password = getenv('DB_PASSWORD') ?: 'library_password';
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->dbName,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            echo "Connection error: " . $e->getMessage();
        }

        return $this->conn;
    }
}