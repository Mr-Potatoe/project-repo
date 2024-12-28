<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';

class SettingController {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getSettings() {
        try {
            $query = "SELECT * FROM settings ORDER BY `key` ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                array_push($settings, $row);
            }

            return json_encode([
                "status" => "success",
                "data" => $settings
            ]);
        } catch(PDOException $e) {
            return json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]);
        }
    }

    public function createSetting($data) {
        try {
            // Check if key already exists
            $query = "SELECT id FROM settings WHERE `key` = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$data->key]);
            if ($stmt->rowCount() > 0) {
                return json_encode([
                    "status" => "error",
                    "message" => "Setting key already exists"
                ]);
            }

            // Insert new setting
            $query = "INSERT INTO settings (`key`, value) VALUES (?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$data->key, $data->value]);

            if ($stmt->rowCount() > 0) {
                $id = $this->conn->lastInsertId();
                return json_encode([
                    "status" => "success",
                    "data" => [
                        "id" => $id,
                        "key" => $data->key,
                        "value" => $data->value
                    ]
                ]);
            }

            return json_encode([
                "status" => "error",
                "message" => "Failed to create setting"
            ]);
        } catch(PDOException $e) {
            return json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]);
        }
    }

    public function updateSetting($id, $data) {
        try {
            // Check if key already exists for other settings
            $query = "SELECT id FROM settings WHERE `key` = ? AND id != ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$data->key, $id]);
            if ($stmt->rowCount() > 0) {
                return json_encode([
                    "status" => "error",
                    "message" => "Setting key already exists"
                ]);
            }

            // Update setting
            $query = "UPDATE settings SET `key` = ?, value = ? WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$data->key, $data->value, $id]);

            if ($stmt->rowCount() > 0) {
                return json_encode([
                    "status" => "success",
                    "message" => "Setting updated successfully"
                ]);
            }

            return json_encode([
                "status" => "error",
                "message" => "Setting not found or no changes made"
            ]);
        } catch(PDOException $e) {
            return json_encode([
                "status" => "error",
                "message" => $e->getMessage()
            ]);
        }
    }

    public function deleteSetting($id) {
        try {
            $query = "DELETE FROM settings WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                return json_encode([
                    "status" => "success",
                    "message" => "Setting deleted successfully"
                ]);
            }

            return json_encode([
                "status" => "error",
                "message" => "Setting not found"
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

// Create setting controller instance
$settingController = new SettingController($db);

// Handle request
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"));

switch ($method) {
    case 'GET':
        echo $settingController->getSettings();
        break;
    case 'POST':
        echo $settingController->createSetting($data);
        break;
    case 'PUT':
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        if ($id) {
            echo $settingController->updateSetting($id, $data);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Setting ID is required"
            ]);
        }
        break;
    case 'DELETE':
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        if ($id) {
            echo $settingController->deleteSetting($id);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Setting ID is required"
            ]);
        }
        break;
}
