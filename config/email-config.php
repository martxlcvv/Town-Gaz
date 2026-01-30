<?php
/**
 * Email Configuration for Password Reset & Notifications
 * Uses PHPMailer for SMTP email sending
 */

// SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'raymartalejado55@gmail.com'); // Change this to your Gmail
define('SMTP_PASSWORD', 'vtpzdlqgavroains'); // Remove spaces from app password
define('SENDER_EMAIL', 'towngazpos@gmail.com');
define('SENDER_NAME', 'Town Gas POS System');

// Code Settings
define('RESET_CODE_LENGTH', 6); // 6-digit code
define('RESET_CODE_EXPIRY', 30); // 30 minutes

/**
 * Send Reset Code via Email
 */
function sendResetCode($to_email, $to_name, $reset_code) {
    global $conn;
    
    try {
        // Load PHPMailer - check if installed via Composer
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once(__DIR__ . '/../vendor/autoload.php');
            
            $mail = new \PHPMailer\PHPMailer\PHPMailer();
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->SMTPAutoTLS = false;
            $mail->Timeout = 10;
            $mail->ConnectTimeout = 10;
            
            // Set sender and recipient
            $mail->setFrom(SENDER_EMAIL, SENDER_NAME);
            $mail->addAddress($to_email, $to_name);
            
            // Email content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Code - Town Gas POS';
            
            $html = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; }
                    .header { background: linear-gradient(135deg, #00A8E8, #007EA7); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; border-radius: 0 0 8px 8px; }
                    .code-box { background: #FFF; border: 2px dashed #00A8E8; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
                    .code { font-size: 32px; font-weight: bold; color: #00A8E8; letter-spacing: 5px; }
                    .expiry { color: #E63946; font-weight: bold; }
                    .footer { color: #999; font-size: 12px; text-align: center; padding-top: 20px; border-top: 1px solid #ddd; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Password Reset Request</h2>
                    </div>
                    <div class='content'>
                        <p>Hello <strong>$to_name</strong>,</p>
                        <p>We received a request to reset your password for your Town Gas POS account.</p>
                        <p>Use the code below to reset your password:</p>
                        
                        <div class='code-box'>
                            <div class='code'>$reset_code</div>
                        </div>
                        
                        <p><strong>Important:</strong></p>
                        <ul>
                            <li>This code expires in <span class='expiry'>30 minutes</span></li>
                            <li>Do not share this code with anyone</li>
                            <li>If you didn't request this, please ignore this email</li>
                        </ul>
                        
                        <p>If you have any issues, contact your system administrator.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " Town Gas POS System. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->Body = $html;
            $mail->AltBody = "Your password reset code is: $reset_code\n\nThis code expires in 30 minutes.";
            
            // Enable debugging
            $mail->SMTPDebug = 2;
            ob_start();
            
            // Send email
            if ($mail->send()) {
                ob_end_clean();
                return true;
            } else {
                $debug = ob_get_clean();
                error_log('===== EMAIL SEND FAILED =====');
                error_log('To: ' . $to_email);
                error_log('SMTP Host: ' . SMTP_HOST);
                error_log('SMTP User: ' . SMTP_USER);
                error_log('SMTP Port: ' . SMTP_PORT);
                error_log('Error: ' . $mail->ErrorInfo);
                error_log('Debug Output: ' . $debug);
                error_log('===== END ERROR LOG =====');
                return false;
            }
        } else {
            // Fallback: Log error that PHPMailer not installed
            error_log('PHPMailer not installed. Cannot send email.');
            return false;
        }
    } catch (Exception $e) {
        error_log('Email error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Generate random reset code
 */
function generateResetCode() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Store reset code in database
 */
function storeResetCode($user_id, $reset_code, $conn) {
    $code_hash = password_hash($reset_code, PASSWORD_DEFAULT);
    $expiry_time = date('Y-m-d H:i:s', strtotime('+' . RESET_CODE_EXPIRY . ' minutes'));
    
    // Check if reset code already exists
    $check_sql = "SELECT id FROM password_resets WHERE user_id = ? AND is_used = 0";
    $stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        // Update existing
        $update_sql = "UPDATE password_resets SET code_hash = ?, expires_at = ?, created_at = NOW() 
                       WHERE user_id = ? AND is_used = 0";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "ssi", $code_hash, $expiry_time, $user_id);
        return mysqli_stmt_execute($stmt);
    } else {
        // Insert new
        $insert_sql = "INSERT INTO password_resets (user_id, code_hash, expires_at, is_used, created_at) 
                       VALUES (?, ?, ?, 0, NOW())";
        $stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt, "iss", $user_id, $code_hash, $expiry_time);
        return mysqli_stmt_execute($stmt);
    }
}

/**
 * Verify reset code
 */
function verifyResetCode($user_id, $reset_code, $conn) {
    $sql = "SELECT code_hash, expires_at FROM password_resets 
            WHERE user_id = ? AND is_used = 0 AND expires_at > NOW()";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        return password_verify($reset_code, $row['code_hash']);
    }
    
    return false;
}

/**
 * Mark reset code as used
 */
function markResetCodeAsUsed($user_id, $conn) {
    $sql = "UPDATE password_resets SET is_used = 1 WHERE user_id = ? AND is_used = 0";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    return mysqli_stmt_execute($stmt);
}
?>
