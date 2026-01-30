<?php
session_start();
require_once '../config/database.php';
prevent_cache();

if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'Admin') {
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
        
        $new_filename = 'admin_' . $user_id . '.' . $file_ext;
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

// Handle logo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo'])) {
    $file = $_FILES['logo'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!in_array($file_ext, $allowed)) {
        $errors[] = "Only image files (JPG, PNG, GIF) are allowed";
    } elseif ($file['size'] > 2097152) {
        $errors[] = "File size must be less than 2MB";
    } else {
        $upload_dir = '../assets/images/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        // Delete old logo files
        foreach (['png', 'jpg', 'jpeg', 'gif'] as $ext) {
            if (file_exists($upload_dir . 'logo.' . $ext)) {
                unlink($upload_dir . 'logo.' . $ext);
            }
        }
        
        $new_filename = 'logo.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $messages[] = "Logo updated successfully! Changes will reflect in sidebar.";
        } else {
            $errors[] = "Failed to upload logo";
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($full_name) || empty($email)) {
        $errors[] = "All fields are required";
    } else {
        $check_email = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
        $stmt = mysqli_prepare($conn, $check_email);
        mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
        mysqli_stmt_execute($stmt);
        
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
            $errors[] = "Email already exists";
        } else {
            $update_sql = "UPDATE users SET full_name = ?, email = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, "ssi", $full_name, $email, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['full_name'] = $full_name;
                $messages[] = "Profile updated successfully!";
                $user_data['full_name'] = $full_name;
                $user_data['email'] = $email;
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

// Handle PIN setup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_pin'])) {
    $pin = trim($_POST['pin'] ?? '');
    $confirm_pin = trim($_POST['confirm_pin'] ?? '');
    
    if (empty($pin) || empty($confirm_pin)) {
        $errors[] = "PIN is required";
    } elseif (strlen($pin) !== 6 || !ctype_digit($pin)) {
        $errors[] = "PIN must be exactly 6 digits";
    } elseif ($pin !== $confirm_pin) {
        $errors[] = "PINs do not match";
    } else {
        $pin_hash = password_hash($pin, PASSWORD_DEFAULT);
        
        $check_sql = "SELECT user_id FROM admin_pins WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
            $update_sql = "UPDATE admin_pins SET pin_hash = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, "si", $pin_hash, $user_id);
        } else {
            $insert_sql = "INSERT INTO admin_pins (user_id, pin_hash) VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($stmt, "is", $user_id, $pin_hash);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $messages[] = "PIN updated successfully!";
        } else {
            $errors[] = "Error updating PIN";
        }
    }
}

// Handle forget PIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forget_pin'])) {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif ($email !== $user_data['email']) {
        $errors[] = "Email does not match your account";
    } else {
        // You would typically send an email here, but for now we'll just show a message
        $messages[] = "PIN recovery instructions would be sent to your email";
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
        flex-wrap: wrap;
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
        display: flex;
        align-items: center;
        gap: 6px;
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
        margin-bottom: 20px;
    }
    
    .card h4 {
        font-size: 1.15rem;
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
    
    .form-group.logo-upload {
        border: 2px dashed var(--border);
        border-radius: 8px;
        padding: 24px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .form-group.logo-upload:hover {
        border-color: var(--primary);
        background: rgba(6, 82, 117, 0.02);
    }
    
    .form-group.logo-upload input {
        display: none;
    }
    
    .logo-upload-label {
        cursor: pointer;
        display: block;
    }
    
    .btn {
        padding: 8px 20px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
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
    
    .pin-display {
        display: flex;
        gap: 8px;
        justify-content: center;
        margin: 16px 0;
    }
    
    .pin-dot {
        width: 12px;
        height: 12px;
        border: 2px solid var(--border);
        border-radius: 50%;
        transition: all 0.2s ease;
    }
    
    .pin-dot.filled {
        background: var(--primary);
        border-color: var(--primary);
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
        
        .settings-tabs button {
            flex: 1 1 calc(50% - 4px);
            justify-content: center;
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
        <button class="tab-btn" onclick="switchTab('pin')"><i class="bi bi-shield-check"></i> PIN</button>
        <button class="tab-btn" onclick="switchTab('logo')"><i class="bi bi-image"></i> Logo</button>
    </div>
    
    <!-- Profile Tab -->
    <div id="profile" class="tab-content active">
        <div class="card">
            <h4><i class="bi bi-person-fill"></i> Update Profile</h4>
            
            <!-- Profile Picture Section -->
            <div style="text-align: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid var(--border);">
                <div style="width: 100px; height: 100px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 15px; overflow: hidden; border: 3px solid var(--primary);">
                    <?php if (!empty($user_data['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($user_data['profile_picture']); ?>?t=<?php echo time(); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="bi bi-person-fill"></i>
                    <?php endif; ?>
                </div>
                <p style="color: #7f8c8d; font-size: 0.9rem; margin: 0;">Admin Profile Picture</p>
            </div>

            <!-- Profile Picture Upload Form -->
            <form method="POST" enctype="multipart/form-data" style="margin-bottom: 25px;" id="profilePicForm">
                <div class="form-group">
                    <label>Upload Profile Picture</label>
                    <div class="form-group logo-upload" onclick="document.getElementById('profilePicInput').click()">
                        <input type="file" id="profilePicInput" name="profile_pic" accept="image/*" onchange="uploadProfilePic()">
                        <label class="logo-upload-label">
                            <i class="bi bi-cloud-upload" style="font-size: 2rem; color: var(--primary); margin-bottom: 10px; display: block;"></i>
                            <p style="margin: 10px 0 5px; font-weight: 600; color: #2c3e50;">Click to upload or drag and drop</p>
                            <p style="color: #7f8c8d; font-size: 0.85rem; margin: 0;">JPG, PNG, or GIF (max 2MB)</p>
                        </label>
                    </div>
                    <p class="info-text">This image will appear in your header profile</p>
                </div>
            </form>

            <!-- Profile Info Form -->
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                    </div>
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary">
                    <i class="bi bi-check"></i> Save Changes
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
    
    <!-- PIN Tab -->
    <div id="pin" class="tab-content">
        <div class="card">
            <h4><i class="bi bi-shield-check"></i> Manage PIN</h4>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Set a 6-digit PIN for sensitive operations</p>
            <form method="POST" id="pinForm">
                <div class="form-group">
                    <label>PIN (6 digits)</label>
                    <input type="password" name="pin" inputmode="numeric" maxlength="6" placeholder="000000" required onInput="updatePinDisplay()">
                    <div class="pin-display" id="pinDisplay">
                        <span class="pin-dot"></span>
                        <span class="pin-dot"></span>
                        <span class="pin-dot"></span>
                        <span class="pin-dot"></span>
                        <span class="pin-dot"></span>
                        <span class="pin-dot"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirm PIN</label>
                    <input type="password" name="confirm_pin" inputmode="numeric" maxlength="6" placeholder="000000" required>
                </div>
                <button type="submit" name="setup_pin" class="btn btn-primary">
                    <i class="bi bi-check"></i> Set PIN
                </button>
            </form>
        </div>
        
        <div class="card">
            <h4><i class="bi bi-key"></i> Forgot PIN?</h4>
            <form method="POST">
                <div class="form-group">
                    <label>Confirm Email</label>
                    <input type="email" name="email" placeholder="Enter your registered email" required>
                    <div class="info-text">We'll send PIN recovery instructions to your email</div>
                </div>
                <button type="submit" name="forget_pin" class="btn btn-primary">
                    <i class="bi bi-envelope"></i> Send Recovery Email
                </button>
            </form>
        </div>
    </div>
    
    <!-- Logo Tab -->
    <div id="logo" class="tab-content">
        <div class="card">
            <h4><i class="bi bi-image"></i> System Logo</h4>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Upload a new logo (JPG, PNG, GIF - max 2MB). This logo will appear in the sidebar.</p>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group logo-upload">
                    <label class="logo-upload-label">
                        <div style="margin-bottom: 12px;">
                            <i class="bi bi-cloud-upload" style="font-size: 2rem; color: var(--primary);"></i>
                        </div>
                        <div style="font-weight: 600; color: #2c3e50; margin-bottom: 4px;">Click to upload or drag and drop</div>
                        <div style="color: #7f8c8d; font-size: 0.85rem;">JPG, PNG, GIF (Max 2MB)</div>
                        <input type="file" name="logo" accept="image/*" required>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-upload"></i> Upload Logo
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

function updatePinDisplay() {
    const pin = document.querySelector('input[name="pin"]').value;
    const dots = document.querySelectorAll('#pinDisplay .pin-dot');
    
    dots.forEach((dot, index) => {
        if (index < pin.length) {
            dot.classList.add('filled');
        } else {
            dot.classList.remove('filled');
        }
    });
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
