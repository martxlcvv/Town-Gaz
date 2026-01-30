<?php
/**
 * Database Configuration File
 * Handles MySQL connection for Town Gas POS System
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tow_gas');

// Connection timeout settings (in seconds)
define('DB_CONNECT_TIMEOUT', 10);
define('DB_READ_TIMEOUT', 30);

// Initialize connection with proper error handling
$conn = null;

try {
    // Initialize mysqli
    $conn = mysqli_init();
    
    if (!$conn) {
        throw new Exception("mysqli_init failed");
    }
    
    // Set connection options
    mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, DB_CONNECT_TIMEOUT);
    mysqli_options($conn, MYSQLI_OPT_READ_TIMEOUT, DB_READ_TIMEOUT);
    
    // Note: MYSQLI_OPT_RECONNECT is deprecated in PHP 8.2+
    // We'll handle reconnection manually in check_db_connection()
    
    // Attempt real connection
    $connected = mysqli_real_connect(
        $conn,
        DB_HOST,
        DB_USER,
        DB_PASS,
        DB_NAME
    );
    
    if (!$connected) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }
    
    // Set charset to utf8mb4 for proper character support
    if (!mysqli_set_charset($conn, "utf8mb4")) {
        throw new Exception("Error setting charset: " . mysqli_error($conn));
    }
    
    // Set SQL mode for better compatibility
    mysqli_query($conn, "SET sql_mode = ''");
    
    // Set session wait_timeout
    mysqli_query($conn, "SET SESSION wait_timeout = 600");
    mysqli_query($conn, "SET SESSION interactive_timeout = 600");
    
} catch (Exception $e) {
    // Create logs directory if it doesn't exist
    $logs_dir = __DIR__ . '/../logs';
    if (!file_exists($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }
    
    // Log error to file
    error_log(date('[Y-m-d H:i:s] ') . $e->getMessage() . "\n", 3, $logs_dir . '/db_errors.log');
    
    // Display user-friendly error
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Connection Error</title>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .error-box {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                max-width: 500px;
                text-align: center;
            }
            h2 { color: #e74c3c; margin-bottom: 20px; }
            p { color: #555; line-height: 1.6; }
            .icon { font-size: 60px; margin-bottom: 20px; }
            ul { text-align: left; margin: 20px 0; color: #666; }
            .btn {
                display: inline-block;
                margin-top: 20px;
                padding: 12px 30px;
                background: #3498db;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                transition: background 0.3s;
                cursor: pointer;
            }
            .btn:hover { background: #2980b9; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <div class="icon">⚠️</div>
            <h2>Database Connection Error</h2>
            <p><strong>Unable to connect to the database.</strong></p>
            <p>Please check the following:</p>
            <ul>
                <li>XAMPP MySQL service is running</li>
                <li>Database credentials are correct</li>
                <li>Database "tow_gas" exists</li>
            </ul>
            <a href="#" onclick="location.reload()" class="btn">Retry Connection</a>
        </div>
    </body>
    </html>
    ');
}

// Timezone setting
date_default_timezone_set('Asia/Manila');

/**
 * Function to check and maintain database connection
 * @return bool - Connection status
 */
function check_db_connection() {
    global $conn;
    
    if (!$conn) {
        return false;
    }
    
    // Ping the connection to check if it's alive
    if (!mysqli_ping($conn)) {
        // Connection lost, attempt to reconnect
        mysqli_close($conn);
        
        $conn = mysqli_init();
        
        if (!$conn) {
            return false;
        }
        
        mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, DB_CONNECT_TIMEOUT);
        
        $connected = mysqli_real_connect(
            $conn,
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME
        );
        
        if ($connected) {
            mysqli_set_charset($conn, "utf8mb4");
            mysqli_query($conn, "SET SESSION wait_timeout = 600");
            mysqli_query($conn, "SET SESSION interactive_timeout = 600");
            return true;
        }
        return false;
    }
    
    return true;
}

/**
 * Function to execute safe query with connection check
 * @param string $sql - SQL query
 * @return mysqli_result|bool - Query result
 */
function safe_query($sql) {
    global $conn;
    
    if (!check_db_connection()) {
        $logs_dir = __DIR__ . '/../logs';
        error_log(date('[Y-m-d H:i:s] ') . "Database connection lost before query: " . $sql . "\n", 3, $logs_dir . '/query_errors.log');
        return false;
    }
    
    $result = mysqli_query($conn, $sql);
    
    if (!$result) {
        $logs_dir = __DIR__ . '/../logs';
        error_log(date('[Y-m-d H:i:s] ') . "Query failed: " . mysqli_error($conn) . " | SQL: " . $sql . "\n", 3, $logs_dir . '/query_errors.log');
    }
    
    return $result;
}

/**
 * Function to sanitize input data
 * @param string $data - Input data to sanitize
 * @return string - Sanitized data
 */
function clean_input($data) {
    global $conn;
    
    if (!check_db_connection()) {
        return htmlspecialchars(trim(stripslashes($data)), ENT_QUOTES, 'UTF-8');
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}

/**
 * Function to log audit trail with prepared statement
 * @param int $user_id - User performing action
 * @param string $action - Action performed
 * @param string $table_name - Table affected
 * @param int $record_id - Record ID affected
 * @param mixed $old_value - Old value (will be JSON encoded)
 * @param mixed $new_value - New value (will be JSON encoded)
 * @return bool - Success status
 */
function log_audit($user_id, $action, $table_name, $record_id = null, $old_value = null, $new_value = null) {
    global $conn;
    
    if (!check_db_connection()) {
        $logs_dir = __DIR__ . '/../logs';
        error_log(date('[Y-m-d H:i:s] ') . "Cannot log audit: Database connection lost\n", 3, $logs_dir . '/audit_errors.log');
        return false;
    }
    
    $ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    $old_value_json = $old_value ? json_encode($old_value) : null;
    $new_value_json = $new_value ? json_encode($new_value) : null;
    
    $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_value, new_value, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "issssss", 
            $user_id, 
            $action, 
            $table_name, 
            $record_id, 
            $old_value_json, 
            $new_value_json, 
            $ip_address
        );
        
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        return $result;
    }
    
    return false;
}

/**
 * Function to format currency
 * @param float $amount - Amount to format
 * @return string - Formatted currency
 */
function format_currency($amount) {
    return '₱' . number_format((float)$amount, 2);
}

/**
 * Function to generate invoice number
 * @return string - Invoice number
 */
function generate_invoice_number() {
    return 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Function to get database status
 * @return array - Database status information
 */
function get_db_status() {
    global $conn;
    
    $status = [
        'connected' => false,
        'database' => DB_NAME,
        'charset' => null,
        'server_info' => null,
        'php_version' => phpversion(),
        'mysqli_version' => function_exists('mysqli_get_client_info') ? mysqli_get_client_info() : 'N/A'
    ];
    
    if ($conn && mysqli_ping($conn)) {
        $status['connected'] = true;
        $status['charset'] = mysqli_character_set_name($conn);
        $status['server_info'] = mysqli_get_server_info($conn);
    }
    
    return $status;
}

/**
 * Function to test database connection
 * @return bool - Connection test result
 */
function test_db_connection() {
    global $conn;
    
    if (!$conn) {
        return false;
    }
    
    $result = mysqli_query($conn, "SELECT 1");
    
    if ($result) {
        mysqli_free_result($result);
        return true;
    }
    
    return false;
}

/**
 * Function to safely close database connection
 * This is called automatically at script end
 */
function close_db_connection() {
    global $conn;
    
    // Check if connection exists and is a valid mysqli object
    if ($conn instanceof mysqli) {
        try {
            // Suppress all errors and warnings
            @mysqli_close($conn);
        } catch (Throwable $e) {
            // Silently catch any error (already closed, etc.)
        } finally {
            // Always set to null to prevent double closing
            $conn = null;
        }
    }
}

// Register shutdown function to close connection properly
register_shutdown_function('close_db_connection');

/**
 * Function to prevent browser caching and back button
 * Call this function at the beginning of pages that should not be cached
 */
function prevent_cache() {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: 0");
}

/**
 * Function to generate a session token
 * Creates a secure random token for session validation
 * @return string - The generated session token
 */
function generate_session_token() {
    if (empty($_SESSION['session_token'])) {
        $_SESSION['session_token'] = bin2hex(random_bytes(32));
        $_SESSION['token_created'] = time();
    }
    return $_SESSION['session_token'];
}

/**
 * Function to validate session token
 * Ensures the current session has a valid token
 * @return bool - True if token is valid, false otherwise
 */
function validate_session_token() {
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    
    if (empty($_SESSION['session_token'])) {
        return false;
    }
    
    // Check if token expires after 24 hours
    $token_age = time() - $_SESSION['token_created'];
    $max_token_age = 86400; // 24 hours
    
    if ($token_age > $max_token_age) {
        regenerate_session_token();
        return false;
    }
    
    return true;
}

/**
 * Function to regenerate session token (prevents fixation attacks)
 * Creates a new token while maintaining session data
 */
function regenerate_session_token() {
    $_SESSION['session_token'] = bin2hex(random_bytes(32));
    $_SESSION['token_created'] = time();
    $_SESSION['token_regenerated'] = true;
}

/**
 * Function to get session token for forms
 * Returns the current session token to be used in forms
 * @return string - The session token or empty string if not set
 */
function get_session_token() {
    if (empty($_SESSION['session_token'])) {
        generate_session_token();
    }
    return $_SESSION['session_token'];
}

/**
 * Function to output session token as hidden input field
 * Use this in all forms to include the token
 */
function output_token_field() {
    $token = get_session_token();
    return '<input type="hidden" name="session_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Function to verify CSRF token from form submission
 * @param string $token - The token from POST data
 * @return bool - True if token is valid
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['session_token'])) {
        error_log('[CSRF] session token missing in session');
        return false;
    }
    
    if (empty($token)) {
        // Try to read token from HTTP headers as fallback (X-CSRF-Token)
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
            error_log('[CSRF] token found in HTTP_X_CSRF_TOKEN header');
        } else {
            // Some servers expose headers with HTTP_ prefix differently
            $allHeaders = function_exists('getallheaders') ? getallheaders() : [];
            if (!empty($allHeaders['X-CSRF-Token'])) {
                $token = $allHeaders['X-CSRF-Token'];
                error_log('[CSRF] token found in X-CSRF-Token header via getallheaders');
            } elseif (!empty($allHeaders['x-csrf-token'])) {
                $token = $allHeaders['x-csrf-token'];
                error_log('[CSRF] token found in x-csrf-token header via getallheaders');
            } else {
                error_log('[CSRF] token missing in POST and all headers. POST keys: ' . implode(',', array_keys($_POST)));
                return false;
            }
        }
    } else {
        error_log('[CSRF] token found in POST data (session_token field)');
    }
    
    // Use hash_equals for timing-safe comparison
    $match = hash_equals($_SESSION['session_token'], $token);
    if (!$match) {
        error_log('[CSRF] token mismatch: session=' . substr($_SESSION['session_token'],0,10) . '..., provided=' . substr($token,0,10) . '...');
    } else {
        error_log('[CSRF] token verified successfully');
    }
    return $match;
}

// Create logs directory if it doesn't exist
$logs_dir = __DIR__ . '/../logs';
if (!file_exists($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}
?>