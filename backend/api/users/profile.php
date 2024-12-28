<?php
header("Access-Control-Allow-Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, PUT, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../../config/database.php';

// Start session and set cookie parameters
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);
session_start();

error_log('Session data: ' . print_r($_SESSION, true)); // Debug session

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    error_log('No user session found'); // Debug session
    http_response_code(401);
    echo json_encode(array("message" => "Unauthorized"));
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    error_log('Database connection error: ' . $e->getMessage()); // Debug database
    http_response_code(500);
    echo json_encode(array("message" => "Database connection failed"));
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
error_log('Request method: ' . $method); // Debug method

switch ($method) {
    case 'GET':
        try {
            $query = "SELECT id, username, email, role, created_at FROM users WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":id", $_SESSION['user']['id']);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                http_response_code(200);
                echo json_encode(array(
                    "message" => "Profile retrieved successfully",
                    "data" => $user
                ));
            } else {
                error_log('User not found: ' . $_SESSION['user']['id']); // Debug user
                http_response_code(404);
                echo json_encode(array("message" => "User not found"));
            }
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage()); // Debug database
            http_response_code(503);
            echo json_encode(array("message" => "Unable to retrieve profile: " . $e->getMessage()));
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));
        error_log('PUT data: ' . print_r($data, true)); // Debug input
        
        if (empty($data)) {
            http_response_code(400);
            echo json_encode(array("message" => "No data provided"));
            exit();
        }

        try {
            $updates = array();
            $params = array();
            
            // Handle username update
            if (!empty($data->username)) {
                // Check if username is already taken
                $check = $db->prepare("SELECT id FROM users WHERE username = :username AND id != :id");
                $check->execute([
                    ':username' => $data->username,
                    ':id' => $_SESSION['user']['id']
                ]);
                
                if ($check->rowCount() > 0) {
                    http_response_code(400);
                    echo json_encode(array("message" => "Username already taken"));
                    exit();
                }
                
                $updates[] = "username = :username";
                $params[':username'] = $data->username;
            }
            
            // Handle email update
            if (!empty($data->email)) {
                // Check if email is already taken
                $check = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
                $check->execute([
                    ':email' => $data->email,
                    ':id' => $_SESSION['user']['id']
                ]);
                
                if ($check->rowCount() > 0) {
                    http_response_code(400);
                    echo json_encode(array("message" => "Email already taken"));
                    exit();
                }
                
                $updates[] = "email = :email";
                $params[':email'] = $data->email;
            }
            
            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(array("message" => "No valid updates provided"));
                exit();
            }
            
            $params[':id'] = $_SESSION['user']['id'];
            $query = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = :id";
            error_log('Update query: ' . $query); // Debug query
            
            $stmt = $db->prepare($query);
            if ($stmt->execute($params)) {
                // Update session data if username or email was changed
                if (isset($params[':username'])) {
                    $_SESSION['user']['username'] = $params[':username'];
                }
                if (isset($params[':email'])) {
                    $_SESSION['user']['email'] = $params[':email'];
                }
                
                http_response_code(200);
                echo json_encode(array(
                    "message" => "Profile updated successfully",
                    "user" => array(
                        "id" => $_SESSION['user']['id'],
                        "username" => $_SESSION['user']['username'],
                        "email" => $_SESSION['user']['email'],
                        "role" => $_SESSION['user']['role']
                    )
                ));
            } else {
                error_log('Update failed: ' . print_r($stmt->errorInfo(), true)); // Debug update
                http_response_code(503);
                echo json_encode(array("message" => "Unable to update profile"));
            }
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage()); // Debug database
            http_response_code(503);
            echo json_encode(array("message" => "Database error: " . $e->getMessage()));
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method not allowed"));
        break;
}
