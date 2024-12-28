<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';

class UserController {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function updateUser($data) {
        try {
            // Validate required fields
            if (!isset($data->id) || !isset($data->username) || !isset($data->email) || !isset($data->role)) {
                return json_encode([
                    "status" => "error",
                    "message" => "Missing required fields"
                ]);
            }

            // Validate role
            if (!in_array($data->role, ['user', 'admin'])) {
                return json_encode([
                    "status" => "error",
                    "message" => "Invalid role"
                ]);
            }

            // Check if email is already taken by another user
            $emailCheckQuery = "SELECT id FROM users WHERE email = ? AND id != ?";
            $emailStmt = $this->conn->prepare($emailCheckQuery);
            $emailStmt->execute([$data->email, $data->id]);
            if ($emailStmt->rowCount() > 0) {
                return json_encode([
                    "status" => "error",
                    "message" => "Email already exists"
                ]);
            }

            // Update user
            $query = "UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                $data->username,
                $data->email,
                $data->role,
                $data->id
            ]);

            if ($stmt->rowCount() > 0) {
                return json_encode([
                    "status" => "success",
                    "message" => "User updated successfully"
                ]);
            } else {
                return json_encode([
                    "status" => "error",
                    "message" => "No changes made or user not found"
                ]);
            }
        } catch(PDOException $e) {
            return json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]);
        }
    }
}

// Get posted data
$data = json_decode(file_get_contents("php://input"));

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Create user controller instance and update user
$userController = new UserController($db);
echo $userController->updateUser($data);
