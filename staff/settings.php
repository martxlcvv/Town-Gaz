<?php
session_start();
require_once '../config/database.php';
prevent_cache();

if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Staff') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php';

$page_title = "Settings";
$messages = [];
$errors = [];

// Get user info
$user_id = $_SESSION['user_id'];
$user_sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
    $file = $_FILES['profile_pic'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!in_array($file_ext, $allowed)) {
        $errors[] = "Only image files (JPG, PNG, GIF) are allowed";
    } elseif ($file['size'] > 2097152) {
        $errors[] = "File size must be less than 2MB";
    } else {
        $upload_dir = '../assets/images/profiles/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        $new_filename = 'staff_' . $user_id . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $update_sql = "UPDATE users SET profile_picture = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $update_sql);
            $pic_path = '/town-gas/assets/images/profiles/' . $new_filename;
            mysqli_stmt_bind_param($stmt, "si", $pic_path, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $messages[] = "Profile picture updated successfully!";
                $_SESSION['profile_picture'] = $pic_path;
                
                // Re-query user data to update display
                $reload_sql = "SELECT * FROM users WHERE user_id = ?";
                $reload_stmt = mysqli_prepare($conn, $reload_sql);
                mysqli_stmt_bind_param($reload_stmt, "i", $user_id);
                mysqli_stmt_execute($reload_stmt);
                $user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($reload_stmt));
                
                // Redirect to refresh the page properly
                header("Location: settings.php?section=profile&success=picture");
                exit();
            }
        } else {
            $errors[] = "Failed to upload profile picture";
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($full_name) || empty($email)) {
        $errors[] = "Name and email are required";
    } else {
        $check_email = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
        $stmt = mysqli_prepare($conn, $check_email);
        mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
        mysqli_stmt_execute($stmt);
        
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
            $errors[] = "Email already exists";
        } else {
            $update_sql = "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, "sssi", $full_name, $email, $phone, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['full_name'] = $full_name;
                $messages[] = "Profile updated successfully!";
                $user_data['full_name'] = $full_name;
                $user_data['email'] = $email;
                $user_data['phone'] = $phone;
            } else {
                $errors[] = "Error updating profile";
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (empty($current) || empty($new) || empty($confirm)) {
        $errors[] = "All password fields are required";
    } elseif ($new !== $confirm) {
        $errors[] = "New passwords do not match";
    } elseif (strlen($new) < 6) {
        $errors[] = "Password must be at least 6 characters";
    } elseif (!password_verify($current, $user_data['password'])) {
        $errors[] = "Current password is incorrect";
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $update_sql = "UPDATE users SET password = ? WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "si", $hashed, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $messages[] = "Password changed successfully!";
        } else {
            $errors[] = "Error changing password";
        }
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
    :root {
        --primary: #065275;
        --success: #27ae60;
        --danger: #e74c3c;
        --light: #f5f7fa;
        --border: #e9ecef;
    }
    
    .main-content {
        margin-left: 280px;
        padding: 20px;
        min-height: 100vh;
        background: var(--light);
    }
    
    .page-header {
        margin-bottom: 25px;
    }
    
    .page-header h2 {
        font-size: 1.8rem;
        font-weight: 700;
        color: #2c3e50;
        margin: 0;
    }
    
    .alert-custom {
        border: none;
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 16px;
        font-size: 0.9rem;
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
    }
    
    .alert-danger {
        background: #f8d7da;
        color: #721c24;
    }
    
    .settings-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 24px;
        border-bottom: 2px solid var(--border);
        padding-bottom: 0;
    }
    
    .settings-tabs button {
        background: none;
        border: none;
        padding: 12px 20px;
        cursor: pointer;
        font-size: 0.95rem;
        font-weight: 600;
        color: #7f8c8d;
        border-bottom: 3px solid transparent;
        transition: all 0.2s ease;
        margin-bottom: -2px;
    }
    
    .settings-tabs button.active {
        color: var(--primary);
        border-color: var(--primary);
    }
    
    .settings-tabs button:hover {
        color: var(--primary);
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    
    .card h4 {
        font-size: 1.2rem;
        font-weight: 700;
        color: #2c3e50;
        margin: 0 0 20px 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .form-group {
        margin-bottom: 16px;
    }
    
    .form-group label {
        display: block;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 6px;
        font-size: 0.9rem;
    }
    
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.9rem;
        font-family: inherit;
    }
    
    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(6, 82, 117, 0.1);
    }
    
    .form-group.pic-upload {
        border: 2px dashed var(--border);
        border-radius: 8px;
        padding: 24px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .form-group.pic-upload:hover {
        border-color: var(--primary);
        background: rgba(6, 82, 117, 0.02);
    }
    
    .form-group.pic-upload input {
        display: none;
    }
    
    .pic-upload-label {
        cursor: pointer;
        display: block;
    }
    
    .pic-preview {
        max-width: 150px;
        max-height: 150px;
        margin-bottom: 16px;
        border-radius: 50%;
        border: 3px solid var(--primary);
    }
    
    .btn {
        padding: 8px 20px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.9rem;
    }
    
    .btn-primary {
        background: var(--primary);
        color: white;
    }
    
    .btn-primary:hover {
        background: #004a5c;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(6, 82, 117, 0.2);
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    
    .form-row.full {
        grid-template-columns: 1fr;
    }
    
    .info-text {
        font-size: 0.85rem;
        color: #7f8c8d;
        margin-top: 4px;
    }
    
    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            padding: 15px;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .settings-tabs {
            flex-wrap: wrap;
        }
        
        .page-header h2 {
            font-size: 1.4rem;
        }
    }
</style>

<div class="main-content">
    <div class="page-header">
        <h2><i class="bi bi-gear"></i> Settings</h2>
    </div>
    
    <?php if (!empty($messages)): ?>
        <!-- Alerts handled by SweetAlert in JavaScript -->
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <!-- Alerts handled by SweetAlert in JavaScript -->
    <?php endif; ?>
    
    <div class="settings-tabs">
        <button class="tab-btn active" onclick="switchTab('profile')"><i class="bi bi-person"></i> Profile</button>
        <button class="tab-btn" onclick="switchTab('password')"><i class="bi bi-lock"></i> Password</button>
    </div>
    
    <!-- Profile Tab -->
    <div id="profile" class="tab-content active">
        <div class="card">
            <h4><i class="bi bi-person-fill"></i> My Profile</h4>
            
            <!-- Current Profile Picture -->
            <div style="text-align: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #e9ecef;">
                <div style="width: 110px; height: 110px; border-radius: 50%; background: #065275; color: white; display: flex; align-items: center; justify-content: center; font-size: 2.8rem; margin: 0 auto 15px; overflow: hidden; border: 3px solid #065275;">
                    <?php if (!empty($user_data['profile_picture']) && file_exists('../assets/images/profiles/' . $user_data['profile_picture'])): ?>
                        <img src="../assets/images/profiles/<?php echo htmlspecialchars($user_data['profile_picture']); ?>?t=<?php echo time(); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="bi bi-person-fill"></i>
                    <?php endif; ?>
                </div>
                <p style="color: #7f8c8d; font-size: 0.9rem; margin: 0;">Your Profile Picture</p>
            </div>

            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                    </div>
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary">
                    <i class="bi bi-check"></i> Save Changes
                </button>
            </form>
        </div>
        
        <div class="card">
            <h4><i class="bi bi-image"></i> Change Profile Picture</h4>
            <p style="color: #7f8c8d; margin-bottom: 20px; font-size: 0.9rem;">Upload a new profile picture (JPG, PNG, GIF - max 2MB)</p>
            <form method="POST" enctype="multipart/form-data" id="profilePicForm">
                <div class="form-group pic-upload">
                    <label class="pic-upload-label">
                        <div style="margin-bottom: 12px;">
                            <i class="bi bi-cloud-upload" style="font-size: 2rem; color: #065275;"></i>
                        </div>
                        <div style="font-weight: 600; color: #2c3e50; margin-bottom: 4px;">Click to upload or drag and drop</div>
                        <div style="color: #7f8c8d; font-size: 0.85rem;">JPG, PNG, GIF (Max 2MB)</div>
                        <input type="file" name="profile_pic" accept="image/*" onchange="uploadProfilePic()">
                    </label>
                </div>
                <button type="submit" class="btn btn-primary" onclick="return false;">
                    <i class="bi bi-upload"></i> Upload Picture
                </button>
            </form>
        </div>
    </div>
    
    <!-- Password Tab -->
    <div id="password" class="tab-content">
        <div class="card">
            <h4><i class="bi bi-lock-fill"></i> Change Password</h4>
            <form method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required>
                        <div class="info-text">Minimum 6 characters</div>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                </div>
                <button type="submit" name="change_password" class="btn btn-primary">
                    <i class="bi bi-check"></i> Change Password
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    document.getElementById(tabName).classList.add('active');
    event.target.closest('.tab-btn').classList.add('active');
}

function uploadProfilePic() {
    showLoading('Uploading...', 'Please wait while we upload your profile picture');
    document.getElementById('profilePicForm').submit();
}

// Show SweetAlert for success and error messages
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $msg): ?>
            showSuccess('Success!', '<?php echo addslashes($msg); ?>');
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $err): ?>
            showError('Error!', '<?php echo addslashes($err); ?>');
        <?php endforeach; ?>
    <?php endif; ?>
});
</script>

<?php include '../includes/footer.php'; ?>
