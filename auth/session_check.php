<?php
// Simple session check endpoint used by client-side scripts
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit();
} else {
    http_response_code(401);
    echo json_encode(['status' => 'unauthorized']);
    exit();
}
