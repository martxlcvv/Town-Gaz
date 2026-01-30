<?php
// ITO ANG LINE 1 - Walang whitespace dito
session_start();
require_once '../config/database.php';
prevent_cache();

if (!$conn || mysqli_connect_errno()) {
    die('Database connection failed: ' . mysqli_connect_error());
}

if (!mysqli_ping($conn)) {
    mysqli_close($conn);
    require_once '../config/database.php';
}

$success = '';
if (isset($_GET['reset']) && $_GET['reset'] == 'success') {
    $success = 'Password reset successful! Please login with your new password.';
}

if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
    $success = 'You have been successfully logged out!';
}

if (isset($_GET['session_expired']) && $_GET['session_expired'] == 1) {
    $error = 'Your session has expired. Please login again.';
}

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role_name'] == 'Admin') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: ../staff/pos.php');
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $sql = "SELECT u.*, r.role_name 
                FROM users u 
                JOIN roles r ON u.role_id = r.role_id 
                WHERE (u.email = ? OR u.username = ?) 
                AND u.status = 'active'";
        
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $email, $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($result && mysqli_num_rows($result) == 1) {
                $user = mysqli_fetch_assoc($result);
                
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['role_name'] = $user['role_name'];
                    $_SESSION['profile_picture'] = $user['profile_picture'];
                    $_SESSION['login_success'] = true;
                    
                    // Generate and set session token
                    generate_session_token();
                    
                    $user_id = $user['user_id'];
                    $ip_address = mysqli_real_escape_string($conn, $_SERVER['REMOTE_ADDR']);
                    
                    $attendance_stmt = mysqli_prepare($conn, "INSERT INTO attendance (user_id, ip_address) VALUES (?, ?)");
                    if ($attendance_stmt) {
                        mysqli_stmt_bind_param($attendance_stmt, "is", $user_id, $ip_address);
                        mysqli_stmt_execute($attendance_stmt);
                        $_SESSION['attendance_id'] = mysqli_insert_id($conn);
                        mysqli_stmt_close($attendance_stmt);
                    }
                    
                    $audit_stmt = mysqli_prepare($conn, "INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address, created_at) VALUES (?, 'LOGIN', 'users', ?, ?, NOW())");
                    if ($audit_stmt) {
                        mysqli_stmt_bind_param($audit_stmt, "iis", $user_id, $user_id, $ip_address);
                        mysqli_stmt_execute($audit_stmt);
                        mysqli_stmt_close($audit_stmt);
                    }
                    
                    // Use client-side redirect that replaces history so the login page
                    // does not remain in the browser history (prevents back-button return).
                    $target = ($user['role_name'] == 'Admin') ? '../admin/dashboard.php?login=success' : '../staff/pos.php?login=success';
                    echo '<!doctype html><html><head><meta charset="utf-8"><title>Redirecting...</title></head><body>';
                    echo '<script>try{ history.replaceState(null, "", "' . addslashes(basename($_SERVER['PHP_SELF'])) . '"); }catch(e){}; window.location.replace("' . addslashes($target) . '");</script>';
                    echo '</body></html>';
                    exit();
                } else {
                    $error = 'Invalid email or password';
                }
            } else {
                $error = 'Invalid email or password';
            }
            
            mysqli_stmt_close($stmt);
        } else {
            $error = 'Database error. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Town Gaz LPG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-blue: #00A8E8;
            --secondary-teal: #00D4B4;
            --accent-red: #E63946;
            --success-green: #28a745;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0, 168, 232, 0.3) 0%, rgba(0, 126, 167, 0.4) 50%, rgba(0, 95, 127, 0.3) 100%);
            animation: gradientShift 15s ease infinite;
            z-index: 0;
            pointer-events: none;
        }
        
        @keyframes gradientShift {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .particle {
            position: fixed;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }
        
        .particle:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation: float 20s infinite ease-in-out;
        }
        
        .particle:nth-child(2) {
            width: 60px;
            height: 60px;
            top: 70%;
            left: 80%;
            animation: float 15s infinite ease-in-out 5s;
        }
        
        .particle:nth-child(3) {
            width: 100px;
            height: 100px;
            top: 50%;
            left: 5%;
            animation: float 25s infinite ease-in-out 10s;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }
        
        .login-container {
            max-width: 920px;
            width: 100%;
            position: relative;
            z-index: 1;
            margin: 20px auto;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 2px solid rgba(255, 255, 255, 0.25);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.6s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.96);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .login-left {
            background: rgba(0, 168, 232, 0.15);
            backdrop-filter: blur(10px);
            padding: 50px 35px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
            min-height: 550px;
            border-right: 2px solid rgba(255, 255, 255, 0.15);
        }
        
        .login-left::before,
        .login-left::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
        }
        
        .login-left::before {
            width: 300px;
            height: 300px;
            top: -100px;
            right: -100px;
            animation: pulse 8s infinite ease-in-out;
        }
        
        .login-left::after {
            width: 200px;
            height: 200px;
            bottom: -50px;
            left: -50px;
            animation: pulse 6s infinite ease-in-out 2s;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.3; }
            50% { transform: scale(1.1); opacity: 0.5; }
        }
        
        .image-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 280px;
            margin-bottom: 30px;
        }
        
        .main-image {
            width: 100%;
            height: auto;
            object-fit: contain;
            filter: drop-shadow(0 12px 40px rgba(0, 0, 0, 0.4));
            animation: floatImage 4s infinite ease-in-out;
        }
        
        @keyframes floatImage {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-12px) scale(1.04); }
        }
        
        .brand-content {
            position: relative;
            z-index: 1;
        }
        
        .brand-title {
            font-size: 2.8rem;
            font-weight: 900;
            margin-bottom: 10px;
            letter-spacing: 2.5px;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            background: linear-gradient(135deg, #ffffff 0%, #e0f7ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .brand-tagline {
            font-size: 1.05rem;
            font-weight: 600;
            margin-bottom: 16px;
            opacity: 0.95;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            letter-spacing: 0.8px;
        }
        
        .brand-description {
            font-size: 0.9rem;
            opacity: 0.9;
            line-height: 1.6;
            max-width: 300px;
            margin: 0 auto;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        .login-right {
            padding: 50px 60px;
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(10px);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .login-header h3 {
            color: var(--primary-blue);
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0, 168, 232, 0.2);
        }
        
        .login-header p {
            color: #6C757D;
            font-size: 1rem;
            font-weight: 500;
        }
        
        .form-label {
            font-weight: 700;
            color: #2C3E50;
            font-size: 0.9rem;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .input-group {
            margin-bottom: 22px;
            position: relative;
        }
        
        .form-control {
            padding: 13px 16px;
            border-radius: 11px;
            border: 2px solid rgba(0, 168, 232, 0.2);
            transition: all 0.3s ease;
            font-size: 0.95rem;
            background: rgba(255, 255, 255, 0.9);
        }
        
        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.25rem rgba(0, 168, 232, 0.15);
            background: white;
            transform: translateY(-1px);
        }
        
        .input-group-text {
            background: linear-gradient(135deg, rgba(0, 168, 232, 0.1) 0%, rgba(0, 126, 167, 0.1) 100%);
            border: 2px solid rgba(0, 168, 232, 0.2);
            border-right: none;
            border-radius: 11px 0 0 11px;
            padding: 0 14px;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 11px 11px 0;
        }
        
        .input-group-text i {
            color: var(--primary-blue);
            font-size: 1.2rem;
        }
        
        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--primary-blue);
            font-size: 1.1rem;
            z-index: 10;
            transition: all 0.3s;
        }
        
        .password-toggle:hover {
            color: var(--secondary-teal);
        }
        
        .forgot-password {
            text-align: right;
            margin-top: -12px;
            margin-bottom: 18px;
        }
        
        .forgot-password a {
            color: var(--primary-blue);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .forgot-password a:hover {
            color: var(--secondary-teal);
            text-decoration: underline;
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-teal) 100%);
            border: none;
            padding: 14px;
            font-weight: 700;
            border-radius: 11px;
            transition: all 0.3s ease;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1.3px;
            box-shadow: 0 6px 20px rgba(0, 168, 232, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 168, 232, 0.4);
        }
        
        .alert {
            border-radius: 11px;
            border: none;
            padding: 13px 16px;
            font-size: 0.9rem;
            animation: shake 0.5s;
            margin-bottom: 20px;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }
        
        .alert-danger {
            background: rgba(230, 57, 70, 0.15);
            color: #C62828;
            border-left: 4px solid var(--accent-red);
            font-weight: 600;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.15);
            color: #1e7e34;
            border-left: 4px solid var(--success-green);
            font-weight: 600;
        }
        
        /* Mobile Responsive */
        @media (max-width: 991px) {
            .login-container {
                max-width: 600px;
            }
            
            .login-left {
                min-height: auto;
                padding: 40px 30px;
                border-right: none;
                border-bottom: 2px solid rgba(255, 255, 255, 0.15);
            }
            
            .image-container {
                max-width: 200px;
                margin-bottom: 24px;
            }
            
            .brand-title {
                font-size: 2.2rem;
                letter-spacing: 2px;
            }
            
            .brand-tagline {
                font-size: 0.95rem;
                margin-bottom: 12px;
            }
            
            .brand-description {
                font-size: 0.85rem;
                max-width: 280px;
            }
            
            .login-right {
                padding: 40px 35px;
            }
            
            .login-header h3 {
                font-size: 1.7rem;
            }
            
            .login-header p {
                font-size: 0.95rem;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 15px;
                align-items: flex-start;
            }
            
            .login-container {
                margin: 10px auto;
                max-width: 100%;
            }
            
            .login-card {
                border-radius: 22px;
            }
            
            .login-left {
                padding: 32px 24px;
            }
            
            .image-container {
                max-width: 180px;
                margin-bottom: 20px;
            }
            
            .brand-title {
                font-size: 1.9rem;
                margin-bottom: 8px;
            }
            
            .brand-tagline {
                font-size: 0.9rem;
            }
            
            .brand-description {
                font-size: 0.8rem;
                max-width: 260px;
            }
            
            .login-right {
                padding: 32px 28px;
            }
            
            .login-header {
                margin-bottom: 28px;
            }
            
            .login-header h3 {
                font-size: 1.5rem;
            }
            
            .login-header p {
                font-size: 0.9rem;
            }
            
            .form-label {
                font-size: 0.85rem;
            }
            
            .form-control {
                padding: 12px 14px;
                font-size: 0.9rem;
            }
            
            .input-group-text {
                padding: 0 12px;
            }
            
            .input-group-text i {
                font-size: 1.1rem;
            }
            
            .password-toggle {
                font-size: 1rem;
                right: 12px;
            }
            
            .btn-login {
                padding: 12px;
                font-size: 0.95rem;
            }
            
            .alert {
                padding: 12px 14px;
                font-size: 0.85rem;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .login-container {
                margin: 8px auto;
            }
            
            .login-card {
                border-radius: 20px;
            }
            
            .login-left {
                padding: 28px 20px;
            }
            
            .image-container {
                max-width: 150px;
                margin-bottom: 16px;
            }
            
            .brand-title {
                font-size: 1.6rem;
                letter-spacing: 1.5px;
            }
            
            .brand-tagline {
                font-size: 0.85rem;
            }
            
            .brand-description {
                font-size: 0.75rem;
                max-width: 240px;
                line-height: 1.5;
            }
            
            .login-right {
                padding: 28px 22px;
            }
            
            .login-header {
                margin-bottom: 24px;
            }
            
            .login-header h3 {
                font-size: 1.35rem;
            }
            
            .login-header p {
                font-size: 0.85rem;
            }
            
            .form-label {
                font-size: 0.8rem;
                margin-bottom: 6px;
            }
            
            .form-control {
                padding: 11px 13px;
                font-size: 0.85rem;
            }
            
            .input-group {
                margin-bottom: 18px;
            }
            
            .input-group-text {
                padding: 0 11px;
            }
            
            .input-group-text i {
                font-size: 1rem;
            }
            
            .password-toggle {
                font-size: 0.95rem;
            }
            
            .forgot-password {
                margin-bottom: 16px;
            }
            
            .forgot-password a {
                font-size: 0.8rem;
            }
            
            .btn-login {
                padding: 11px;
                font-size: 0.9rem;
                letter-spacing: 1px;
            }
        }
        
        @media (max-width: 360px) {
            .login-right {
                padding: 24px 18px;
            }
            
            .brand-title {
                font-size: 1.4rem;
            }
            
            .login-header h3 {
                font-size: 1.2rem;
            }
        }
        
        /* Height adjustments */
        @media (max-height: 700px) {
            .login-left {
                padding: 30px 25px;
                min-height: auto;
            }
            
            .image-container {
                max-width: 160px;
                margin-bottom: 18px;
            }
            
            .brand-title {
                font-size: 2rem;
                margin-bottom: 8px;
            }
            
            .login-header {
                margin-bottom: 25px;
            }
        }
    </style>
</head>
<body>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    
    <div class="container">
        <div class="login-container">
            <div class="login-card">
                <div class="row g-0">
                    <div class="col-lg-4 col-md-5 login-left">
                        <div class="image-container">
                            <img src="../assets/images/main lg.png" alt="Town Gaz LPG" class="main-image">
                        </div>  
                        
                        <div class="brand-content">
                            <h2 class="brand-title">TOWN GAZ</h2>
                            <p class="brand-tagline">Ang LPG ng Bayan</p>
                            <p class="brand-description">
                                Your trusted LPG provider. Manage your business efficiently with our comprehensive POS system.
                            </p>
                        </div>
                    </div>
                    
                    <div class="col-lg-8 col-md-7 login-right">
                        <div class="login-header">
                            <h3>Welcome Back!</h3>
                            <p>Please login to your account</p>
                        </div>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <?php echo htmlspecialchars($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email or Username</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person-fill"></i>
                                    </span>
                                    <input type="text" class="form-control" id="email" name="email" 
                                           placeholder="Enter email or username" required autofocus>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock-fill"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Enter password" required>
                                    <span class="password-toggle" onclick="togglePassword()">
                                        <i class="bi bi-eye" id="toggleIcon"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="forgot-password">
                                <a href="forgot-password.php">Forgot Password?</a>
                            </div>
                            
                            <button type="submit" class="btn btn-login btn-primary w-100">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login to System
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        }

        // ENTER key submits form
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.target.closest('.btn-close')) {
                const form = document.querySelector('form');
                if (form) {
                    form.submit();
                }
            }
        });

        // Simple client-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
                return false;
            }
        });
    </script>
</body>
</html>