<?php
header('Content-Type: application/json; charset=utf-8');
// This endpoint returns current session status for client-side checks
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database functions (which contain token helpers)
require_once __DIR__ . '/../config/database.php';

// Do NOT include check-auth.php here - that file may redirect to login.php
// if session is missing. We want to return JSON instead.

$logged = false;

// Validate session existence and token if helper exists
if (isset($_SESSION['user_id']) && function_exists('validate_session_token')) {
    try {
        if (validate_session_token()) {
            $logged = true;
        }
    } catch (Throwable $e) {
        // treat as not logged if token validation fails
        $logged = false;
    }
}

echo json_encode(['logged' => $logged]);
exit();
