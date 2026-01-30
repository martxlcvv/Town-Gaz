<?php
$pin = "123456";
$hashed_pin = password_hash($pin, PASSWORD_BCRYPT, ['cost' => 10]);
echo "PIN: " . $pin . "\n";
echo "Hashed PIN: " . $hashed_pin . "\n";
echo "\nTo update your database, run:\n";
echo "UPDATE admin_pins SET pin_hash = '" . $hashed_pin . "' WHERE user_id = 1;\n";
?>
