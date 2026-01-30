<?php
/**
 * PIN Reset Tool
 * Use this to set your PIN to 123456
 */

// Include database connection
require_once 'config/database.php';

// If form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_pin'])) {
    $user_id = intval($_POST['user_id']);
    $new_pin = "123456";
    
    // Hash the PIN
    $pin_hash = password_hash($new_pin, PASSWORD_BCRYPT, ['cost' => 10]);
    
    // Update the database
    $sql = "UPDATE admin_pins SET pin_hash = ? WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $pin_hash, $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $success = "PIN for user ID $user_id has been set to: 123456";
    } else {
        $error = "Failed to update PIN: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

// Get all users with PINs
$users_sql = "SELECT ap.user_id, ap.pin_hash, u.username, u.email FROM admin_pins ap LEFT JOIN users u ON ap.user_id = u.user_id ORDER BY ap.user_id";
$users_result = mysqli_query($conn, $users_sql);
$users = mysqli_fetch_all($users_result, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>PIN Reset Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .success { color: green; padding: 10px; background: #e8f5e9; border-radius: 4px; margin-bottom: 20px; }
        .error { color: red; padding: 10px; background: #ffebee; border-radius: 4px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f5f5f5; }
        button { padding: 10px 20px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0b7dda; }
    </style>
</head>
<body>
    <div class="container">
        <h1>PIN Reset Tool</h1>
        <p style="color: #666;">Set all users' PIN to: <strong>123456</strong></p>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <h2>Current PIN Users</h2>
        <table>
            <tr>
                <th>User ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Action</th>
            </tr>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                <td><?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                        <button type="submit" name="reset_pin" onclick="return confirm('Set PIN to 123456 for this user?');">
                            Reset to 123456
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>
