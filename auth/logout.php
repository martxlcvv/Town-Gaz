


<?php
/**
 * Logout Handler
 * Handles user logout and attendance tracking
 */

session_start();
require_once '../config/database.php';
prevent_cache();

// Store user_id before destroying session
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$attendance_id = isset($_SESSION['attendance_id']) ? $_SESSION['attendance_id'] : null;
$session_token = isset($_SESSION['session_token']) ? $_SESSION['session_token'] : null;

// Update attendance logout time
if ($attendance_id) {
    $attendance_sql = "UPDATE attendance SET logout_time = CURRENT_TIMESTAMP WHERE attendance_id = ?";
    $stmt = mysqli_prepare($conn, $attendance_sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $attendance_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// Log audit with prepared statement
if ($user_id) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $audit_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address, created_at) 
                 VALUES (?, 'LOGOUT', 'users', ?, ?, NOW())";
    $audit_stmt = mysqli_prepare($conn, $audit_sql);
    if ($audit_stmt) {
        mysqli_stmt_bind_param($audit_stmt, "iis", $user_id, $user_id, $ip_address);
        mysqli_stmt_execute($audit_stmt);
        mysqli_stmt_close($audit_stmt);
    }
}

// Destroy session and invalidate token
session_unset();
session_destroy();

// Clear any session-related cookies
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login with logout success message
// Use history replace to prevent back navigation to protected pages
// Output a small HTML page that replaces the current history entry
// and then navigates to the login page.
header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\">\n<meta http-equiv=\"Cache-Control\" content=\"no-store, no-cache, must-revalidate, max-age=0\">\n<meta http-equiv=\"Pragma\" content=\"no-cache\">\n<meta http-equiv=\"Expires\" content=\"0\">\n<title>Logging out...</title>\n</head>\n<body>\n<script>\n  // Replace current history entry so back won't return here\n  try {\n    history.replaceState(null, '', 'login.php?logout=success');\n  } catch(e) {}\n  // Then navigate using replace to avoid creating a new history entry\n  window.location.replace('login.php?logout=success');\n</script>\n</body>\n</html>";
exit();
?>
