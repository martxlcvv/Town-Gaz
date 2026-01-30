<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_admin();
prevent_cache();

$page_title = "User Management";

// Handle user operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify session token
    if (!verify_csrf_token($_POST['session_token'] ?? '')) {
        $_SESSION['error_message'] = "Invalid session. Please try again.";
        header('Location: users.php');
        exit();
    }
    
    $is_fetch = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if (isset($_POST['add_user'])) {
        $username = clean_input($_POST['username']);
        $email = clean_input($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $full_name = clean_input($_POST['full_name']);
        $contact = clean_input($_POST['contact']);
        $role_id = clean_input($_POST['role_id']);
        
        // Check if username or email exists
        $check_sql = "SELECT * FROM users WHERE username = '$username' OR email = '$email'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $_SESSION['error_message'] = "Username or email already exists!";
            if ($is_fetch) {
                echo json_encode(['status' => 'error', 'message' => 'Username or email already exists!']);
                exit();
            }
        } else {
            $sql = "INSERT INTO users (username, email, password, full_name, contact, role_id) 
                    VALUES ('$username', '$email', '$password', '$full_name', '$contact', $role_id)";
            
            if (mysqli_query($conn, $sql)) {
                log_audit($_SESSION['user_id'], 'CREATE', 'users', mysqli_insert_id($conn), null, 
                         ['username' => $username, 'email' => $email]);
                $_SESSION['success_message'] = "User created successfully!";
                if ($is_fetch) {
                    echo "User created successfully!";
                    exit();
                }
            } else {
                if ($is_fetch) {
                    echo json_encode(['status' => 'error', 'message' => 'Database error']);
                    exit();
                }
            }
        }
        if (!$is_fetch) {
            header('Location: users.php');
            exit();
        }
    }
    
    if (isset($_POST['edit_user'])) {
        $user_id = clean_input($_POST['user_id']);
        $username = clean_input($_POST['username']);
        $email = clean_input($_POST['email']);
        $full_name = clean_input($_POST['full_name']);
        $contact = clean_input($_POST['contact']);
        $role_id = clean_input($_POST['role_id']);
        
        // Get old values
        $old_sql = "SELECT * FROM users WHERE user_id = $user_id";
        $old_data = mysqli_fetch_assoc(mysqli_query($conn, $old_sql));
        
        $sql = "UPDATE users 
                SET username = '$username', email = '$email', 
                    full_name = '$full_name', contact = '$contact', role_id = $role_id
                WHERE user_id = $user_id";
        
        if (mysqli_query($conn, $sql)) {
            log_audit($_SESSION['user_id'], 'UPDATE', 'users', $user_id, $old_data, 
                     ['username' => $username, 'email' => $email]);
            $_SESSION['success_message'] = "User updated successfully!";
        }
        header('Location: users.php');
        exit();
    }
    
    if (isset($_POST['change_password'])) {
        $user_id = clean_input($_POST['user_id']);
        $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        
        $sql = "UPDATE users SET password = '$new_password' WHERE user_id = $user_id";
        
        if (mysqli_query($conn, $sql)) {
            log_audit($_SESSION['user_id'], 'PASSWORD_CHANGE', 'users', $user_id, null, 
                     ['action' => 'Password reset by admin']);
            $_SESSION['success_message'] = "Password changed successfully!";
            if ($is_fetch) {
                echo "Password changed successfully!";
                exit();
            }
        } else {
            if ($is_fetch) {
                echo json_encode(['status' => 'error', 'message' => 'Database error']);
                exit();
            }
        }
        if (!$is_fetch) {
            header('Location: users.php');
            exit();
        }
    }
    
    if (isset($_POST['toggle_status'])) {
        $user_id = clean_input($_POST['user_id']);
        $new_status = clean_input($_POST['new_status']);
        
        $sql = "UPDATE users SET status = '$new_status' WHERE user_id = $user_id";
        
        if (mysqli_query($conn, $sql)) {
            log_audit($_SESSION['user_id'], 'STATUS_CHANGE', 'users', $user_id, null, 
                     ['status' => $new_status]);
            $_SESSION['success_message'] = "User status updated successfully!";
        }
        header('Location: users.php');
        exit();
    }
}

// Get all users
$users_sql = "SELECT u.*, r.role_name,
              (SELECT COUNT(*) FROM sales WHERE user_id = u.user_id AND status = 'completed') as total_sales,
              (SELECT MAX(login_time) FROM attendance WHERE user_id = u.user_id) as last_login
              FROM users u
              JOIN roles r ON u.role_id = r.role_id
              ORDER BY u.created_at DESC";
$users_result = mysqli_query($conn, $users_sql);

// Get roles
$roles_sql = "SELECT * FROM roles";
$roles_result = mysqli_query($conn, $roles_sql);

// Count statistics
$total_users = mysqli_num_rows($users_result);
$active_users = 0;
$admin_count = 0;
$cashier_count = 0;

mysqli_data_seek($users_result, 0);
while ($u = mysqli_fetch_assoc($users_result)) {
    if ($u['status'] == 'active') $active_users++;
    if ($u['role_name'] == 'Admin') $admin_count++;
    if ($u['role_name'] == 'Cashier') $cashier_count++;
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.all.min.js"></script>

<style>
/* Dashboard Header Styles */
.dashboard-header {
    background: linear-gradient(135deg, #1a4d5c 0%, #0f3543 100%);
    border-radius: 12px;
    padding: 2rem 1.5rem 1.5rem 1.5rem;
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 6px 30px rgba(26, 77, 92, 0.25);
    color: white;
    backdrop-filter: blur(10px);
}

.header-content h1 {
    font-size: 2.2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    color: #ffffff;
    letter-spacing: 0.5px;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
}

.header-content p {
    color: rgba(255, 255, 255, 0.95);
    margin-bottom: 0;
    font-size: 1rem;
    letter-spacing: 0.3px;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.animate-slide-in {
    animation: slideLeft 0.5s ease-out;
    animation-fill-mode: both;
}

.animate-fade-in {
    animation: fadeIn 0.8s ease-out;
    animation-fill-mode: both;
}

@keyframes slideLeft {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.me-2 {
    margin-right: 0.5rem;
}

.mb-0 {
    margin-bottom: 0;
}

/* Users Page Styling */
:root {
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
}

body {
    background-color: var(--light-bg);
    color: var(--text-dark);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.btn-add-user {
    background: var(--primary-blue);
    border: none;
    color: white;
    padding: 0.6rem 1.25rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.btn-add-user:hover {
    background: #2980b9;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}

/* Stats Cards - COMPACT */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.stat-card {
    background: var(--card-bg);
    border-radius: 10px;
    padding: 1.15rem;
    box-shadow: var(--shadow-light);
    transition: all 0.3s ease;
    border-top: 4px solid;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-medium);
}

.stat-card.primary {
    border-top-color: var(--primary-blue);
}

.stat-card.success {
    border-top-color: var(--primary-green);
}

.stat-card.info {
    border-top-color: var(--primary-red);
}

.stat-card.warning {
    border-top-color: var(--primary-yellow);
}

.stat-card-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.stat-card.primary .stat-card-icon {
    background: rgba(52, 152, 219, 0.1);
    color: var(--primary-blue);
}

.stat-card.success .stat-card-icon {
    background: rgba(39, 174, 96, 0.1);
    color: var(--primary-green);
}

.stat-card.info .stat-card-icon {
    background: rgba(231, 76, 60, 0.1);
    color: var(--primary-red);
}

.stat-card.warning .stat-card-icon {
    background: rgba(241, 196, 15, 0.1);
    color: var(--primary-yellow);
}

.stat-card-content h6 {
    color: var(--text-light);
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.4rem;
}

.stat-card-content h3 {
    color: var(--text-dark);
    font-weight: 800;
    font-size: 1.7rem;
    margin: 0;
}

.content-card {
    background: white;
    border-radius: 12px;
    box-shadow: var(--shadow-light);
    overflow: hidden;
    margin-bottom: 1.5rem;
    animation: fadeIn 0.6s ease-out;
}

.content-card-header {
    background: var(--light-bg);
    border-bottom: 1px solid var(--border-color);
    padding: 1.1rem 1.25rem;
}

.content-card-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 1.05rem;
    color: var(--text-dark);
}

.search-wrapper {
    padding: 1.25rem;
    background: var(--light-bg);
    border-bottom: 1px solid var(--border-color);
}

.search-container {
    position: relative;
    max-width: 600px;
    margin: 0 auto;
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #667eea;
    font-size: 1rem;
    z-index: 2;
}

.search-input {
    width: 100%;
    padding: 0.7rem 1rem 0.7rem 2.75rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    background: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.search-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 2px 12px rgba(102, 126, 234, 0.15);
}

.search-results-count {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: var(--primary-blue);
    color: white;
    padding: 0.3rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: none;
}

.search-results-count.show {
    display: block;
    animation: fadeIn 0.3s ease-out;
}

.table-wrapper {
    overflow-x: auto;
    padding: 1rem;
}

.users-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 0.25rem;
    font-size: 0.85rem;
}

.users-table thead th {
    background: var(--light-bg);
    color: var(--text-dark);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.4px;
    padding: 0.6rem 0.7rem;
    border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}

.users-table tbody tr {
    background: white;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.users-table tbody tr:hover {
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
    transform: translateY(-1px);
}

.users-table tbody td {
    padding: 0.75rem 0.85rem;
    vertical-align: middle;
    border: none;
}

.users-table tbody tr td:first-child {
    border-radius: 10px 0 0 10px;
}

.users-table tbody tr td:last-child {
    border-radius: 0 10px 10px 0;
}

.user-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: var(--primary-blue);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    box-shadow: var(--shadow-light);
}

.badge-custom {
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.7rem;
    letter-spacing: 0.2px;
}

.badge-admin {
    background: var(--primary-red) !important;
    color: white;
}

.badge-cashier {
    background: var(--primary-blue) !important;
    color: white;
}

.badge-active {
    background: var(--primary-green) !important;
    color: white;
}

.badge-inactive {
    background: var(--primary-gray) !important;
    color: white;
}

.action-buttons {
    display: flex;
    gap: 0.35rem;
}

.btn-action {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    font-size: 0.8rem;
    cursor: pointer;
}

.btn-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15);
}

.btn-edit {
    background: var(--primary-yellow);
    color: #d68910;
}

.btn-edit:hover {
    background: #f39c12;
    color: white;
}

.btn-password {
    background: var(--primary-blue);
    color: white;
}

.btn-password:hover {
    background: #2980b9;
    color: white;
}

.btn-toggle {
    background: var(--primary-green);
    color: white;
}

.btn-toggle:hover {
    background: #219653;
    color: white;
}

.btn-deactivate {
    background: var(--primary-gray);
    color: white;
}

.btn-deactivate:hover {
    background: #7f8c8d;
    color: white;
}

.modal-content {
    border-radius: 12px;
    border: none;
    overflow: hidden;
}

.modal-header {
    background: var(--light-bg);
    border-bottom: 1px solid var(--border-color);
    padding: 1rem;
}

.modal-title {
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    font-size: 1rem;
}

.modal-title i {
    color: var(--primary-purple);
    margin-right: 0.5rem;
}

.modal-body {
    padding: 1.5rem;
}

.form-label {
    font-weight: 600;
    color: #4a5568;
    margin-bottom: 0.4rem;
    font-size: 0.85rem;
}

.form-control,
.form-select {
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.6rem 0.8rem;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.form-control:focus,
.form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

.btn-modal-action {
    padding: 0.6rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.btn-primary {
    background: var(--primary-blue);
    border: none;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
    color: white;
}

.btn-secondary {
    padding: 0.6rem 1.5rem;
    font-size: 0.9rem;
}

.alert-custom {
    border-radius: 10px;
    border: none;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
    animation: slideDown 0.5s ease-out;
    margin-bottom: 1rem;
}

.no-results-message {
    text-align: center;
    padding: 2rem 1rem;
    color: #718096;
}

.no-results-message i {
    font-size: 3rem;
    color: #cbd5e0;
    margin-bottom: 0.75rem;
}

.no-results-message h5 {
    color: #4a5568;
    margin-bottom: 0.35rem;
    font-size: 0.95rem;
}

.users-table tbody tr.hidden {
    display: none;
}

.d-flex {
    display: flex;
}

.align-items-center {
    align-items: center;
}

.gap-2 {
    gap: 0.5rem;
}

.flex-grow-1 {
    flex-grow: 1;
}

.align-items-start {
    align-items: flex-start;
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .stat-card {
        margin-bottom: 0;
        padding: 0.75rem;
    }

    .stat-card-icon {
        width: 35px;
        height: 35px;
        font-size: 1rem;
    }

    .stat-card-content h3 {
        font-size: 1.25rem;
    }

    .table-wrapper {
        padding: 0.6rem;
    }

    .action-buttons {
        flex-wrap: wrap;
    }
}

@media (max-width: 576px) {
    .users-table thead {
        display: none;
    }

    .users-table tbody tr {
        display: block;
        margin-bottom: 0.5rem;
        border-radius: 8px;
        padding: 0.75rem;
    }

    .users-table tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px solid #f0f0f0;
        font-size: 0.8rem;
    }

    .users-table tbody td:last-child {
        border-bottom: none;
    }

    .users-table tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #667eea;
        margin-right: 0.5rem;
        font-size: 0.7rem;
    }

    .action-buttons {
        justify-content: flex-end;
        width: 100%;
    }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <h1 class="animate-slide-in">
                    <i class="bi bi-person-gear me-2"></i>User Management
                </h1>
                <p class="mb-0 animate-fade-in">
                    Manage system users and their permissions
                </p>
            </div>
            <div class="header-actions">
                <button class="btn btn-add-user" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-plus-circle me-2"></i>Add New User
                </button>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-custom alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i><?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-custom alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="d-flex align-items-start">
                    <div class="stat-card-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-card-content flex-grow-1">
                        <h6>Total Users</h6>
                        <h3><?php echo $total_users; ?></h3>
                    </div>
                </div>
            </div>
            <div class="stat-card success">
                <div class="d-flex align-items-start">
                    <div class="stat-card-icon">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div class="stat-card-content flex-grow-1">
                        <h6>Active Users</h6>
                        <h3><?php echo $active_users; ?></h3>
                    </div>
                </div>
            </div>
            <div class="stat-card info">
                <div class="d-flex align-items-start">
                    <div class="stat-card-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div class="stat-card-content flex-grow-1">
                        <h6>Administrators</h6>
                        <h3><?php echo $admin_count; ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Users Table -->
        <div class="content-card">
            <div class="content-card-header">
                <h5>
                    <i class="bi bi-people me-2"></i>All Users
                </h5>
            </div>
            
            <!-- Search Bar -->
            <div class="search-wrapper">
                <div class="search-container">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" 
                           id="userSearch" 
                           class="search-input" 
                           placeholder="Search by name, username, email, or role...">
                    <span class="search-results-count" id="searchCount"></span>
                </div>
            </div>
            
            <div class="table-wrapper">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Contact</th>
                            <th>Sales</th>
                            <th>Last Login</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($users_result, 0);
                        while ($user = mysqli_fetch_assoc($users_result)): 
                            $initials = strtoupper(substr($user['full_name'], 0, 1));
                        ?>
                            <tr>
                                <td data-label="ID"><?php echo $user['user_id']; ?></td>
                                <td data-label="User">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="user-avatar"><?php echo $initials; ?></div>
                                        <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                    </div>
                                </td>
                                <td data-label="Username"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td data-label="Email"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td data-label="Role">
                                    <span class="badge badge-custom badge-<?php echo $user['role_name'] == 'Admin' ? 'admin' : 'cashier'; ?>">
                                        <?php echo htmlspecialchars($user['role_name']); ?>
                                    </span>
                                </td>
                                <td data-label="Contact"><?php echo htmlspecialchars($user['contact']); ?></td>
                                <td data-label="Sales"><strong><?php echo $user['total_sales']; ?></strong></td>
                                <td data-label="Last Login">
                                    <?php if ($user['last_login']): ?>
                                        <small><?php echo date('M d, Y h:i A', strtotime($user['last_login'])); ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">Never</small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status">
                                    <span class="badge badge-custom badge-<?php echo $user['status'] == 'active' ? 'active' : 'inactive'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <div class="action-buttons">
                                        <button class="btn-action btn-edit" 
                                                onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                                title="Edit User">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn-action btn-password" 
                                                onclick="changePassword(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                                title="Change Password">
                                            <i class="bi bi-key"></i>
                                        </button>
                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <input type="hidden" name="new_status" 
                                                       value="<?php echo $user['status'] == 'active' ? 'inactive' : 'active'; ?>">
                                                <button type="submit" name="toggle_status" 
                                                        class="btn-action btn-<?php echo $user['status'] == 'active' ? 'deactivate' : 'toggle'; ?>"
                                                        title="<?php echo $user['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="bi bi-<?php echo $user['status'] == 'active' ? 'x-circle' : 'check-circle'; ?>"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <!-- No Results Message -->
                <div id="noResults" class="no-results-message" style="display: none;">
                    <i class="bi bi-search"></i>
                    <h5>No users found</h5>
                    <p>Try adjusting your search terms</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Number *</label>
                        <input type="text" name="contact" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select name="role_id" class="form-select" required>
                            <?php 
                            mysqli_data_seek($roles_result, 0);
                            while ($role = mysqli_fetch_assoc($roles_result)): 
                            ?>
                                <option value="<?php echo $role['role_id']; ?>">
                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary btn-modal-action" onclick="submitAddUserForm(event)">
                        <i class="bi bi-save me-2"></i>Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" id="edit_username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Number *</label>
                        <input type="text" name="contact" id="edit_contact" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select name="role_id" id="edit_role_id" class="form-select" required>
                            <?php 
                            mysqli_data_seek($roles_result, 0);
                            while ($role = mysqli_fetch_assoc($roles_result)): 
                            ?>
                                <option value="<?php echo $role['role_id']; ?>">
                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_user" class="btn btn-primary btn-modal-action">
                        <i class="bi bi-save me-2"></i>Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-key me-2"></i>Change Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="password_user_id">
                    <p>Changing password for: <strong id="password_username"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">New Password *</label>
                        <input type="password" name="new_password" id="new_password" class="form-control" 
                               required minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password *</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" 
                               required minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="change_password" class="btn btn-primary btn-modal-action" onclick="submitChangePasswordForm(event)">
                        <i class="bi bi-key me-2"></i>Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('userSearch');
    const searchCount = document.getElementById('searchCount');
    const noResults = document.getElementById('noResults');
    const table = document.querySelector('.users-table tbody');
    const rows = table.querySelectorAll('tr');
    
    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase().trim();
        let visibleCount = 0;
        
        rows.forEach(row => {
            const fullName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const username = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            const email = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
            const role = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
            
            const matchFound = fullName.includes(searchTerm) || 
                             username.includes(searchTerm) || 
                             email.includes(searchTerm) ||
                             role.includes(searchTerm);
            
            if (matchFound) {
                row.classList.remove('hidden');
                visibleCount++;
            } else {
                row.classList.add('hidden');
            }
        });
        
        // Update search count
        if (searchTerm !== '') {
            searchCount.textContent = `${visibleCount} found`;
            searchCount.classList.add('show');
        } else {
            searchCount.classList.remove('show');
        }
        
        // Show/hide no results message
        if (visibleCount === 0 && searchTerm !== '') {
            table.style.display = 'none';
            noResults.style.display = 'block';
        } else {
            table.style.display = '';
            noResults.style.display = 'none';
        }
    });
});

function editUser(user) {
    document.getElementById('edit_user_id').value = user.user_id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_full_name').value = user.full_name;
    document.getElementById('edit_contact').value = user.contact;
    document.getElementById('edit_role_id').value = user.role_id;
    
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

function changePassword(userId, username) {
    document.getElementById('password_user_id').value = userId;
    document.getElementById('password_username').textContent = username;
    
    new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
}

// Generate 6-digit PIN
function generatePIN() {
    return Math.floor(100000 + Math.random() * 900000);
}

// Submit Add User Form with PIN
function submitAddUserForm(event) {
    event.preventDefault();
    
    const form = event.target.closest('form');
    const formData = new FormData(form);
    formData.append('add_user', '1');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.text())
    .then(data => {
        // Check for errors or success
        if (data.includes('successfully')) {
            const pin = generatePIN();
            
            // Close the modal
            bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide();
            
            // Show success alert with PIN verification
            Swal.fire({
                title: 'Success!',
                text: 'User created successfully! Enter your PIN to confirm.',
                icon: 'success',
                input: 'password',
                inputPlaceholder: '●●●●●●',
                inputAttributes: {
                    maxlength: '6',
                    inputmode: 'numeric',
                    autocomplete: 'off',
                    style: 'font-size: 2rem; letter-spacing: 8px; text-align: center; font-weight: bold;'
                },
                confirmButtonColor: '#1a4d5c',
                confirmButtonText: 'Confirm PIN',
                showCancelButton: true,
                cancelButtonText: 'Cancel',
                allowOutsideClick: false,
                inputValidator: (value) => {
                    if (!value) {
                        return 'Please enter your PIN';
                    }
                    if (value.length !== 6) {
                        return 'PIN must be 6 digits';
                    }
                    if (value !== '123456') {
                        return 'Invalid PIN!';
                    }
                    return null;
                },
                didOpen: () => {
                    const input = Swal.getInput();
                    input.addEventListener('input', function() {
                        this.value = this.value.replace(/[^0-9]/g, '');
                    });
                },
                didClose: (result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Reloading...',
                            text: 'Updating user list',
                            icon: 'info',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => {
                                Swal.showLoading();
                                // Reload immediately
                                setTimeout(() => {
                                    location.reload();
                                }, 100);
                            }
                        });
                    }
                }
            });
        } else {
            Swal.fire({
                title: 'Error!',
                text: 'Failed to create user. Username or email may already exist.',
                icon: 'error',
                confirmButtonColor: '#e74c3c'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error!',
            text: 'An error occurred while creating the user.',
            icon: 'error',
            confirmButtonColor: '#e74c3c'
        });
    });
}

// Submit Change Password Form with PIN
function submitChangePasswordForm(event) {
    event.preventDefault();
    
    const form = event.target.closest('form');
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    // Validate passwords match
    if (newPassword !== confirmPassword) {
        Swal.fire({
            title: 'Error!',
            text: 'Passwords do not match.',
            icon: 'error',
            confirmButtonColor: '#e74c3c'
        });
        return;
    }
    
    const formData = new FormData(form);
    formData.append('change_password', '1');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.text())
    .then(data => {
        if (data.includes('successfully')) {
            // Close the modal
            bootstrap.Modal.getInstance(document.getElementById('changePasswordModal')).hide();
            
            // Show success alert with PIN verification
            Swal.fire({
                title: 'Success!',
                text: 'Password changed successfully! Enter your PIN to confirm.',
                icon: 'success',
                input: 'password',
                inputPlaceholder: '●●●●●●',
                inputAttributes: {
                    maxlength: '6',
                    inputmode: 'numeric',
                    autocomplete: 'off',
                    style: 'font-size: 2rem; letter-spacing: 8px; text-align: center; font-weight: bold;'
                },
                confirmButtonColor: '#27ae60',
                confirmButtonText: 'Confirm PIN',
                showCancelButton: true,
                cancelButtonText: 'Cancel',
                allowOutsideClick: false,
                inputValidator: (value) => {
                    if (!value) {
                        return 'Please enter your PIN';
                    }
                    if (value.length !== 6) {
                        return 'PIN must be 6 digits';
                    }
                    if (value !== '123456') {
                        return 'Invalid PIN!';
                    }
                    return null;
                },
                didOpen: () => {
                    const input = Swal.getInput();
                    input.addEventListener('input', function() {
                        this.value = this.value.replace(/[^0-9]/g, '');
                    });
                },
                didClose: (result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Success!',
                            text: 'Password changed successfully!',
                            icon: 'success',
                            confirmButtonColor: '#27ae60'
                        }).then(() => {
                            location.reload();
                        });
                    }
                }
            });
        } else {
            Swal.fire({
                title: 'Error!',
                text: 'Failed to change password.',
                icon: 'error',
                confirmButtonColor: '#e74c3c'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error!',
            text: 'An error occurred while changing the password.',
            icon: 'error',
            confirmButtonColor: '#e74c3c'
        });
    });
}
</script>

<?php include '../includes/footer.php'; ?>x