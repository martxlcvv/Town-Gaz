<?php
session_start();
require_once '../../config/database.php';
require_once '../../auth/check-auth.php';
require_admin();

$page_title = "Manage Admin PIN";

// Get current PIN status
$sql = "SELECT * FROM admin_pins WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pin_data = mysqli_fetch_assoc($result);

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<!-- HTML for PIN management -->
<?php include '../../includes/footer.php'; ?>