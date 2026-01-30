<?php
/**
 * Town Gas POS System
 * Root Index File
 * 
 * This file redirects users appropriately
 * Place this in the root directory: htdocs/town-gas/index.php
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to appropriate dashboard based on role
    if ($_SESSION['role_name'] == 'Admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: staff/pos.php');
    }
    exit();
} else {
    // Redirect to login page
    header('Location: auth/login.php');
    exit();
}
?>