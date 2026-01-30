<?php
// Direct PIN update - Just run this file to fix your PIN
require_once 'config/database.php';

$pin = "123456";
$user_id = 1; // Admin user

// Generate new hash with explicit cost
$pin_hash = password_hash($pin, PASSWORD_BCRYPT, ['cost' => 10]);

echo "=== PIN Hash Update ===\n";
echo "User ID: " . $user_id . "\n";
echo "New PIN: " . $pin . "\n";
echo "New Hash: " . $pin_hash . "\n\n";

// Update the database
$sql = "UPDATE admin_pins SET pin_hash = ? WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo "Error preparing statement: " . mysqli_error($conn) . "\n";
    exit;
}

mysqli_stmt_bind_param($stmt, "si", $pin_hash, $user_id);

if (mysqli_stmt_execute($stmt)) {
    echo "✓ PIN hash updated successfully!\n\n";
    echo "You can now use PIN: 123456 to add new products\n";
    
    // Verify it works
    $verify_result = password_verify($pin, $pin_hash);
    echo "Verification test: " . ($verify_result ? "PASS ✓" : "FAIL ✗") . "\n";
} else {
    echo "Error executing statement: " . mysqli_stmt_error($stmt) . "\n";
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
