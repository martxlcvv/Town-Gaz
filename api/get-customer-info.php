<?php
/**
 * Get Customer Information API
 * Returns customer details (name, contact, address)
 */

// Set JSON header FIRST - before ANY code
header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Connect to database
require_once '../config/database.php';

// Get customer ID from request
$customer_id = isset($_GET['id']) ? intval(trim($_GET['id'])) : 0;

if (empty($customer_id) || $customer_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
    exit;
}

// Get customer information from database
$sql = "SELECT customer_id, customer_name, contact, address FROM customers WHERE customer_id = ?";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($customer) {
    echo json_encode([
        'success' => true,
        'customer' => [
            'customer_id' => $customer['customer_id'],
            'customer_name' => $customer['customer_name'],
            'contact' => $customer['contact'] ?? '',
            'address' => $customer['address'] ?? ''
        ]
    ]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Customer not found']);
}
