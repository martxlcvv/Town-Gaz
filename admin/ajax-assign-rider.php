<?php
// AJAX Handler - Enable errors temporarily for debugging
error_reporting(E_ALL);
// Do not display PHP errors in AJAX JSON responses; log instead
ini_set('display_errors', 0);

// Start clean output buffer
ob_start();

// Start session
session_start();

// Include database - check if file exists
if (!file_exists('../config/database.php')) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database config file not found']);
    exit();
}

require_once '../config/database.php';

// Check if database connection exists
if (!isset($conn)) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Clear any accidental output
ob_end_clean();

// Set JSON header immediately
header('Content-Type: application/json');

// Check if this is an AJAX request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

// Determine user role - prefer session value, fallback to DB join with `roles`
$user_id = (int)$_SESSION['user_id'];
$user_role = '';
if (isset($_SESSION['role_name'])) {
    $user_role = strtolower(trim($_SESSION['role_name']));
} else {
    $role_check_sql = "SELECT r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ?";
    $stmt = mysqli_prepare($conn, $role_check_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $user_data = mysqli_fetch_assoc($result);
        $user_role = strtolower(trim($user_data['role_name']));
    } else {
        echo json_encode(['success' => false, 'message' => 'User verification failed']);
        exit();
    }
}

// Allow admin and staff only
if ($user_role !== 'admin' && $user_role !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Only admin and staff can assign riders.']);
    exit();
}

// Handle rider assignment
if (isset($_POST['assign_rider'])) {
    $delivery_id = (int)$_POST['delivery_id'];
    $rider_id = (int)$_POST['rider_id'];
    
    if ($delivery_id <= 0 || $rider_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid delivery or rider ID']);
        exit();
    }
    
    $sql = "UPDATE deliveries SET rider_id = ?, delivery_status = 'delivered', updated_at = NOW() WHERE delivery_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $rider_id, $delivery_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Try to log audit if function exists
        if (function_exists('log_audit')) {
            log_audit($user_id, 'UPDATE', 'deliveries', $delivery_id, null, 
                     ['rider_assigned' => $rider_id, 'status' => 'delivered']);
        }
        echo json_encode(['success' => true, 'message' => 'Rider assigned successfully and marked as delivered!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
    exit();
}

// Handle status update
if (isset($_POST['update_status'])) {
    $delivery_id = (int)$_POST['delivery_id'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $old_sql = "SELECT delivery_status FROM deliveries WHERE delivery_id = ?";
    $stmt = mysqli_prepare($conn, $old_sql);
    mysqli_stmt_bind_param($stmt, "i", $delivery_id);
    mysqli_stmt_execute($stmt);
    $old_result = mysqli_stmt_get_result($stmt);
    $old_data = mysqli_fetch_assoc($old_result);
    
    $sql = "UPDATE deliveries SET delivery_status = ?, updated_at = NOW() WHERE delivery_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $status, $delivery_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Try to log audit if function exists
        if (function_exists('log_audit')) {
            log_audit($user_id, 'UPDATE', 'deliveries', $delivery_id, $old_data, ['status' => $status]);
        }
        echo json_encode(['success' => true, 'message' => 'Delivery status updated successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
    exit();
}

// If no valid action
echo json_encode(['success' => false, 'message' => 'No valid action specified']);
exit();