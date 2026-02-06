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
        --primary: #3498db;
        --primary-blue: #3498db;
        --primary-green: #27ae60;
        --primary-orange: #e67e22;
        --primary-red: #e74c3c;
        --primary-purple: #9b59b6;
        --primary-yellow: #f1c40f;
        --primary-teal: #1abc9c;
        --primary-gray: #95a5a6;
        --light-bg: #f8f9fa;
        --card-bg: #ffffff;
        --text-dark: #2c3e50;
        --text-light: #7f8c8d;
        --border-color: #e9ecef;
        --shadow-light: 0 2px 10px rgba(0,0,0,0.04);
        --shadow-medium: 0 4px 20px rgba(0,0,0,0.06);
        --success: #27ae60;
        --danger: #e74c3c;
        --light: #f8f9fa;
        --border: #e9ecef;
    }
    
    body {
        background-color: var(--light-bg);
        color: var(--text-dark);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .main-content {
        margin-left: 280px;
        padding: 20px;
        min-height: 100vh;
        background: var(--light-bg);
    }
    
    .page-header {
        margin-bottom: 25px;
        background: linear-gradient(135deg, #1a4d5c 0%, #0f3543 100%);
        border-radius: 10px;
        padding: 1.25rem 1.5rem;
        color: white;
        box-shadow: 0 4px 15px rgba(26, 77, 92, 0.2);
    }
    
    .page-header h2 {
        font-size: 2rem;
        font-weight: 800;
        color: #ffffff;
        margin: 0;
        letter-spacing: 0.5px;
    }
    
    .alert-custom {
        border: none;
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 16px;
        font-size: 0.9rem;
        box-shadow: var(--shadow-light);
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
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 0;
        flex-wrap: wrap;
    }
    
    .settings-tabs button {
        background: none;
        border: none;
        padding: 12px 20px;
        cursor: pointer;
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text-light);
        border-bottom: 3px solid transparent;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        margin-bottom: -2px;
        display: flex;
        align-items: center;
        gap: 6px;
        letter-spacing: 0.3px;
    }
    
    .settings-tabs button.active {
        color: var(--primary-blue);
        border-color: var(--primary-blue);
    }
    
    .settings-tabs button:hover {
        color: var(--primary-blue);
        transform: translateY(-2px);
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
        animation: fadeIn 0.4s ease-out;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .card {
        background: white;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 24px;
        box-shadow: var(--shadow-light);
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }
    
    .card:hover {
        box-shadow: var(--shadow-medium);
        border-color: rgba(52, 152, 219, 0.2);
    }
    
    .card h4 {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-dark);
        margin: 0 0 20px 0;
        display: flex;
        align-items: center;
        gap: 10px;
        letter-spacing: 0.3px;
    }
    
    .card h4 i {
        color: var(--primary-blue);
        font-size: 1.4rem;
    }
    
    .form-group {
        margin-bottom: 16px;
    }
    
    .form-group label {
        display: block;
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 8px;
        font-size: 0.9rem;
        letter-spacing: 0.2px;
    }
    
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 10px 12px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.9rem;
        font-family: inherit;
        transition: all 0.3s ease;
        background: white;
    }
    
    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
        background: #f8fbfe;
    }
    
    .form-group.logo-upload {
        border: 2px dashed var(--border-color);
        border-radius: 10px;
        padding: 30px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        background: linear-gradient(135deg, #f8fbfe 0%, #f0f7fc 100%);
    }
    
    .form-group.logo-upload:hover {
        border-color: var(--primary-blue);
        background: linear-gradient(135deg, #e8f3fc 0%, #d8ecf8 100%);
        box-shadow: 0 4px 12px rgba(52, 152, 219, 0.15);
    }
    
    .form-group.logo-upload input {
        display: none;
    }
    
    .logo-upload-label {
        cursor: pointer;
        display: block;
    }
    
    .btn {
        padding: 10px 24px;
        border: none;
        border-radius: 8px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        letter-spacing: 0.3px;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, var(--primary-blue), #2980b9);
        color: white;
        box-shadow: 0 4px 12px rgba(52, 152, 219, 0.25);
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #2980b9, #1f618d);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(52, 152, 219, 0.35);
        color: white;
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
        color: var(--text-light);
        margin-top: 6px;
        font-style: italic;
    }
    
    .pin-display {
        display: flex;
        gap: 8px;
        justify-content: center;
        margin: 20px 0;
    }
    
    .pin-dot {
        width: 14px;
        height: 14px;
        border: 2px solid var(--border-color);
        border-radius: 50%;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        background: white;
    }
    
    .pin-dot.filled {
        background: var(--primary-blue);
        border-color: var(--primary-blue);
        box-shadow: 0 0 8px rgba(52, 152, 219, 0.4);
        transform: scale(1.1);
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
            font-size: 1.5rem;
        }
        
        .page-header {
            padding: 1rem;
        }
        
        .settings-tabs button {
            flex: 1 1 calc(50% - 4px);
            justify-content: center;
            font-size: 0.85rem;
            padding: 10px 15px;
        }
        
        .card {
            padding: 16px;
        }
        
        .card h4 {
            font-size: 1.1rem;
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
            <p style="color: var(--text-light); margin-bottom: 20px; font-size: 0.95rem;">Manage your personal information and profile picture.</p>
            
            <!-- Profile Picture Section -->
            <div style="text-align: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color);">
                <div style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-blue), #2980b9); color: white; display: flex; align-items: center; justify-content: center; font-size: 3rem; margin: 0 auto 15px; overflow: hidden; border: 4px solid rgba(52, 152, 219, 0.2); box-shadow: 0 8px 24px rgba(52, 152, 219, 0.2);">
                    <?php if (!empty($user_data['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($user_data['profile_picture']); ?>?t=<?php echo time(); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="bi bi-person-fill"></i>
                    <?php endif; ?>
                </div>
                <p style="color: var(--text-light); font-size: 0.95rem; margin: 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Admin Profile Picture</p>
            </div>

            <!-- Profile Picture Upload Form -->
            <form method="POST" enctype="multipart/form-data" style="margin-bottom: 25px;" id="profilePicForm">
                <div class="form-group">
                    <label>Upload Profile Picture</label>
                    <div class="form-group logo-upload" onclick="document.getElementById('profilePicInput').click()">
                        <input type="file" id="profilePicInput" name="profile_pic" accept="image/*" onchange="uploadProfilePic()">
                        <label class="logo-upload-label">
                            <i class="bi bi-cloud-upload" style="font-size: 2rem; color: var(--primary-blue); margin-bottom: 10px; display: block;"></i>
                            <p style="margin: 10px 0 5px; font-weight: 700; color: var(--text-dark); letter-spacing: 0.3px;">Click to upload or drag and drop</p>
                            <p style="color: var(--text-light); font-size: 0.85rem; margin: 0;">JPG, PNG, or GIF (max 2MB)</p>
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
            <p style="color: var(--text-light); margin-bottom: 20px; font-size: 0.95rem;">Secure your account by changing your password regularly.</p>
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
            <p style="color: var(--text-light); margin-bottom: 20px; font-size: 0.95rem;">Set a 6-digit PIN for sensitive operations</p>
            <form method="POST" id="pinForm">
                <div class="form-group">
                    <label>PIN (6 digits)</label>
                    <input type="password" name="pin" inputmode="numeric" maxlength="6" placeholder="000000" required onInput="updatePinDisplay()" style="font-size: 1.5rem; letter-spacing: 0.5rem; text-align: center;">
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
                    <input type="password" name="confirm_pin" inputmode="numeric" maxlength="6" placeholder="000000" required style="font-size: 1.5rem; letter-spacing: 0.5rem; text-align: center;">
                </div>
                <button type="submit" name="setup_pin" class="btn btn-primary">
                    <i class="bi bi-check"></i> Set PIN
                </button>
            </form>
        </div>
        
        <div class="card">
            <h4><i class="bi bi-key"></i> Forgot PIN?</h4>
            <p style="color: var(--text-light); margin-bottom: 20px; font-size: 0.95rem;">Enter your email to receive PIN recovery instructions.</p>
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
            <p style="color: var(--text-light); margin-bottom: 20px; font-size: 0.95rem;">Upload a new logo (JPG, PNG, GIF - max 2MB). This logo will appear in the sidebar.</p>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group logo-upload">
                    <label class="logo-upload-label">
                        <div style="margin-bottom: 12px;">
                            <i class="bi bi-cloud-upload" style="font-size: 2.5rem; color: var(--primary-blue);"></i>
                        </div>
                        <div style="font-weight: 700; color: var(--text-dark); margin-bottom: 8px; letter-spacing: 0.3px;">Click to upload or drag and drop</div>
                        <div style="color: var(--text-light); font-size: 0.85rem;">JPG, PNG, GIF (Max 2MB)</div>
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
