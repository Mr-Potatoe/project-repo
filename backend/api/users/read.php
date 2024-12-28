<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../../config/database.php';

class UserController {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getUsers() {
        try {
            $query = "SELECT id, username, email, role, created_at FROM users";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            $users = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                array_push($users, $row);
            }

            return json_encode([
                "status" => "success",
                "data" => $users
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

// Create user controller instance and get users
$userController = new UserController($db);
echo $userController->getUsers();
