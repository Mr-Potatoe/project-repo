<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start();

if (isset($_SESSION['user'])) {
    // Clear session data
    session_unset();
    session_destroy();
    
    http_response_code(200);
    echo json_encode(array(
        "message" => "Logged out successfully"
    ));
} else {
    http_response_code(401);
    echo json_encode(array(
        "message" => "No active session found"
    ));
}
