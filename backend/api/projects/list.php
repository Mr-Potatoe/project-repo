<?php
header("Access-Control-Allow-Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../../config/database.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(array("message" => "Unauthorized"));
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get projects for the current user
    $query = "SELECT p.*, d.log_message as latest_log 
              FROM projects p 
              LEFT JOIN (
                  SELECT project_id, log_message, 
                         ROW_NUMBER() OVER (PARTITION BY project_id ORDER BY timestamp DESC) as rn
                  FROM deployment_logs
              ) d ON p.id = d.project_id AND d.rn = 1
              WHERE p.user_id = :user_id
              ORDER BY p.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $_SESSION['user']['id']);
    $stmt->execute();

    $projects = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $project = array(
            "id" => $row['id'],
            "name" => $row['name'],
            "description" => $row['description'],
            "database_name" => $row['database_name'],
            "upload_path" => $row['upload_path'],
            "status" => $row['status'],
            "url" => $row['url'],
            "created_at" => $row['created_at'],
            "updated_at" => $row['updated_at'],
            "latest_log" => $row['latest_log']
        );
        $projects[] = $project;
    }

    http_response_code(200);
    echo json_encode(array(
        "message" => "Projects retrieved successfully",
        "data" => $projects
    ));
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(array(
        "message" => "Unable to retrieve projects",
        "error" => $e->getMessage()
    ));
}
