<?php
/**
 * Get Customer Information API
 * Returns customer details (name, contact, address)
 */

session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get customer ID from request
$customer_id = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($customer_id)) {
    echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
    exit;
}

// Get customer information from database
$sql = "SELECT customer_id, customer_name, contact, address FROM customers WHERE customer_id = ?";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
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
            'customer_name' => htmlspecialchars($customer['customer_name']),
            'contact' => htmlspecialchars($customer['contact'] ?? ''),
            'address' => htmlspecialchars($customer['address'] ?? '')
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Customer not found']);
}
