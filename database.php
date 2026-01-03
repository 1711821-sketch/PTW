<?php
// Include this file using: require_once 'database.php';

// Load local environment variables if available
require_once __DIR__ . '/config/load_env.php';

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            // Get environment variables (try $_ENV first, then getenv())
            $host = $_ENV['PGHOST'] ?? getenv('PGHOST');
            $port = $_ENV['PGPORT'] ?? getenv('PGPORT');
            $dbname = $_ENV['PGDATABASE'] ?? getenv('PGDATABASE');
            $username = $_ENV['PGUSER'] ?? getenv('PGUSER');
            $password = $_ENV['PGPASSWORD'] ?? getenv('PGPASSWORD');

            if ($host && $port && $dbname && $username && $password) {
                $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;connect_timeout=5";
                $this->connection = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 5,
                ]);
            }
            // Fallback to DATABASE_URL if individual vars not available
            elseif (isset($_ENV['DATABASE_URL']) || getenv('DATABASE_URL')) {
                $url = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
                $this->connection = new PDO($url, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } else {
                throw new Exception("No database connection information available");
            }
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
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
        $sql = "INSERT INTO time_entries (work_order_id, user_id, entry_date, hours, description)
                VALUES (?, ?, ?, ?, ?)
                ON CONFLICT (work_order_id, user_id, entry_date)
                DO UPDATE SET
                    hours = EXCLUDED.hours,
                    description = EXCLUDED.description,
                    updated_at = CURRENT_TIMESTAMP";

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
