<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../../config/database.php';

class LogController {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getLogs() {
        try {
            $query = "
                SELECT 
                    dl.*,
                    p.name as project_name
                FROM deployment_logs dl
                LEFT JOIN projects p ON dl.project_id = p.id
                ORDER BY dl.timestamp DESC
            ";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            $logs = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                array_push($logs, $row);
            }

            return json_encode([
                "status" => "success",
                "data" => $logs
            ]);
        } catch(PDOException $e) {
            return json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]);
        }
    }
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Create log controller instance and get logs
$logController = new LogController($db);
echo $logController->getLogs();
