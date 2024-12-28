<?php
header("Access-Control-Allow-Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, Cookie");
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

error_log('Session data in change-password: ' . print_r($_SESSION, true));
error_log('Request method: ' . $_SERVER['REQUEST_METHOD']);
error_log('Request body: ' . file_get_contents("php://input"));

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    error_log('No user session found in change-password');
    http_response_code(401);
    echo json_encode(array("message" => "Unauthorized"));
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    error_log('Database connection error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(array("message" => "Database connection failed"));
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (!$data) {
    error_log('Invalid JSON data received');
    http_response_code(400);
    echo json_encode(array("message" => "Invalid request data"));
    exit();
}

if (empty($data->currentPassword) || empty($data->newPassword)) {
    error_log('Missing password data');
    http_response_code(400);
    echo json_encode(array("message" => "Current password and new password are required"));
    exit();
}

try {
    // Get current user's password
    $query = "SELECT password FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $_SESSION['user']['id']);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify current password
        if (password_verify($data->currentPassword, $row['password'])) {
            // Update password
            $update_query = "UPDATE users SET password = :password WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            
            $new_password_hash = password_hash($data->newPassword, PASSWORD_BCRYPT);
            
            $update_stmt->bindParam(":password", $new_password_hash);
            $update_stmt->bindParam(":id", $_SESSION['user']['id']);
            
            if ($update_stmt->execute()) {
                http_response_code(200);
                echo json_encode(array("message" => "Password updated successfully"));
            } else {
                throw new Exception("Failed to update password");
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Current password is incorrect"));
        }
    } else {
        throw new Exception("User not found");
    }
} catch (Exception $e) {
    error_log('Error in change-password: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(array("message" => "Failed to change password: " . $e->getMessage()));
}
