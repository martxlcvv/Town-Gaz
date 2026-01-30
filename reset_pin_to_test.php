<?php
// Simple script to reset PIN to 123456
$pin = "123456";
$pin_hash = password_hash($pin, PASSWORD_BCRYPT, ['cost' => 10]);

echo "PIN Reset Tool\n";
echo "==============\n\n";
echo "To reset your PIN to 123456, run this SQL command:\n\n";
echo "UPDATE admin_pins SET pin_hash = '" . $pin_hash . "' WHERE user_id = 1;\n\n";
echo "Then try adding a new product with PIN: 123456\n";
?>
