<?php
session_start();
require_once 'config/database.php';
require_once 'auth/check-auth.php';
require_admin();

echo "<h1>PHP Error Log Contents</h1>";
echo "<pre>";

// Possible XAMPP error log locations
$possible_logs = [
    'c:\\xampp\\apache\\logs\\error.log',
    'c:\\xampp\\apache\\logs\\access.log',
    'c:\\php\\logs\\error.log',
    'c:\\xampp\\php\\logs\\error.log'
];

foreach ($possible_logs as $log_path) {
    echo "\n=== Checking: $log_path ===\n";
    if (file_exists($log_path)) {
        echo "FILE EXISTS - Last 50 lines:\n";
        $lines = file($log_path);
        $relevant = array_filter($lines, function($line) {
            return strpos($line, '[CSRF]') !== false || strpos($line, '[DELETE]') !== false;
        });
        
        $last_lines = array_slice($relevant, -50);
        foreach ($last_lines as $line) {
            echo htmlspecialchars($line);
        }
    } else {
        echo "NOT FOUND\n";
    }
}

echo "</pre>";
echo "<p><a href='admin/products.php'>Back to Products</a></p>";
?>
