<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_admin();

$page_title = "Setup Admin PIN";

// Check if already has PIN
$sql = "SELECT * FROM admin_pins WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    header("Location: settings/manage-pin.php");
    exit();
}

// Handle PIN setup
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['setup_pin'])) {
    $pin1 = $_POST['pin1'];
    $pin2 = $_POST['pin2'];
    
    // Validation
    if (strlen($pin1) != 6 || !is_numeric($pin1)) {
        $error = "PIN must be 6 digits";
    } elseif ($pin1 !== $pin2) {
        $error = "PINs do not match";
    } else {
        // Hash and save PIN
        $pin_hash = password_hash($pin1, PASSWORD_BCRYPT, ['cost' => 10]);
        $sql = "INSERT INTO admin_pins (user_id, pin_hash) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "is", $_SESSION['user_id'], $pin_hash);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "PIN setup successful!";
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Failed to setup PIN";
        }
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<!-- HTML form for PIN setup -->
<?php include '../includes/footer.php'; ?>