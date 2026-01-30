<?php
/**
 * POS Inventory Update API
 * Updates inventory in real-time when items are added/removed from cart
 */
// Suppress all output except JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any accidental output
ob_start();

session_start();

// Set JSON header FIRST
header('Content-Type: application/json; charset=utf-8');

// Require database after session start
require_once '../config/database.php';

// Check database connection
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    ob_end_clean();
    exit;
}

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    ob_end_clean();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    ob_end_clean();
    exit;
}

$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$quantity_change = isset($_POST['quantity_change']) ? intval($_POST['quantity_change']) : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if (!$product_id || $quantity_change === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    ob_end_clean();
    exit;
}

$today = date('Y-m-d');

// Check if inventory record exists for today
$check_sql = "SELECT current_stock FROM inventory WHERE product_id = ? AND date = ?";
$stmt = mysqli_prepare($conn, $check_sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    ob_end_clean();
    exit;
}

mysqli_stmt_bind_param($stmt, "is", $product_id, $today);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

$current_stock = $row ? intval($row['current_stock']) : 0;
$new_stock = $current_stock - $quantity_change;

// Prevent negative stock
if ($new_stock < 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Insufficient stock',
        'available' => $current_stock
    ]);
    ob_end_clean();
    exit;
}

// If no inventory record for today, create it
if (!$row) {
    $insert_sql = "INSERT INTO inventory (product_id, date, opening_stock, current_stock, closing_stock) 
                   VALUES (?, ?, ?, ?, ?)";
    $insert_stmt = mysqli_prepare($conn, $insert_sql);
    
    if ($insert_stmt) {
        mysqli_stmt_bind_param($insert_stmt, "isiii", $product_id, $today, $current_stock, $new_stock, $new_stock);
        mysqli_stmt_execute($insert_stmt);
        mysqli_stmt_close($insert_stmt);
    }
} else {
    // Update existing inventory record
    $update_sql = "UPDATE inventory SET current_stock = ? WHERE product_id = ? AND date = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    
    if ($update_stmt) {
        mysqli_stmt_bind_param($update_stmt, "iis", $new_stock, $product_id, $today);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
    }
}

// Get product name for response
$prod_sql = "SELECT product_name FROM products WHERE product_id = ?";
$prod_stmt = mysqli_prepare($conn, $prod_sql);
$product_name = 'Unknown';

if ($prod_stmt) {
    mysqli_stmt_bind_param($prod_stmt, "i", $product_id);
    mysqli_stmt_execute($prod_stmt);
    $prod_result = mysqli_stmt_get_result($prod_stmt);
    $prod_row = mysqli_fetch_assoc($prod_result);
    if ($prod_row) {
        $product_name = $prod_row['product_name'];
    }
    mysqli_stmt_close($prod_stmt);
}

// Log the action
$log_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_value, new_value) 
           VALUES (?, ?, ?, ?, ?, ?)";
$log_stmt = mysqli_prepare($conn, $log_sql);

if ($log_stmt) {
    $user_id = intval($_SESSION['user_id']);
    $action_log = $action . ' via POS (' . abs($quantity_change) . ' units)';
    $table_name = 'inventory';
    $old_value_str = strval($current_stock);
    $new_value_str = strval($new_stock);
    
    mysqli_stmt_bind_param($log_stmt, "ississ", 
        $user_id,
        $action_log,
        $table_name,
        $product_id,
        $old_value_str,
        $new_value_str
    );
    @mysqli_stmt_execute($log_stmt);
    mysqli_stmt_close($log_stmt);
}

// Clean output buffer and send JSON response
ob_end_clean();
http_response_code(200);
echo json_encode([
    'success' => true,
    'product_id' => $product_id,
    'product_name' => $product_name,
    'old_stock' => $current_stock,
    'new_stock' => $new_stock
]);
?>
