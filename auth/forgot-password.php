<?php
session_start();
require_once '../config/database.php';
require_once '../config/email-config.php';

$error = '';
$success = '';
$step = 1;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (isset($_POST['verify_email'])) {
        $email = trim($_POST['email']);
        
        if (empty($email)) {
            $error = 'Please enter your email address';
        } else {
            $sql = "SELECT user_id, email, full_name FROM users WHERE email = ? AND status = 'active'";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) == 1) {
                $user = mysqli_fetch_assoc($result);
                $_SESSION['reset_user_id'] = $user['user_id'];
                $_SESSION['reset_email'] = $user['email'];
                $_SESSION['reset_full_name'] = $user['full_name'];
                
                // Generate and send reset code
                $reset_code = generateResetCode();
                if (storeResetCode($user['user_id'], $reset_code, $conn)) {
                    if (sendResetCode($email, $user['full_name'], $reset_code)) {
                        $step = 2;
                        $success = 'Verification code sent to your email! Please check your inbox.';
                    } else {
                        $error = 'Failed to send email. Please try again.';
                    }
                } else {
                    $error = 'Failed to generate reset code. Please try again.';
                }
            } else {
                $error = 'No account found with this email address';
            }
        }
    }
    
    if (isset($_POST['request_new_code'])) {
        if (isset($_SESSION['reset_user_id'])) {
            $reset_code = generateResetCode();
            $email = $_SESSION['reset_email'];
            $full_name = $_SESSION['reset_full_name'];
            
            if (storeResetCode($_SESSION['reset_user_id'], $reset_code, $conn)) {
                if (sendResetCode($email, $full_name, $reset_code)) {
                    $success = 'New verification code sent to your email!';
                    $step = 2;
                } else {
                    $error = 'Failed to send email. Please try again.';
                    $step = 2;
                }
            } else {
                $error = 'Failed to generate new code. Please try again.';
                $step = 2;
            }
        }
    }
    
    if (isset($_POST['verify_code'])) {
        $reset_code = trim($_POST['reset_code']);
        
        if (empty($reset_code)) {
            $error = 'Please enter your verification code';
            $step = 2;
        } else if (!isset($_SESSION['reset_user_id'])) {
            $error = 'Session expired. Please start over.';
            $step = 1;
        } else if (verifyResetCode($_SESSION['reset_user_id'], $reset_code, $conn)) {
            $step = 3;
            $_SESSION['reset_code_verified'] = true;
            $success = 'Verification successful! Please enter your new password.';
        } else {
            $error = 'Invalid or expired verification code. Please try again.';
            $step = 2;
        }
    }
    
    if (isset($_POST['reset_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in all fields';
            $step = 3;
        } else if (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters long';
            $step = 3;
        } else if ($new_password !== $confirm_password) {
            $error = 'Passwords do not match';
            $step = 3;
        } else if (!isset($_SESSION['reset_code_verified'])) {
            $error = 'Verification failed. Please start over.';
            $step = 1;
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $user_id = $_SESSION['reset_user_id'];
            $ip_address = $_SERVER['REMOTE_ADDR'];
            
            $update_sql = "UPDATE users SET password = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                // Mark all reset codes as used
                $mark_sql = "UPDATE password_resets SET is_used = 1, used_at = NOW() WHERE user_id = ? AND is_used = 0";
                $mark_stmt = mysqli_prepare($conn, $mark_sql);
                mysqli_stmt_bind_param($mark_stmt, "i", $user_id);
                mysqli_stmt_execute($mark_stmt);
                
                // Log the reset
                $audit_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address, created_at) 
                             VALUES (?, 'PASSWORD_RESET', 'users', ?, ?, NOW())";
                $audit_stmt = mysqli_prepare($conn, $audit_sql);
                mysqli_stmt_bind_param($audit_stmt, "iis", $user_id, $user_id, $ip_address);
                mysqli_stmt_execute($audit_stmt);
                
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_full_name']);
                unset($_SESSION['reset_code_verified']);
                
                header('Location: login.php?reset=success');
                exit();
            } else {
                $error = 'Failed to reset password. Please try again.';
                $step = 3;
            }
        }
    }
}

if (isset($_SESSION['reset_user_id']) && !isset($_POST['verify_email'])) {
    if (!isset($_POST['verify_code']) && !isset($_POST['reset_password'])) {
        $step = 2;
    }
}

if (isset($_POST['verify_code']) && empty($error)) {
    $step = 3;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Town Gas POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-blue: #00A8E8;
            --secondary-teal: #007EA7;
            --dark-teal: #005F7F;
            --accent-red: #E63946;
            --success-green: #28A745;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            overflow-x: hidden;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-teal) 50%, var(--dark-teal) 100%);
            overflow-y: auto;
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
        
        .forgot-container {
            max-width: 520px;
            width: 100%;
            position: relative;
            z-index: 1;
            margin: 20px auto;
        }
        
        .forgot-card {
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 24px;
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
        
        .forgot-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-teal) 100%);
            padding: 35px 30px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .forgot-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(255, 255, 255, 0.03) 10px, rgba(255, 255, 255, 0.03) 20px);
            animation: slide 20s linear infinite;
        }
        
        @keyframes slide {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        .forgot-icon {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            position: relative;
            z-index: 1;
        }
        
        .forgot-icon i {
            font-size: 2.2rem;
            color: white;
        }
        
        .forgot-header h3 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }
        
        .forgot-header p {
            font-size: 0.95rem;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }
        
        .forgot-body {
            padding: 30px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 25px;
            gap: 8px;
        }
        
        .step {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #E9ECEF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #6C757D;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .step.active {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-teal));
            color: white;
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(0, 168, 232, 0.4);
        }
        
        .step.completed {
            background: var(--success-green);
            color: white;
        }
        
        .step-line {
            width: 45px;
            height: 3px;
            background: #E9ECEF;
            transition: all 0.3s;
        }
        
        .step-line.active {
            background: var(--primary-blue);
        }
        
        .form-label {
            font-weight: 700;
            color: #2C3E50;
            font-size: 0.9rem;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-control {
            padding: 12px 16px;
            border-radius: 10px;
            border: 2px solid rgba(0, 168, 232, 0.2);
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.25rem rgba(0, 168, 232, 0.15);
            transform: translateY(-1px);
        }
        
        .input-group-text {
            background: linear-gradient(135deg, rgba(0, 168, 232, 0.1) 0%, rgba(0, 126, 167, 0.1) 100%);
            border: 2px solid rgba(0, 168, 232, 0.2);
            border-right: none;
            border-radius: 10px 0 0 10px;
            padding: 0 14px;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        
        .input-group-text i {
            color: var(--primary-blue);
            font-size: 1.1rem;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-teal) 100%);
            border: none;
            padding: 12px;
            font-weight: 700;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            box-shadow: 0 6px 20px rgba(0, 168, 232, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }
        
        .btn-submit:hover::before {
            left: 100%;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 168, 232, 0.4);
        }
        
        .btn-back {
            background: transparent;
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
            padding: 12px;
            font-weight: 700;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
        }
        
        .btn-back:hover {
            background: var(--primary-blue);
            color: white;
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 12px 16px;
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
        
        .password-requirements {
            background: #F8F9FA;
            padding: 12px;
            border-radius: 10px;
            margin-top: 12px;
            font-size: 0.82rem;
        }
        
        .password-requirements ul {
            margin: 8px 0 0 0;
            padding-left: 18px;
        }
        
        .password-requirements li {
            color: #6C757D;
            margin: 4px 0;
        }
        
        small.text-muted {
            font-size: 0.82rem;
            display: block;
            margin-top: 6px;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            body {
                padding: 15px;
                align-items: flex-start;
            }
            
            .forgot-container {
                margin: 10px auto;
                max-width: 100%;
            }
            
            .forgot-card {
                border-radius: 20px;
            }
            
            .forgot-header {
                padding: 28px 20px;
            }
            
            .forgot-header h3 {
                font-size: 1.5rem;
            }
            
            .forgot-header p {
                font-size: 0.88rem;
            }
            
            .forgot-icon {
                width: 60px;
                height: 60px;
                margin-bottom: 12px;
            }
            
            .forgot-icon i {
                font-size: 1.8rem;
            }
            
            .forgot-body {
                padding: 24px 20px;
            }
            
            .step {
                width: 34px;
                height: 34px;
                font-size: 0.85rem;
            }
            
            .step-line {
                width: 35px;
            }
            
            .step-indicator {
                margin-bottom: 20px;
                gap: 6px;
            }
            
            .form-label {
                font-size: 0.85rem;
                margin-bottom: 6px;
            }
            
            .form-control {
                padding: 11px 14px;
                font-size: 0.9rem;
            }
            
            .input-group-text {
                padding: 0 12px;
            }
            
            .input-group-text i {
                font-size: 1rem;
            }
            
            .btn-submit, .btn-back {
                padding: 11px;
                font-size: 0.88rem;
                letter-spacing: 1px;
            }
            
            .alert {
                padding: 11px 14px;
                font-size: 0.85rem;
            }
            
            .password-requirements {
                padding: 10px;
                font-size: 0.78rem;
            }
            
            small.text-muted {
                font-size: 0.78rem;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .forgot-container {
                margin: 5px auto;
            }
            
            .forgot-header {
                padding: 24px 16px;
            }
            
            .forgot-body {
                padding: 20px 16px;
            }
            
            .btn-submit, .btn-back {
                font-size: 0.85rem;
            }
        }
        
        /* Ensure content fits on screen */
        @media (max-height: 700px) {
            .forgot-header {
                padding: 25px 20px;
            }
            
            .forgot-icon {
                width: 55px;
                height: 55px;
                margin-bottom: 10px;
            }
            
            .forgot-icon i {
                font-size: 1.6rem;
            }
            
            .forgot-header h3 {
                font-size: 1.4rem;
                margin-bottom: 6px;
            }
            
            .forgot-body {
                padding: 22px;
            }
            
            .step-indicator {
                margin-bottom: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="forgot-container">
            <div class="forgot-card">
                <div class="forgot-header">
                    <div class="forgot-icon">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <h3>Reset Password</h3>
                    <p>Don't worry, we'll help you recover your account</p>
                </div>
                
                <div class="forgot-body">
                    <div class="step-indicator">
                        <div class="step <?php echo ($step >= 1) ? 'active' : ''; ?> <?php echo ($step > 1) ? 'completed' : ''; ?>">
                            <?php echo ($step > 1) ? '<i class="bi bi-check-lg"></i>' : '1'; ?>
                        </div>
                        <div class="step-line <?php echo ($step > 1) ? 'active' : ''; ?>"></div>
                        <div class="step <?php echo ($step >= 2) ? 'active' : ''; ?> <?php echo ($step > 2) ? 'completed' : ''; ?>">
                            <?php echo ($step > 2) ? '<i class="bi bi-check-lg"></i>' : '2'; ?>
                        </div>
                        <div class="step-line <?php echo ($step > 2) ? 'active' : ''; ?>"></div>
                        <div class="step <?php echo ($step >= 3) ? 'active' : ''; ?>">3</div>
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
                    
                    <?php if ($step == 1): ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-envelope-fill"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           placeholder="Enter your email address" required autofocus>
                                </div>
                                <small class="text-muted">Enter the email address associated with your account</small>
                            </div>
                            
                            <button type="submit" name="verify_email" class="btn btn-submit btn-primary w-100 mb-2">
                                <i class="bi bi-arrow-right me-2"></i>Verify Email
                            </button>
                            
                            <a href="login.php" class="btn btn-back w-100">
                                <i class="bi bi-arrow-left me-2"></i>Back to Login
                            </a>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($step == 2): ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="reset_code" class="form-label">Verification Code</label>
                                <p class="text-muted small mb-2">We sent a 6-digit code to <strong><?php echo htmlspecialchars($_SESSION['reset_email'] ?? 'your email'); ?></strong></p>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-shield-check"></i>
                                    </span>
                                    <input type="text" class="form-control" id="reset_code" name="reset_code" 
                                           placeholder="000000" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus>
                                </div>
                                <small class="text-muted">Enter the 6-digit code from your email</small>
                            </div>
                            
                            <button type="submit" name="verify_code" class="btn btn-submit btn-primary w-100 mb-2">
                                <i class="bi bi-arrow-right me-2"></i>Verify Code
                            </button>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="request_new_code" class="btn btn-outline-secondary btn-sm mb-2">
                                    <i class="bi bi-envelope me-2"></i>Didn't receive code? Send new code
                                </button>
                            </div>
                            
                            <a href="login.php" class="btn btn-back w-100">
                                <i class="bi bi-arrow-left me-2"></i>Back to Login
                            </a>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($step == 3): ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock-fill"></i>
                                    </span>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           placeholder="Enter new password" required autofocus>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock-fill"></i>
                                    </span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           placeholder="Confirm new password" required>
                                </div>
                            </div>
                            
                            <div class="password-requirements">
                                <strong><i class="bi bi-info-circle me-2"></i>Password Requirements:</strong>
                                <ul>
                                    <li>At least 6 characters long</li>
                                    <li>Must match in both fields</li>
                                    <li>Use a strong, unique password</li>
                                </ul>
                            </div>
                            
                            <button type="submit" name="reset_password" class="btn btn-submit btn-primary w-100 mt-3 mb-2">
                                <i class="bi bi-check-circle me-2"></i>Reset Password
                            </button>
                            
                            <a href="login.php" class="btn btn-back w-100">
                                <i class="bi bi-arrow-left me-2"></i>Back to Login
                            </a>
                        </form>h
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.all.min.js"></script>
    <script>
        const RESET_CODE_COOLDOWN_KEY = 'reset_code_cooldown_time';
        const RESET_CODE_COOLDOWN_DURATION = 600000; // 10 minutes in milliseconds
        let resendCountdownInterval = null;

        function getResetCodeCooldownTime() {
            return parseInt(localStorage.getItem(RESET_CODE_COOLDOWN_KEY) || '0');
        }

        function setResetCodeCooldownTime(time) {
            localStorage.setItem(RESET_CODE_COOLDOWN_KEY, time);
        }

        function isResetCodeOnCooldown() {
            const cooldownTime = getResetCodeCooldownTime();
            if (cooldownTime === 0) return false;
            
            const now = Date.now();
            if (now < cooldownTime) {
                return true;
            } else {
                setResetCodeCooldownTime(0);
                return false;
            }
        }

        function getSecondsUntilCanResend() {
            const cooldownTime = getResetCodeCooldownTime();
            const now = Date.now();
            const secondsRemaining = Math.ceil((cooldownTime - now) / 1000);
            return Math.max(0, secondsRemaining);
        }

        function formatTimeRemaining(seconds) {
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${minutes}:${secs.toString().padStart(2, '0')}`;
        }

        function updateResendCodeButton() {
            const resendBtn = document.querySelector('button[name="request_new_code"]');
            if (!resendBtn) return;

            if (isResetCodeOnCooldown()) {
                const secondsRemaining = getSecondsUntilCanResend();
                resendBtn.disabled = true;
                resendBtn.innerHTML = `<i class="bi bi-hourglass-split me-2"></i>Wait ${formatTimeRemaining(secondsRemaining)} to resend`;
                resendBtn.style.opacity = '0.6';
                resendBtn.style.cursor = 'not-allowed';

                // Clear existing interval if any
                if (resendCountdownInterval) clearInterval(resendCountdownInterval);

                // Update countdown every second
                resendCountdownInterval = setInterval(() => {
                    const remaining = getSecondsUntilCanResend();
                    if (remaining > 0) {
                        resendBtn.innerHTML = `<i class="bi bi-hourglass-split me-2"></i>Wait ${formatTimeRemaining(remaining)} to resend`;
                    } else {
                        clearInterval(resendCountdownInterval);
                        resendBtn.disabled = false;
                        resendBtn.innerHTML = '<i class="bi bi-envelope me-2"></i>Didn\'t receive code? Send new code';
                        resendBtn.style.opacity = '1';
                        resendBtn.style.cursor = 'pointer';
                        
                        Swal.fire({
                            icon: 'info',
                            title: 'Ready to Resend',
                            text: 'You can now request a new verification code.',
                            confirmButtonColor: '#00A8E8'
                        });
                    }
                }, 1000);
            } else {
                resendBtn.disabled = false;
                resendBtn.innerHTML = '<i class="bi bi-envelope me-2"></i>Didn\'t receive code? Send new code';
                resendBtn.style.opacity = '1';
                resendBtn.style.cursor = 'pointer';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Check and restore cooldown on page load
            updateResendCodeButton();

            // Handle request_new_code button click
            const resendBtn = document.querySelector('button[name="request_new_code"]');
            if (resendBtn) {
                resendBtn.addEventListener('click', function(e) {
                    if (isResetCodeOnCooldown()) {
                        e.preventDefault();
                        const secondsRemaining = getSecondsUntilCanResend();
                        Swal.fire({
                            icon: 'warning',
                            title: 'Please Wait',
                            text: `You can send a new code in ${formatTimeRemaining(secondsRemaining)}`,
                            confirmButtonColor: '#00A8E8'
                        });
                        return false;
                    }
                });
            }

            // Handle form submission to set cooldown
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const resendBtn = form.querySelector('button[name="request_new_code"]');
                    if (resendBtn && e.submitter === resendBtn) {
                        // Set cooldown when resending code
                        setResetCodeCooldownTime(Date.now() + RESET_CODE_COOLDOWN_DURATION);
                        
                        // Show notification
                        setTimeout(() => {
                            Swal.fire({
                                icon: 'success',
                                title: 'Code Sent!',
                                text: 'A new verification code has been sent to your email. Please wait 10 minutes before requesting another code.',
                                confirmButtonColor: '#00A8E8'
                            });
                            
                            // Update button
                            updateResendCodeButton();
                        }, 500);
                    }
                });
            }
        });
    </script>