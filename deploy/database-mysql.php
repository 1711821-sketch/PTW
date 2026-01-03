<?php
// MySQL-compatible database.php
// This file replaces database.php on MySQL servers

// Load local environment variables if available
require_once __DIR__ . '/../config/load_env.php';

class Database {
    private static $instance = null;
    private $connection;
    private $driver = 'mysql';

    private function __construct() {
        try {
            // Determine database driver from environment
            $dbDriver = $_ENV['DB_DRIVER'] ?? getenv('DB_DRIVER') ?? 'mysql';
            $this->driver = $dbDriver;

            if ($dbDriver === 'pgsql') {
                // PostgreSQL connection
                $host = $_ENV['PGHOST'] ?? getenv('PGHOST');
                $port = $_ENV['PGPORT'] ?? getenv('PGPORT') ?? '5432';
                $dbname = $_ENV['PGDATABASE'] ?? getenv('PGDATABASE');
                $username = $_ENV['PGUSER'] ?? getenv('PGUSER');
                $password = $_ENV['PGPASSWORD'] ?? getenv('PGPASSWORD');

                if ($host && $dbname && $username && $password) {
                    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;connect_timeout=5";
                    $this->connection = new PDO($dsn, $username, $password, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_TIMEOUT => 5,
                    ]);
                } else {
                    throw new Exception("PostgreSQL connection info incomplete");
                }
            } else {
                // MySQL connection (default)
                $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? 'localhost';
                $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? '3306';
                $dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
                $username = $_ENV['DB_USER'] ?? getenv('DB_USER');
                $password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');

                if ($dbname && $username) {
                    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
                    $this->connection = new PDO($dsn, $username, $password, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    ]);
                } else {
                    throw new Exception("MySQL connection info incomplete");
                }
            }
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    public function getDriver() {
        return $this->driver;
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }

    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function execute($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $result = $stmt->execute($params);
            return $result;
        } catch (PDOException $e) {
            throw new Exception("Execute failed: " . $e->getMessage());
        }
    }

    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
}

// Time Entry functions
class TimeEntry {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function addTimeEntry($workOrderId, $userId, $date, $hours, $description = '') {
        $driver = $this->db->getDriver();

        if ($driver === 'pgsql') {
            $sql = "INSERT INTO time_entries (work_order_id, user_id, entry_date, hours, description)
                    VALUES (?, ?, ?, ?, ?)
                    ON CONFLICT (work_order_id, user_id, entry_date)
                    DO UPDATE SET
                        hours = EXCLUDED.hours,
                        description = EXCLUDED.description,
                        updated_at = CURRENT_TIMESTAMP";
        } else {
            $sql = "INSERT INTO time_entries (work_order_id, user_id, entry_date, hours, description)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        hours = VALUES(hours),
                        description = VALUES(description),
                        updated_at = CURRENT_TIMESTAMP";
        }

        return $this->db->query($sql, [$workOrderId, $userId, $date, $hours, $description]);
    }

    public function getTimeEntriesForWorkOrder($workOrderId) {
        $sql = "SELECT te.*, u.username, u.entreprenor_firma
                FROM time_entries te
                JOIN users u ON te.user_id = u.id
                WHERE te.work_order_id = ?
                ORDER BY te.entry_date DESC";

        return $this->db->fetchAll($sql, [$workOrderId]);
    }

    public function getTotalHoursForWorkOrder($workOrderId) {
        $sql = "SELECT COALESCE(SUM(hours), 0) as total_hours
                FROM time_entries
                WHERE work_order_id = ?";

        $result = $this->db->fetch($sql, [$workOrderId]);
        return $result['total_hours'];
    }

    public function getUserTimeEntriesForWorkOrder($workOrderId, $userId) {
        $sql = "SELECT * FROM time_entries
                WHERE work_order_id = ? AND user_id = ?
                ORDER BY entry_date DESC";

        return $this->db->fetchAll($sql, [$workOrderId, $userId]);
    }

    public function getTimeEntryForDate($workOrderId, $userId, $date) {
        $sql = "SELECT * FROM time_entries
                WHERE work_order_id = ? AND user_id = ? AND entry_date = ?";

        return $this->db->fetch($sql, [$workOrderId, $userId, $date]);
    }
}
?>
