<?php
require_once 'config/database.php';

// Get admin user
$sql = "SELECT u.user_id, u.full_name, ap.pin_hash FROM users u 
        LEFT JOIN admin_pins ap ON u.user_id = ap.user_id 
        WHERE u.role_id = (SELECT role_id FROM roles WHERE role_name = 'Admin') 
        LIMIT 1";
        
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $admin = mysqli_fetch_assoc($result);
    echo "Admin User Found:\n";
    echo "- User ID: " . $admin['user_id'] . "\n";
    echo "- Name: " . $admin['full_name'] . "\n";
    echo "- Has PIN: " . (!empty($admin['pin_hash']) ? "YES" : "NO") . "\n\n";
    
    if (!empty($admin['pin_hash'])) {
        echo "PIN Hash: " . $admin['pin_hash'] . "\n\n";
        
        // Test verification with PIN 123456
        $test_pin = "123456";
        $verify = password_verify($test_pin, $admin['pin_hash']);
        
        echo "Verification Test:\n";
        echo "- PIN: $test_pin\n";
        echo "- Result: " . ($verify ? "✓ PASS" : "✗ FAIL") . "\n";
    }
} else {
    echo "No admin user found\n";
}

mysqli_close($conn);
?>
