<?php
/**
 * PIN Verification System Configuration
 * Secure PIN-based authentication for sensitive operations
 */

// PIN Configuration
define('PIN_LENGTH', 6); // PIN must be 6 digits
define('PIN_MAX_ATTEMPTS', 3); // Maximum failed attempts before lockout
define('PIN_LOCKOUT_DURATION', 60); // Lockout duration in seconds (60 seconds)
define('PIN_SESSION_KEY', 'pin_attempts'); // Session key for tracking attempts
define('PIN_LOCKOUT_KEY', 'pin_lockout_time'); // Session key for lockout timestamp

/**
 * Initialize PIN session tracking
 */
function init_pin_session() {
    if (!isset($_SESSION[PIN_SESSION_KEY])) {
        $_SESSION[PIN_SESSION_KEY] = 0;
    }
    if (!isset($_SESSION[PIN_LOCKOUT_KEY])) {
        $_SESSION[PIN_LOCKOUT_KEY] = 0;
    }
}

/**
 * Check if PIN attempts are locked out
 * @return bool True if locked out, false otherwise
 */
function is_pin_locked() {
    init_pin_session();
    
    if ($_SESSION[PIN_LOCKOUT_KEY] > 0) {
        $remaining = $_SESSION[PIN_LOCKOUT_KEY] - time();
        if ($remaining > 0) {
            return true;
        } else {
            // Reset lockout
            $_SESSION[PIN_LOCKOUT_KEY] = 0;
            $_SESSION[PIN_SESSION_KEY] = 0;
            return false;
        }
    }
    return false;
}

/**
 * Get remaining lockout time in seconds
 * @return int Remaining seconds, 0 if not locked
 */
function get_lockout_remaining() {
    init_pin_session();
    
    if ($_SESSION[PIN_LOCKOUT_KEY] > 0) {
        $remaining = $_SESSION[PIN_LOCKOUT_KEY] - time();
        return max(0, $remaining);
    }
    return 0;
}

/**
 * Increment failed PIN attempts
 * @return array Status with 'locked' boolean and 'remaining' attempts
 */
function increment_pin_attempts() {
    init_pin_session();
    
    $_SESSION[PIN_SESSION_KEY]++;
    
    if ($_SESSION[PIN_SESSION_KEY] >= PIN_MAX_ATTEMPTS) {
        $_SESSION[PIN_LOCKOUT_KEY] = time() + PIN_LOCKOUT_DURATION;
        $remaining_attempts = 0;
        $locked = true;
    } else {
        $remaining_attempts = PIN_MAX_ATTEMPTS - $_SESSION[PIN_SESSION_KEY];
        $locked = false;
    }
    
    return [
        'locked' => $locked,
        'remaining' => $remaining_attempts,
        'lockout_duration' => PIN_LOCKOUT_DURATION
    ];
}

/**
 * Reset PIN attempts after successful verification
 */
function reset_pin_attempts() {
    init_pin_session();
    $_SESSION[PIN_SESSION_KEY] = 0;
    $_SESSION[PIN_LOCKOUT_KEY] = 0;
}

/**
 * Verify admin PIN against database
 * @param int $user_id User ID
 * @param string $pin Entered PIN
 * @param object $conn Database connection
 * @return array Result with 'success' boolean and 'message'
 */
function verify_admin_pin($user_id, $pin, $conn) {
    // Check if locked out
    if (is_pin_locked()) {
        return [
            'success' => false,
            'message' => 'Too many failed attempts. Please wait ' . get_lockout_remaining() . ' seconds.',
            'locked' => true,
            'remaining_time' => get_lockout_remaining()
        ];
    }
    
    // Validate PIN format
    if (!preg_match('/^\d{' . PIN_LENGTH . '}$/', $pin)) {
        return [
            'success' => false,
            'message' => 'PIN must be exactly ' . PIN_LENGTH . ' digits.',
            'locked' => false
        ];
    }
    
    // Fetch user PIN from database
    $stmt = mysqli_prepare($conn, "SELECT admin_pin, role FROM users WHERE user_id = ? AND status = 'active'");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user = mysqli_fetch_assoc($result)) {
        // Verify PIN
        if ($user['admin_pin'] === $pin) {
            reset_pin_attempts();
            
            // Log successful PIN verification
            if (function_exists('log_audit')) {
                log_audit($user_id, 'PIN_VERIFY', 'authentication', null, null, [
                    'status' => 'success',
                    'action' => 'Admin PIN verified'
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'PIN verified successfully.',
                'role' => $user['role']
            ];
        } else {
            // Increment failed attempts
            $attempt_result = increment_pin_attempts();
            
            // Log failed PIN attempt
            if (function_exists('log_audit')) {
                log_audit($user_id, 'PIN_FAIL', 'authentication', null, null, [
                    'status' => 'failed',
                    'attempts' => $_SESSION[PIN_SESSION_KEY],
                    'locked' => $attempt_result['locked']
                ]);
            }
            
            if ($attempt_result['locked']) {
                return [
                    'success' => false,
                    'message' => 'Maximum attempts exceeded. Account locked for ' . PIN_LOCKOUT_DURATION . ' seconds.',
                    'locked' => true,
                    'remaining_time' => PIN_LOCKOUT_DURATION
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Incorrect PIN. ' . $attempt_result['remaining'] . ' attempt(s) remaining.',
                    'locked' => false,
                    'remaining_attempts' => $attempt_result['remaining']
                ];
            }
        }
    } else {
        return [
            'success' => false,
            'message' => 'User not found or inactive.',
            'locked' => false
        ];
    }
}

/**
 * Check if user has admin PIN set
 * @param int $user_id User ID
 * @param object $conn Database connection
 * @return bool True if PIN is set, false otherwise
 */
function has_admin_pin($user_id, $conn) {
    $stmt = mysqli_prepare($conn, "SELECT admin_pin FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user = mysqli_fetch_assoc($result)) {
        return !empty($user['admin_pin']);
    }
    return false;
}