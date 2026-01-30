<?php
/**
 * Authentication Check File
 * Include this file at the top of protected pages
 * Ensures user is logged in and has proper access
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent browser caching of protected pages to avoid back-button access
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Generate session token if not exists (ensure token is present before validation)
if (function_exists('generate_session_token')) {
    generate_session_token();
}

// Validate session token
if (function_exists('validate_session_token') && !validate_session_token()) {
    session_destroy();
    header('Location: ../auth/login.php?session_expired=1');
    exit();
}

// Function to check admin access
function require_admin() {
    if (!isset($_SESSION['role_name']) || $_SESSION['role_name'] != 'Admin') {
        header('Location: ../auth/login.php');
        exit();
    }
}

// Function to check staff access
function require_staff() {
    if (!isset($_SESSION['role_name']) || ($_SESSION['role_name'] != 'Staff' && $_SESSION['role_name'] != 'Admin')) {
        header('Location: ../auth/login.php');
        exit();
    }
}

// Function to show login success modal
function show_login_success_modal() {
    if (isset($_GET['login']) && $_GET['login'] == 'success' && isset($_SESSION['login_success'])) {
        $role = isset($_SESSION['role_name']) ? $_SESSION['role_name'] : '';
        $name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : '';

        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeRole = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');

        echo <<<HTML
        <style>
            .login-success-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); display:flex; align-items:center; justify-content:center; z-index:99999; animation: fadeIn 0.3s ease; backdrop-filter: blur(5px); }
            @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
            .login-success-modal { background: white; padding: 50px 40px; border-radius:25px; text-align:center; max-width:450px; width:90%; box-shadow: 0 25px 80px rgba(0,0,0,0.4); animation: slideUp 0.5s ease; position:relative; }
            @keyframes slideUp { from { opacity:0; transform: translateY(50px) scale(0.9); } to { opacity:1; transform: translateY(0) scale(1); } }
            .login-success-icon { width:100px; height:100px; background: linear-gradient(135deg,#00A8E8,#007EA7); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 25px; animation: checkmark 0.6s ease 0.3s both; box-shadow: 0 10px 30px rgba(0,168,232,0.4); }
            @keyframes checkmark { 0%{ transform: scale(0) rotate(-180deg);} 50%{ transform: scale(1.3) rotate(10deg);} 100%{ transform: scale(1) rotate(0deg);} }
            .login-success-title { font-size:2rem; font-weight:800; background: linear-gradient(135deg,#00A8E8,#007EA7); -webkit-background-clip:text; -webkit-text-fill-color:transparent; margin-bottom:15px; }
            .login-success-message { font-size:1.15rem; color:#6C757D; margin-bottom:25px; font-weight:500; }
            .login-success-details { background: linear-gradient(135deg,#F8F9FA,#E9ECEF); padding:20px; border-radius:15px; margin-bottom:25px; border:2px solid rgba(0,168,232,0.1); }
            .role-badge { display:inline-block; padding:8px 20px; border-radius:20px; font-weight:700; font-size:0.95rem; margin-top:10px; }
            .role-admin { background: linear-gradient(135deg,#E63946,#D62828); color:white; }
            .role-staff { background: linear-gradient(135deg,#00A8E8,#007EA7); color:white; }
            .login-success-btn { background: linear-gradient(135deg,#00A8E8,#007EA7); color:white; border:none; padding:15px 40px; border-radius:12px; font-weight:700; cursor:pointer; }
        </style>
        <div class="login-success-overlay" id="loginSuccessOverlay">
            <div class="login-success-modal">
                <div class="login-success-icon"><i class="bi bi-check-lg"></i></div>
                <h3 class="login-success-title">Login Successful!</h3>
                <p class="login-success-message">Welcome back to Town Gaz POS System</p>
                <div class="login-success-details">
                    <p><i class="bi bi-person-circle me-2"></i>{$safeName}</p>
                    <span class="role-badge role-{$safeRole}"><i class="bi bi-shield-check me-2"></i>{$safeRole} Access</span>
                </div>
                <button class="login-success-btn" onclick="closeLoginSuccess()"><i class="bi bi-arrow-right me-2"></i>Continue to Dashboard</button>
            </div>
        </div>
        <script>
            function closeLoginSuccess(){ const overlay=document.getElementById("loginSuccessOverlay"); overlay.style.animation="fadeOut 0.3s ease"; setTimeout(()=>{ overlay.style.display="none"; window.history.replaceState({}, document.title, window.location.pathname); },300); }
            setTimeout(function(){ closeLoginSuccess(); },4000);
            document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeLoginSuccess(); });
        </script>
        HTML;

        // Unset the login success flag
        unset($_SESSION['login_success']);
    }
}

// Prevent browsers' back-forward cache from restoring protected pages.
// If a page is restored from bfcache, force a reload so server-side
// session checks run and redirect logged-out users to login.
echo <<<JS
<script>
window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        try { window.location.reload(); } catch (e) {}
    }
});
// Register an unload handler to make the page ineligible for bfcache in some browsers
window.addEventListener('unload', function() {});
</script>
JS;

?>

<?php
// Client-side session guard: fetch session_check on load/popstate and redirect if unauthorized
$sessionCheck = dirname($_SERVER['SCRIPT_NAME']) . '/../auth/session_check.php';
echo "<script>\n(function(){\n  const checkUrl = '" . addslashes($sessionCheck) . "';\n  function verify() {\n    fetch(checkUrl, { credentials: 'same-origin' }).then(r => { if (r.status !== 200) { window.location.replace('/" . trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), "/") . "'); } }).catch(() => { /* ignore */ });\n  }\n  window.addEventListener('load', verify);\n  window.addEventListener('popstate', verify);\n  window.addEventListener('pageshow', function(e){ if (e.persisted) verify(); });\n})();\n</script>";