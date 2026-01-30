<?php
/**
 * Direct Promo Test Page
 * Save as: admin/test_promo.php
 */

require_once '../config/database.php';

// Test parameters
$test_code = 'TEST15';
$test_total = 200;

echo "<h2>Promo Code Test Results</h2>";
echo "<hr>";

// Test 1: Check if promo exists
echo "<h3>Test 1: Promo Exists?</h3>";
$sql1 = "SELECT * FROM promotions WHERE promo_code = ?";
$stmt1 = mysqli_prepare($conn, $sql1);
mysqli_stmt_bind_param($stmt1, "s", $test_code);
mysqli_stmt_execute($stmt1);
$result1 = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt1));

if ($result1) {
    echo "✅ Promo EXISTS<br>";
    echo "<pre>" . print_r($result1, true) . "</pre>";
} else {
    echo "❌ Promo NOT FOUND<br>";
}

// Test 2: Check status
echo "<hr><h3>Test 2: Is Active?</h3>";
if ($result1['status'] == 'active') {
    echo "✅ Status: ACTIVE<br>";
} else {
    echo "❌ Status: " . $result1['status'] . "<br>";
}

// Test 3: Check dates
echo "<hr><h3>Test 3: Date Validation</h3>";
echo "Start Date: " . $result1['start_date'] . "<br>";
echo "End Date: " . $result1['end_date'] . "<br>";
echo "Current Date: " . date('Y-m-d') . "<br>";

$sql3 = "SELECT 
    DATE(start_date) <= CURDATE() as started,
    DATE(end_date) >= CURDATE() as not_expired,
    (DATE(start_date) <= CURDATE() AND DATE(end_date) >= CURDATE()) as is_valid
FROM promotions WHERE promo_code = ?";
$stmt3 = mysqli_prepare($conn, $sql3);
mysqli_stmt_bind_param($stmt3, "s", $test_code);
mysqli_stmt_execute($stmt3);
$result3 = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt3));

echo "Has Started? " . ($result3['started'] ? "✅ YES" : "❌ NO") . "<br>";
echo "Not Expired? " . ($result3['not_expired'] ? "✅ YES" : "❌ NO") . "<br>";
echo "Is Valid? " . ($result3['is_valid'] ? "✅ YES" : "❌ NO") . "<br>";

// Test 4: Full query (like AJAX)
echo "<hr><h3>Test 4: Full Validation Query</h3>";
$sql4 = "SELECT * FROM promotions 
         WHERE promo_code = ? 
         AND status = 'active' 
         AND DATE(start_date) <= CURDATE() 
         AND DATE(end_date) >= CURDATE()";
$stmt4 = mysqli_prepare($conn, $sql4);
mysqli_stmt_bind_param($stmt4, "s", $test_code);
mysqli_stmt_execute($stmt4);
$result4 = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt4));

if ($result4) {
    echo "✅ Query MATCHES promo<br>";
} else {
    echo "❌ Query DOES NOT match promo<br>";
}

// Test 5: Min purchase check
echo "<hr><h3>Test 5: Minimum Purchase Check</h3>";
echo "Test Total: ₱" . number_format($test_total, 2) . "<br>";
echo "Min Required: ₱" . number_format($result1['min_purchase_amount'], 2) . "<br>";

if ($test_total >= $result1['min_purchase_amount']) {
    echo "✅ Meets minimum<br>";
} else {
    echo "❌ Below minimum<br>";
}

// Test 6: Calculate discount
echo "<hr><h3>Test 6: Discount Calculation</h3>";
if ($result1['discount_type'] == 'fixed') {
    $discount = $result1['discount_value'];
    echo "Discount Type: FIXED<br>";
    echo "Discount Amount: ₱" . number_format($discount, 2) . "<br>";
} else {
    $discount = ($test_total * $result1['discount_value']) / 100;
    if ($result1['max_discount_amount'] > 0) {
        $discount = min($discount, $result1['max_discount_amount']);
    }
    echo "Discount Type: PERCENTAGE (" . $result1['discount_value'] . "%)<br>";
    echo "Calculated Discount: ₱" . number_format($discount, 2) . "<br>";
}

$final_total = $test_total - $discount;
echo "Final Total: ₱" . number_format($final_total, 2) . "<br>";

// Test 7: AJAX URL Test
echo "<hr><h3>Test 7: AJAX Test</h3>";
$ajax_url = "ajax/validate_promo.php?code=" . urlencode($test_code) . "&total=" . $test_total;
echo "AJAX URL: <a href='$ajax_url' target='_blank'>$ajax_url</a><br>";
echo "Click the link above to see AJAX response<br>";

// Test 8: File exists check
echo "<hr><h3>Test 8: File Check</h3>";
if (file_exists('ajax/validate_promo.php')) {
    echo "✅ ajax/validate_promo.php EXISTS<br>";
} else {
    echo "❌ ajax/validate_promo.php NOT FOUND<br>";
    echo "Expected path: " . __DIR__ . "/ajax/validate_promo.php<br>";
}

echo "<hr>";
echo "<h3>Summary</h3>";
if ($result4 && $test_total >= $result1['min_purchase_amount']) {
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 10px;'>";
    echo "<h2 style='color: #155724;'>✅ PROMO SHOULD WORK!</h2>";
    echo "<p>If it's not working in POS, check:</p>";
    echo "<ul>";
    echo "<li>Browser Console for JavaScript errors</li>";
    echo "<li>Network tab for AJAX request/response</li>";
    echo "<li>Path to ajax/validate_promo.php file</li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 10px;'>";
    echo "<h2 style='color: #721c24;'>❌ PROMO HAS ISSUES</h2>";
    echo "<p>Check the test results above to see what's wrong.</p>";
    echo "</div>";
}
?>