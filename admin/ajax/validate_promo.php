<?php
/**
 * Complete Promo Code Validation with Usage Limit Check
 * Save as: admin/ajax/validate_promo.php
 */

// Turn off all errors
error_reporting(0);
ini_set('display_errors', 0);

// Clear any output
ob_start();
while (ob_get_level()) {
    ob_end_clean();
}

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Response template
$response = [
    'success' => false,
    'message' => '',
    'promo' => null,
    'discount' => 0
];

// Check parameters
if (!isset($_GET['code']) || !isset($_GET['total'])) {
    $response['message'] = 'Missing parameters';
    echo json_encode($response);
    exit;
}

$promo_code = strtoupper(trim($_GET['code']));
$total_amount = floatval($_GET['total']);

if (empty($promo_code)) {
    $response['message'] = 'Please enter a promo code';
    echo json_encode($response);
    exit;
}

if ($total_amount <= 0) {
    $response['message'] = 'Invalid cart amount';
    echo json_encode($response);
    exit;
}

// Connect to database
require_once '../../config/database.php';

if (!$conn) {
    $response['message'] = 'Database connection failed';
    echo json_encode($response);
    exit;
}

// Find active promo
$sql = "SELECT * FROM promotions 
        WHERE promo_code = ? 
        AND status = 'active' 
        AND DATE(start_date) <= CURDATE() 
        AND DATE(end_date) >= CURDATE()";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $promo_code);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$promo = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$promo) {
    // Check why promo not found (for better error messages)
    $debug_sql = "SELECT promo_code, status, 
                         DATE(start_date) as start_date, 
                         DATE(end_date) as end_date,
                         CURDATE() as today
                  FROM promotions 
                  WHERE promo_code = ?";
    
    $debug_stmt = mysqli_prepare($conn, $debug_sql);
    mysqli_stmt_bind_param($debug_stmt, "s", $promo_code);
    mysqli_stmt_execute($debug_stmt);
    $debug_result = mysqli_fetch_assoc(mysqli_stmt_get_result($debug_stmt));
    mysqli_stmt_close($debug_stmt);
    
    if ($debug_result) {
        // Promo exists but doesn't meet criteria
        if ($debug_result['status'] != 'active') {
            $response['message'] = 'This promo code is inactive';
        } elseif ($debug_result['start_date'] > $debug_result['today']) {
            $response['message'] = 'Promo starts on ' . date('M d, Y', strtotime($debug_result['start_date']));
        } elseif ($debug_result['end_date'] < $debug_result['today']) {
            $response['message'] = 'Promo expired on ' . date('M d, Y', strtotime($debug_result['end_date']));
        } else {
            $response['message'] = 'Promo code not valid';
        }
    } else {
        // Promo doesn't exist
        $response['message'] = 'Invalid promo code "' . htmlspecialchars($promo_code) . '"';
    }
    
    echo json_encode($response);
    exit;
}

// Check minimum purchase
if ($total_amount < $promo['min_purchase_amount']) {
    $response['message'] = 'Minimum purchase of ₱' . number_format($promo['min_purchase_amount'], 2) . ' required';
    echo json_encode($response);
    exit;
}

// ✅ NEW: Check usage limit
if (!empty($promo['usage_limit']) && $promo['usage_limit'] > 0) {
    $usage_sql = "SELECT COUNT(*) as usage_count FROM sale_promotions WHERE promo_id = ?";
    $usage_stmt = mysqli_prepare($conn, $usage_sql);
    mysqli_stmt_bind_param($usage_stmt, "i", $promo['promo_id']);
    mysqli_stmt_execute($usage_stmt);
    $usage_result = mysqli_fetch_assoc(mysqli_stmt_get_result($usage_stmt));
    mysqli_stmt_close($usage_stmt);
    
    if ($usage_result['usage_count'] >= $promo['usage_limit']) {
        $response['message'] = 'Promo code usage limit reached';
        echo json_encode($response);
        exit;
    }
    
    // ✅ Show remaining uses in success message
    $remaining = $promo['usage_limit'] - $usage_result['usage_count'];
    $promo['remaining_uses'] = $remaining;
}

// Calculate discount
$discount = 0;

if ($promo['discount_type'] == 'percentage') {
    // Percentage discount
    $discount = ($total_amount * $promo['discount_value']) / 100;
    
    // Apply max discount cap if set
    if (!empty($promo['max_discount_amount']) && $promo['max_discount_amount'] > 0) {
        $discount = min($discount, $promo['max_discount_amount']);
    }
} else {
    // Fixed amount discount
    $discount = $promo['discount_value'];
}

// Ensure discount doesn't exceed total
$discount = min($discount, $total_amount);
$discount = round($discount, 2);

// Success response
$response['success'] = true;
$response['message'] = 'Promo code "' . $promo['promo_code'] . '" applied successfully!';
$response['promo'] = [
    'promo_id' => (int)$promo['promo_id'],
    'promo_code' => $promo['promo_code'],
    'promo_name' => $promo['promo_name'],
    'discount_type' => $promo['discount_type'],
    'discount_value' => (float)$promo['discount_value'],
    'max_discount_amount' => (float)$promo['max_discount_amount'],
    'min_purchase_amount' => (float)$promo['min_purchase_amount'],
    'remaining_uses' => isset($promo['remaining_uses']) ? $promo['remaining_uses'] : null
];
$response['discount'] = $discount;

echo json_encode($response);
exit;