<?php
// ============================================================
// ENTERPRISE HRMS - DATABASE CONNECTION (PDO Singleton)
// ============================================================

class Database {
    private static ?Database $instance = null;
    private PDO $pdo;

    private string $host     = 'localhost';
    private string $dbname   = 'hrms_db';
    private string $username = 'root';
    private string $password = '';
    private string $charset  = 'utf8mb4';
    private int    $port     = 3306;

    private function __construct() {
        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset={$this->charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset} COLLATE utf8mb4_unicode_ci",
            PDO::ATTR_PERSISTENT         => false,
        ];
        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Database connection failed', 'error' => APP_ENV === 'development' ? $e->getMessage() : 'Service unavailable']);
            exit;
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }

    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void           { $this->pdo->commit(); }
    public function rollBack(): void         { $this->pdo->rollBack(); }

    // Prevent cloning / serialization
    private function __clone() {}
    public function __wakeup() { throw new Exception("Cannot unserialize singleton."); }
}

// Convenience function
function db(): PDO {
    return Database::getInstance()->getConnection();
}
