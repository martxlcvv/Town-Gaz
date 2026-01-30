<?php
/**
 * PIN Verification API Endpoint
 * Verifies PIN for sensitive operations (works for both admin and staff)
 */

session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get PIN from request (handle both JSON and form data)
$pin = '';

// Try to get PIN from JSON first
if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $json_data = json_decode(file_get_contents('php://input'), true);
    $pin = isset($json_data['pin']) ? trim($json_data['pin']) : '';
} else {
    // Fall back to POST data
    $pin = isset($_POST['pin']) ? trim($_POST['pin']) : '';
}

if (empty($pin)) {
    echo json_encode(['success' => false, 'message' => 'PIN is required']);
    exit;
}

// Get user PIN from admin_pins table (unified system)
$user_id = $_SESSION['user_id'];
$sql = "SELECT pin_hash FROM admin_pins WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pin_data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$pin_data || empty($pin_data['pin_hash'])) {
    echo json_encode(['success' => false, 'message' => 'PIN not configured for this user']);
    exit;
}

// Verify PIN using password_verify
$pin_verified = password_verify($pin, $pin_data['pin_hash']);

if ($pin_verified) {
    // Log successful PIN verification if log_audit function exists
    if (function_exists('log_audit')) {
        log_audit($_SESSION['user_id'], 'PIN_VERIFIED', 'admin_pins', $user_id, null, 
                 ['action' => 'PIN verification successful']);
    }
    
    echo json_encode(['success' => true, 'message' => 'PIN verified successfully']);
} else {
    // Log failed PIN verification attempt if log_audit function exists
    if (function_exists('log_audit')) {
        log_audit($_SESSION['user_id'], 'PIN_FAILED', 'admin_pins', $user_id, null, 
                 ['action' => 'PIN verification failed']);
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid PIN']);
}

