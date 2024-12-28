<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start();

if (isset($_SESSION['user'])) {
    http_response_code(200);
    echo json_encode(array(
        "message" => "Session is valid",
        "user" => array(
            "id" => $_SESSION['user']['id'],
            "username" => $_SESSION['user']['username'],
            "email" => $_SESSION['user']['email'],
            "role" => $_SESSION['user']['role']
        )
    ));
} else {
    http_response_code(401);
    echo json_encode(array(
        "message" => "No active session"
    ));
}
