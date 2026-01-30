<?php
require_once 'config/database.php';

echo "=== Checking Admin PIN in Database ===\n\n";

// Get the PIN data
$sql = "SELECT user_id, pin_hash FROM admin_pins WHERE user_id = 1";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    echo "User ID: " . $row['user_id'] . "\n";
    echo "Pin Hash: " . $row['pin_hash'] . "\n";
    echo "Hash Length: " . strlen($row['pin_hash']) . "\n\n";
    
    // Test verification
    $test_pins = ['123456', '1', '12345', '1234567'];
    echo "Testing different PINs:\n";
    foreach ($test_pins as $pin) {
        $verify = password_verify($pin, $row['pin_hash']);
        echo "  PIN '$pin': " . ($verify ? 'PASS ✓' : 'FAIL ✗') . "\n";
    }
} else {
    echo "ERROR: No PIN found for user_id = 1\n";
}

mysqli_close($conn);
?>
