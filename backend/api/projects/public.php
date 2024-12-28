<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get only deployed projects with valid URLs
    $query = "SELECT p.id, p.name, p.description, p.url, p.created_at, p.updated_at 
              FROM projects p 
              WHERE p.status = 'deployed' AND p.url IS NOT NULL AND p.url != ''
              ORDER BY p.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute();

    $projects = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $project = array(
            "id" => $row['id'],
            "name" => $row['name'],
            "description" => $row['description'],
            "url" => $row['url'],
            "created_at" => $row['created_at'],
            "updated_at" => $row['updated_at']
        );
        $projects[] = $project;
    }

    // Debug log
    error_log("Projects found: " . count($projects));
    error_log("Projects data: " . json_encode($projects));

    echo json_encode(array(
        "success" => true,
        "data" => $projects
    ));

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(array(
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ));
} catch (Exception $e) {
    error_log("Server error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(array(
        "success" => false,
        "message" => "Server error: " . $e->getMessage()
    ));
}
