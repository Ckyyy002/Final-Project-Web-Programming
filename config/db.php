<?php
// config/db.php
require_once __DIR__ . '/constants.php';

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            // Don't expose database errors to users
            die("Database connection error. Please try again later.");
        }
    }
    
    public static function getConnection() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->pdo;
    }
    
    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() {}
    
    // Helper method for prepared statements
    public static function execute($sql, $params = []) {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
?>
