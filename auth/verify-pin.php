<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in'
    ]);
    exit();
}

// Check if PIN is provided
if (!isset($_POST['pin']) || empty($_POST['pin'])) {
    echo json_encode([
        'success' => false,
        'message' => 'PIN is required'
    ]);
    exit();
}

$pin = trim($_POST['pin']);
$user_id = $_SESSION['user_id'];

// Validate PIN format (6 digits)
if (!preg_match('/^\d{6}$/', $pin)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid PIN format'
    ]);
    exit();
}

// Check if user has Admin role and verify PIN
$sql = "SELECT u.user_id, u.full_name, u.pin, r.role_name 
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        WHERE u.user_id = ? AND u.status = 'active'";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    echo json_encode([
        'success' => false,
        'message' => 'User not found'
    ]);
    exit();
}

// Check if user is Admin
if ($user['role_name'] !== 'Admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Admin privileges required'
    ]);
    exit();
}

// Check if PIN is set
if (empty($user['pin'])) {
    echo json_encode([
        'success' => false,
        'message' => 'PIN not set. Please contact administrator.'
    ]);
    exit();
}

// Verify PIN (using password_verify if hashed, or direct comparison)
$pin_valid = false;

// Check if PIN is hashed (starts with $2y$ which is bcrypt)
if (substr($user['pin'], 0, 4) === '$2y$') {
    $pin_valid = password_verify($pin, $user['pin']);
} else {
    // Direct comparison for plain text PIN (not recommended for production)
    $pin_valid = ($pin === $user['pin']);
}

if ($pin_valid) {
    // PIN verified successfully
    // Store verification in session with timestamp
    $_SESSION['pin_verified'] = true;
    $_SESSION['pin_verified_at'] = time();
    $_SESSION['pin_verified_user'] = $user['user_id'];
    
    // Log the PIN verification
    $log_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) 
                VALUES (?, 'PIN_VERIFY', 'users', ?, 'Admin PIN verified for sensitive operation')";
    $log_stmt = mysqli_prepare($conn, $log_sql);
    mysqli_stmt_bind_param($log_stmt, "ii", $user_id, $user_id);
    mysqli_stmt_execute($log_stmt);
    
    echo json_encode([
        'success' => true,
        'message' => 'PIN verified successfully',
        'user' => [
            'id' => $user['user_id'],
            'name' => $user['full_name'],
            'role' => $user['role_name']
        ]
    ]);
} else {
    // Invalid PIN
    // Log failed attempt
    $log_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) 
                VALUES (?, 'PIN_VERIFY_FAILED', 'users', ?, 'Failed PIN verification attempt')";
    $log_stmt = mysqli_prepare($conn, $log_sql);
    mysqli_stmt_bind_param($log_stmt, "ii", $user_id, $user_id);
    mysqli_stmt_execute($log_stmt);
    
    echo json_encode([
        'success' => false,
        'message' => 'Invalid PIN'
    ]);
}

mysqli_close($conn);
?>