<?php
require_once 'config/database.php';

echo "=== PIN Verification Test ===\n";

$sql = 'SELECT user_id, pin_hash FROM admin_pins LIMIT 1';
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    echo 'User ID: ' . $row['user_id'] . "\n";
    echo 'PIN Hash exists: ' . (strlen($row['pin_hash']) > 0 ? 'YES' : 'NO') . "\n";
    echo 'Hash length: ' . strlen($row['pin_hash']) . "\n";
    echo 'First 30 chars: ' . substr($row['pin_hash'], 0, 30) . "\n";
    echo 'Hash algorithm: ' . password_algos()[0] . "\n\n";
    
    // Test password_verify with the PIN 123456
    $test_pin = '123456';
    $verify = password_verify($test_pin, $row['pin_hash']);
    echo 'Testing PIN: ' . $test_pin . "\n";
    echo 'Verification result: ' . ($verify ? 'PASS ✓' : 'FAIL ✗') . "\n";
    
    if (!$verify) {
        echo "\nDebugging info:\n";
        echo "- PIN is numeric: " . (is_numeric($test_pin) ? 'YES' : 'NO') . "\n";
        echo "- PIN length: " . strlen($test_pin) . "\n";
        echo "- Hash starts with \$2: " . (strpos($row['pin_hash'], '$2') === 0 ? 'YES' : 'NO') . "\n";
    }
} else {
    echo "No PIN records found in database\n";
}

mysqli_close($conn);
?>
