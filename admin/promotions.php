<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_admin();
prevent_cache();

$page_title = "Promotions Management";

// Handle POST requests with PIN verification
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify session token
    if (!verify_csrf_token($_POST['session_token'] ?? '')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid session. Please try again.']);
        exit();
    }
    
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Check if this is a PIN-verified execution
        $isPinVerified = isset($_POST['pin_verified']) && $_POST['pin_verified'] === 'true';
        
        // Check if PIN is required for this action
        $requiresPin = in_array($action, ['add', 'edit', 'toggle_status', 'delete']);
        
        if ($requiresPin && !$isPinVerified) {
            // Request PIN verification
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode([
                    'requires_pin' => true,
                    'message' => 'Admin PIN verification required'
                ]);
                exit;
            }
        } else {
            // Either PIN is verified OR action doesn't require PIN - process it
            processPromoAction($_POST);
        }
    }
}

function processPromoAction($data) {
    global $conn;
    $action = $data['action'];
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if ($action == 'add') {
        $promo_name = clean_input($data['promo_name']);
        $promo_code = strtoupper(clean_input($data['promo_code']));
        $discount_type = clean_input($data['discount_type']);
        $discount_value = floatval($data['discount_value']);
        $min_purchase = floatval($data['min_purchase']);
        $max_discount = !empty($data['max_discount']) ? floatval($data['max_discount']) : NULL;
        $usage_limit = !empty($data['usage_limit']) ? intval($data['usage_limit']) : NULL;
        $start_date = clean_input($data['start_date']);
        $end_date = clean_input($data['end_date']);
        $description = clean_input($data['description']);
        
        // Check if promo code already exists
        $check_sql = "SELECT promo_id FROM promotions WHERE promo_code = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $promo_code);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            if ($isAjax) {
                echo json_encode(['success' => false, 'message' => 'Promo code already exists!']);
                exit;
            }
            $_SESSION['error'] = "Promo code already exists!";
        } else {
            $sql = "INSERT INTO promotions (promo_name, promo_code, discount_type, discount_value, 
                    min_purchase_amount, max_discount_amount, usage_limit, start_date, end_date, 
                    description, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)";
            
            $stmt = mysqli_prepare($conn, $sql);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sssddiisssi", 
                    $promo_name, $promo_code, $discount_type, $discount_value,
                    $min_purchase, $max_discount, $usage_limit, 
                    $start_date, $end_date, $description, $_SESSION['user_id']
                );
                
                if (mysqli_stmt_execute($stmt)) {
                    if ($isAjax) {
                        echo json_encode(['success' => true, 'message' => 'Promotion added successfully!']);
                        exit;
                    }
                    $_SESSION['success'] = "Promotion added successfully!";
                    log_audit($_SESSION['user_id'], 'INSERT', 'promotions', 
                             mysqli_insert_id($conn), null, $promo_code);
                } else {
                    if ($isAjax) {
                        echo json_encode(['success' => false, 'message' => 'Error adding promotion!']);
                        exit;
                    }
                    $_SESSION['error'] = "Error adding promotion!";
                }
                mysqli_stmt_close($stmt);
            }
        }
        mysqli_stmt_close($check_stmt);
        
    } elseif ($action == 'edit') {
        $promo_id = intval($data['promo_id']);
        $promo_name = clean_input($data['promo_name']);
        $promo_code = strtoupper(clean_input($data['promo_code']));
        $discount_type = clean_input($data['discount_type']);
        $discount_value = floatval($data['discount_value']);
        $min_purchase = floatval($data['min_purchase']);
        $max_discount = !empty($data['max_discount']) ? floatval($data['max_discount']) : NULL;
        $usage_limit = !empty($data['usage_limit']) ? intval($data['usage_limit']) : NULL;
        $start_date = clean_input($data['start_date']);
        $end_date = clean_input($data['end_date']);
        $description = clean_input($data['description']);
        
        $sql = "UPDATE promotions SET promo_name = ?, promo_code = ?, discount_type = ?,
                discount_value = ?, min_purchase_amount = ?, max_discount_amount = ?,
                usage_limit = ?, start_date = ?, end_date = ?, description = ?
                WHERE promo_id = ?";
        
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sssddiisssi", 
                $promo_name, $promo_code, $discount_type, $discount_value,
                $min_purchase, $max_discount, $usage_limit, 
                $start_date, $end_date, $description, $promo_id
            );
            
            if (mysqli_stmt_execute($stmt)) {
                if ($isAjax) {
                    echo json_encode(['success' => true, 'message' => 'Promotion updated successfully!']);
                    exit;
                }
                $_SESSION['success'] = "Promotion updated successfully!";
                log_audit($_SESSION['user_id'], 'UPDATE', 'promotions', $promo_id, null, $promo_code);
            } else {
                if ($isAjax) {
                    echo json_encode(['success' => false, 'message' => 'Error updating promotion!']);
                    exit;
                }
                $_SESSION['error'] = "Error updating promotion!";
            }
            mysqli_stmt_close($stmt);
        }
        
    } elseif ($action == 'toggle_status') {
        $promo_id = intval($data['promo_id']);
        $new_status = clean_input($data['new_status']);
        
        $sql = "UPDATE promotions SET status = ? WHERE promo_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $new_status, $promo_id);
        
        if (mysqli_stmt_execute($stmt)) {
            if ($isAjax) {
                echo json_encode(['success' => true, 'message' => 'Promotion ' . ($new_status == 'active' ? 'activated' : 'deactivated') . ' successfully!']);
                exit;
            }
            $_SESSION['success'] = "Promotion " . ($new_status == 'active' ? 'activated' : 'deactivated') . " successfully!";
            log_audit($_SESSION['user_id'], 'UPDATE', 'promotions', $promo_id, null, 'Status changed to ' . $new_status);
        } else {
            if ($isAjax) {
                echo json_encode(['success' => false, 'message' => 'Error updating promotion status!']);
                exit;
            }
            $_SESSION['error'] = "Error updating promotion status!";
        }
        mysqli_stmt_close($stmt);
        
    } elseif ($action == 'delete') {
        $promo_id = intval($data['promo_id']);
        
        // Check if promo has been used
        $check_sql = "SELECT COUNT(*) as count FROM sale_promotions WHERE promo_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $promo_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));
        
        if ($check_result['count'] > 0) {
            if ($isAjax) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete promotion that has been used!']);
                exit;
            }
            $_SESSION['error'] = "Cannot delete promotion that has been used!";
        } else {
            $sql = "DELETE FROM promotions WHERE promo_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $promo_id);
            
            if (mysqli_stmt_execute($stmt)) {
                if ($isAjax) {
                    echo json_encode(['success' => true, 'message' => 'Promotion deleted successfully!']);
                    exit;
                }
                $_SESSION['success'] = "Promotion deleted successfully!";
                log_audit($_SESSION['user_id'], 'DELETE', 'promotions', $promo_id, null, null);
            } else {
                if ($isAjax) {
                    echo json_encode(['success' => false, 'message' => 'Error deleting promotion!']);
                    exit;
                }
                $_SESSION['error'] = "Error deleting promotion!";
            }
            mysqli_stmt_close($stmt);
        }
        mysqli_stmt_close($check_stmt);
    }
}

// Fetch all promotions
$promotions_sql = "SELECT p.*, u.full_name as created_by_name,
                   (SELECT COUNT(*) FROM sale_promotions WHERE promo_id = p.promo_id) as times_used,
                   (SELECT IFNULL(SUM(discount_amount), 0) FROM sale_promotions WHERE promo_id = p.promo_id) as total_discount
                   FROM promotions p
                   LEFT JOIN users u ON p.created_by = u.user_id
                   ORDER BY p.created_at DESC";
$promotions_result = mysqli_query($conn, $promotions_sql);

// Fetch inactive/expired promotions
$inactive_sql = "SELECT p.*, u.full_name as created_by_name FROM promotions p
                 LEFT JOIN users u ON p.created_by = u.user_id
                 WHERE p.status = 'inactive' OR p.end_date < NOW()
                 ORDER BY p.updated_at DESC LIMIT 20";
$inactive_result = mysqli_query($conn, $inactive_sql);

// Fetch deleted promotions from audit logs
$deleted_sql = "SELECT al.*, u.full_name as action_by FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.user_id
                WHERE al.action = 'DELETE' AND al.table_name = 'promotions'
                ORDER BY al.created_at DESC LIMIT 20";
$deleted_result = mysqli_query($conn, $deleted_sql);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.min.css" rel="stylesheet">

<style>
/* Promotions Page - Dark Teal Dashboard Design */
:root {
    --primary-teal: #1a4d5c;
    --secondary-teal: #0f3543;
    --dark-teal: #082a33;
    --accent-cyan: #22d3ee;
    --accent-green: #4ade80;
    --accent-blue: #3b82f6;
    --accent-orange: #f59e0b;
    --accent-red: #ef4444;
    --accent-purple: #9b59b6;
    --light-bg: #f8fafc;
    --card-bg: #ffffff;
    --text-dark: #1e293b;
    --text-light: #64748b;
    --text-muted: #94a3b8;
    --border-color: #e2e8f0;
    --shadow-light: 0 2px 12px rgba(26, 77, 92, 0.08);
    --shadow-medium: 0 6px 25px rgba(26, 77, 92, 0.12);
    --shadow-heavy: 0 12px 40px rgba(26, 77, 92, 0.18);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background-color: var(--light-bg);
    color: var(--text-dark);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Welcome Header */
.welcome-header {
    background: linear-gradient(135deg, var(--primary-teal) 0%, var(--secondary-teal) 100%);
    border-radius: 12px;
    padding: 2rem 1.5rem 1.5rem 1.5rem;
    color: white;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-medium);
    border: none;
    border-left: 5px solid var(--accent-cyan);
    backdrop-filter: blur(10px);
}

.welcome-header h1 {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    color: #ffffff;
    letter-spacing: -0.5px;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
}

.welcome-header h1 i {
    color: var(--accent-cyan);
}

.welcome-header p {
    color: rgba(255, 255, 255, 0.95);
    margin-bottom: 0;
    font-size: 1.05rem;
    letter-spacing: 0.3px;
}

/* Promo Cards */
.promo-card {
    background: var(--card-bg);
    border: none;
    border-radius: 14px;
    box-shadow: var(--shadow-light);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    height: 100%;
    border-top: 4px solid var(--accent-purple);
    display: flex;
    flex-direction: column;
}

.promo-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-heavy);
}

.promo-header {
    background: linear-gradient(135deg, var(--primary-teal) 0%, var(--secondary-teal) 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 0;
    flex-shrink: 0;
}

.promo-code {
    font-size: 1.4rem;
    font-weight: 700;
    letter-spacing: 2px;
    font-family: 'Courier New', monospace;
    color: var(--accent-cyan);
}

.date-range {
    background: rgba(34, 211, 238, 0.2);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    display: inline-block;
    margin-top: 0.5rem;
    font-size: 0.9rem;
    color: #ffffff;
}

.discount-badge {
    font-size: 2rem;
    font-weight: 700;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--accent-red), #dc2626);
    color: white;
    display: inline-block;
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.25);
}

.stat-box {
    background: var(--light-bg);
    padding: 0.85rem;
    border-radius: 10px;
    text-align: center;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.stat-box:hover {
    border-color: var(--accent-cyan);
    box-shadow: 0 0 0 2px rgba(34, 211, 238, 0.1);
}

.stat-box .fw-bold {
    color: var(--text-dark);
    font-size: 1.1rem;
    margin-bottom: 0.3rem;
}

.stat-box .text-muted {
    font-size: 0.8rem;
    margin-bottom: 0.25rem;
    color: var(--text-light);
}

.promo-card .card-body {
    flex: 1;
    padding: 1rem;
    display: flex;
    flex-direction: column;
}

.promo-card .card-body > *:not(.d-grid) {
    flex-shrink: 0;
}

.promo-card .d-grid {
    margin-top: auto;
    flex-shrink: 0;
}

.promo-card .card-footer {
    flex-shrink: 0;
}

/* Alert Messages */
.alert-custom {
    border-radius: 12px;
    border: none;
    box-shadow: var(--shadow-light);
    border-left: 5px solid;
    padding: 0.85rem 1.25rem;
}

.alert-success {
    background: rgba(74, 222, 128, 0.15);
    border-left-color: var(--accent-green);
    color: var(--text-dark);
}

.alert-danger {
    background: rgba(239, 68, 68, 0.15);
    border-left-color: var(--accent-red);
    color: var(--text-dark);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 2rem 1rem;
}

.empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    background: rgba(26, 77, 92, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: var(--primary-teal);
}

/* Responsive */
@media (max-width: 991px) {
    .welcome-header {
        padding: 1rem;
    }

    .welcome-header h1 {
        font-size: 1.3rem;
    }

    .promo-code {
        font-size: 1.2rem;
    }

    .discount-badge {
        font-size: 1.5rem;
        padding: 0.7rem 1rem;
    }
}

@media (max-width: 576px) {
    .welcome-header h1 {
        font-size: 1.2rem;
    }

    .welcome-header p {
        font-size: 0.9rem;
    }

    .promo-code {
        font-size: 1.1rem;
        letter-spacing: 1.5px;
    }

    .discount-badge {
        font-size: 1.3rem;
        padding: 0.6rem 0.9rem;
    }

    .stat-box {
        padding: 0.6rem;
    }

    .stat-box .fw-bold {
        font-size: 0.9rem;
    }

    .stat-box .text-muted {
        font-size: 0.8rem;
    }
}

/* PIN Modal Styles */
.pin-input-container {
    position: relative;
    margin: 1.5rem 0;
    height: 50px;
}

.pin-input {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 1;
    font-size: 18px;
    font-weight: bold;
    text-align: center;
    letter-spacing: 12px;
    color: transparent;
    background: transparent;
    border: 2px solid var(--accent-cyan);
    border-radius: 10px;
    padding: 0 10px;
    cursor: text;
    z-index: 100;
    transition: all 0.2s ease;
    -webkit-appearance: none;
    appearance: none;
}

.pin-input:focus {
    outline: none;
    border-color: var(--accent-cyan);
    box-shadow: 0 0 0 4px rgba(34, 211, 238, 0.2);
}

.pin-input:disabled {
    opacity: 0.5;
    border-color: #bdc3c7;
    cursor: not-allowed;
}

.pin-input::placeholder {
    color: transparent;
}

.pin-display {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    pointer-events: none;
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    width: 100%;
    height: 50px;
    z-index: 50;
}

.pin-dot {
    width: 45px;
    height: 45px;
    border: 2px solid var(--border-color);
    border-radius: 50%;
    transition: all 0.3s ease;
    background: var(--light-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
    position: relative;
}

.pin-dot::before {
    content: '';
    display: none;
}

.pin-dot.filled {
    background: var(--accent-cyan);
    border-color: var(--accent-cyan);
    transform: scale(1.15);
    box-shadow: 0 4px 15px rgba(34, 211, 238, 0.3);
}

.pin-dot.filled::before {
    content: '●';
    display: block;
    color: white;
    font-size: 20px;
    line-height: 1;
}

@media (max-width: 576px) {
    .pin-display {
        gap: 8px;
    }

    .pin-dot {
        width: 40px;
        height: 40px;
        font-size: 20px;
        line-height: 40px;
    }
}

/* History Tables */
.section-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.section-title i {
    color: var(--primary-teal);
    font-size: 1.4rem;
}

.history-section {
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 2px solid var(--border-color);
}

.history-table {
    background: var(--card-bg);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--shadow-light);
    margin-bottom: 2rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.history-table table {
    margin: 0;
    width: 100%;
    font-size: 0.9rem;
}

.history-table thead {
    background: linear-gradient(135deg, var(--primary-teal) 0%, var(--secondary-teal) 100%);
    color: white;
}

.history-table thead th {
    padding: 0.85rem;
    font-weight: 600;
    border: none;
    text-align: left;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.75rem;
}

.history-table tbody tr {
    border-bottom: 1px solid var(--border-color);
    transition: background-color 0.2s ease;
}

.history-table tbody tr:hover {
    background-color: rgba(26, 77, 92, 0.05);
}

.history-table tbody td {
    padding: 0.85rem;
    color: var(--text-dark);
    vertical-align: middle;
    font-size: 0.9rem;
}

.badge-small {
    display: inline-block;
    padding: 0.35rem 0.6rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-inactive {
    background: rgba(189, 195, 199, 0.2);
    color: #7f8c8d;
}

.badge-expired {
    background: rgba(231, 76, 60, 0.2);
    color: var(--primary-red);
}

.badge-deleted {
    background: rgba(46, 55, 64, 0.2);
    color: #2c3e50;
}

.code-cell {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    color: var(--primary-blue);
}

.date-cell {
    font-size: 0.85rem;
    color: var(--text-light);
    white-space: nowrap;
}

.no-data {
    text-align: center;
    padding: 2rem;
    color: var(--text-light);
}

.no-data i {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: var(--border-color);
}

@media (max-width: 768px) {
    .history-table table {
        font-size: 0.85rem;
    }

    .history-table thead th,
    .history-table tbody td {
        padding: 0.6rem;
    }

    .section-title {
        font-size: 1.1rem;
    }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                <div>
                    <h1>
                        <i class="bi bi-megaphone-fill me-2"></i>Promotions Management
                    </h1>
                    <p class="mb-0">
                        Manage discounts and promotional campaigns
                    </p>
                </div>
                <div class="mt-2 mt-md-0">
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPromoModal">
                        <i class="bi bi-plus-circle me-2"></i>Add New Promo
                    </button>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-custom alert-dismissible fade show mb-3">
                <div class="d-flex align-items-center">
                    <i class="bi bi-check-circle me-2"></i>
                    <div class="flex-grow-1"><?php echo $_SESSION['success']; ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-custom alert-dismissible fade show mb-3">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <div class="flex-grow-1"><?php echo $_SESSION['error']; ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Promotions Grid -->
        <div class="row g-3">
            <?php if (mysqli_num_rows($promotions_result) > 0): ?>
                <?php while ($promo = mysqli_fetch_assoc($promotions_result)): ?>
                    <div class="col-xl-4 col-lg-6 col-md-6">
                        <div class="promo-card">
                            <div class="promo-header">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h5 class="mb-1" style="font-size: 1rem; font-weight: 600;"><?php echo htmlspecialchars($promo['promo_name']); ?></h5>
                                        <div class="promo-code"><?php echo htmlspecialchars($promo['promo_code']); ?></div>
                                    </div>
                                    <div>
                                        <?php if ($promo['status'] == 'active'): ?>
                                            <span class="badge bg-success" style="font-size: 0.75rem;">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary" style="font-size: 0.75rem;">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="date-range">
                                    <i class="bi bi-calendar-range"></i>
                                    <?php echo date('M d', strtotime($promo['start_date'])); ?> - 
                                    <?php echo date('M d', strtotime($promo['end_date'])); ?>
                                </div>
                            </div>
                            
                            <div class="card-body" style="padding: 1rem;">
                                <div class="text-center mb-3">
                                    <div class="discount-badge">
                                        <?php if ($promo['discount_type'] == 'percentage'): ?>
                                            <?php echo number_format($promo['discount_value'], 0); ?>%
                                        <?php else: ?>
                                            ₱<?php echo number_format($promo['discount_value'], 0); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($promo['description']): ?>
                                    <p class="text-muted small mb-3" style="font-size: 0.85rem;">
                                        <i class="bi bi-info-circle me-1"></i>
                                        <?php echo htmlspecialchars($promo['description']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="stat-box">
                                            <div class="text-muted">Min Purchase</div>
                                            <div class="fw-bold">₱<?php echo number_format($promo['min_purchase_amount'], 0); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="stat-box">
                                            <div class="text-muted">Max Discount</div>
                                            <div class="fw-bold">
                                                <?php if ($promo['max_discount_amount']): ?>
                                                    ₱<?php echo number_format($promo['max_discount_amount'], 0); ?>
                                                <?php else: ?>
                                                    <span style="color: var(--text-light);">No Limit</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="stat-box">
                                            <div class="text-muted">Times Used</div>
                                            <div class="fw-bold" style="color: var(--primary-blue);"><?php echo number_format($promo['times_used']); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="stat-box">
                                            <div class="text-muted">Total Savings</div>
                                            <div class="fw-bold" style="color: var(--primary-green);">₱<?php echo number_format($promo['total_discount'], 0); ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-primary btn-sm" 
                                            onclick="editPromo(<?php echo htmlspecialchars(json_encode($promo)); ?>)" style="font-size: 0.9rem;">
                                        <i class="bi bi-pencil me-1"></i> Edit
                                    </button>
                                    
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-outline-warning btn-sm flex-fill" 
                                                onclick="requestPin('toggle', <?php echo $promo['promo_id']; ?>, '<?php echo $promo['status'] == 'active' ? 'inactive' : 'active'; ?>')"
                                                style="font-size: 0.9rem;">
                                            <i class="bi bi-toggle-<?php echo $promo['status'] == 'active' ? 'on' : 'off'; ?>"></i>
                                            <?php echo $promo['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                        
                                        <button class="btn btn-outline-danger btn-sm" 
                                                onclick="requestPin('delete', <?php echo $promo['promo_id']; ?>)"
                                                style="font-size: 0.9rem;">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-footer" style="background: var(--light-bg); border-top: 1px solid var(--border-color); padding: 0.75rem 1rem;">
                                <small class="text-muted" style="font-size: 0.85rem;">
                                    <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($promo['created_by_name']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-megaphone"></i>
                        </div>
                        <h3 class="mt-3">No Promotions Yet</h3>
                        <p class="text-muted">Create your first promotion to start offering discounts!</p>
                        <button class="btn btn-primary btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#addPromoModal">
                            <i class="bi bi-plus-circle me-2"></i>Add First Promo
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Deactivated/Expired Promotions -->
        <?php if (mysqli_num_rows($inactive_result) > 0): ?>
        <div class="history-section">
            <h3 class="section-title">
                <i class="bi bi-clock-history"></i>Inactive & Expired Promotions
            </h3>
            <div class="history-table">
                <table>
                    <thead>
                        <tr>
                            <th>Promo Code</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>End Date</th>
                            <th>Created By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($promo = mysqli_fetch_assoc($inactive_result)): ?>
                        <tr>
                            <td><span class="code-cell"><?php echo htmlspecialchars($promo['promo_code']); ?></span></td>
                            <td><?php echo htmlspecialchars($promo['promo_name']); ?></td>
                            <td>
                                <?php 
                                $isExpired = strtotime($promo['end_date']) < time();
                                if ($isExpired) {
                                    echo '<span class="badge-small badge-expired">Expired</span>';
                                } else {
                                    echo '<span class="badge-small badge-inactive">Inactive</span>';
                                }
                                ?>
                            </td>
                            <td><span class="date-cell"><?php echo date('M d, Y', strtotime($promo['end_date'])); ?></span></td>
                            <td><?php echo htmlspecialchars($promo['created_by_name'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Deleted Promotions History -->
        <?php if (mysqli_num_rows($deleted_result) > 0): ?>
        <div class="history-section">
            <h3 class="section-title">
                <i class="bi bi-trash-fill"></i>Deleted Promotions History
            </h3>
            <div class="history-table">
                <table>
                    <thead>
                        <tr>
                            <th>Promo Code</th>
                            <th>Deleted By</th>
                            <th>Deleted On</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($deleted = mysqli_fetch_assoc($deleted_result)): ?>
                        <tr>
                            <td><span class="code-cell"><?php echo htmlspecialchars($deleted['old_value'] ?? 'N/A'); ?></span></td>
                            <td><?php echo htmlspecialchars($deleted['action_by'] ?? 'System'); ?></td>
                            <td><span class="date-cell"><?php echo date('M d, Y H:i', strtotime($deleted['created_at'])); ?></span></td>
                            <td><span class="badge-small badge-deleted">ID: <?php echo htmlspecialchars($deleted['record_id']); ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Promo Modal -->
<div class="modal fade" id="addPromoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Promotion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addPromoForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Promo Name <span class="text-danger">*</span></label>
                            <input type="text" name="promo_name" class="form-control form-control-sm" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Promo Code <span class="text-danger">*</span></label>
                            <input type="text" name="promo_code" class="form-control form-control-sm" required 
                                   style="text-transform: uppercase;" placeholder="e.g., SAVE50">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Discount Type <span class="text-danger">*</span></label>
                            <select name="discount_type" class="form-select form-select-sm" required onchange="updateDiscountLabel(this.value, 'add')">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₱)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Discount Value <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text" id="discountLabelAdd">%</span>
                                <input type="number" name="discount_value" class="form-control" required 
                                       step="0.01" min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Minimum Purchase</label>
                            <input type="number" name="min_purchase" class="form-control form-control-sm" value="0" 
                                   step="0.01" min="0">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Maximum Discount</label>
                            <input type="number" name="max_discount" class="form-control form-control-sm" 
                                   step="0.01" min="0" placeholder="Leave empty for no limit">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control form-control-sm" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control form-control-sm" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Usage Limit</label>
                            <input type="number" name="usage_limit" class="form-control form-control-sm" 
                                   placeholder="Leave empty for unlimited" min="1">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control form-control-sm" rows="2" 
                                      placeholder="Describe the promotion..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="requestPin('add')">
                        <i class="bi bi-check-circle me-2"></i>Add Promotion
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Promo Modal -->
<div class="modal fade" id="editPromoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Promotion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editPromoForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="promo_id" id="edit_promo_id">
                    
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Promo Name <span class="text-danger">*</span></label>
                            <input type="text" name="promo_name" id="edit_promo_name" class="form-control form-control-sm" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Promo Code <span class="text-danger">*</span></label>
                            <input type="text" name="promo_code" id="edit_promo_code" class="form-control form-control-sm" required 
                                   style="text-transform: uppercase;">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Discount Type <span class="text-danger">*</span></label>
                            <select name="discount_type" id="edit_discount_type" class="form-select form-select-sm" required 
                                    onchange="updateDiscountLabel(this.value, 'edit')">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₱)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Discount Value <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text" id="discountLabelEdit">%</span>
                                <input type="number" name="discount_value" id="edit_discount_value" 
                                       class="form-control" required step="0.01" min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Minimum Purchase</label>
                            <input type="number" name="min_purchase" id="edit_min_purchase" 
                                   class="form-control form-control-sm" step="0.01" min="0">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Maximum Discount</label>
                            <input type="number" name="max_discount" id="edit_max_discount" 
                                   class="form-control form-control-sm" step="0.01" min="0">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="edit_start_date" class="form-control form-control-sm" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" id="edit_end_date" class="form-control form-control-sm" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Usage Limit</label>
                            <input type="number" name="usage_limit" id="edit_usage_limit" class="form-control form-control-sm" min="1">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="requestPin('edit')">
                        <i class="bi bi-check-circle me-2"></i>Update Promotion
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- PIN Verification Modal -->
<div class="modal fade" id="pinModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-shield-lock me-2"></i>Admin PIN Verification
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3" style="font-size: 0.95rem;">Enter your 6-digit PIN to continue:</p>
                <div class="pin-input-container">
                    <input type="text" id="pinInput" maxlength="6" pattern="\d*" 
                           class="pin-input" autocomplete="off">
                    <div class="pin-display" id="pinDisplay">
                        <?php for($i = 0; $i < 6; $i++): ?>
                        <div class="pin-dot"></div>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="alert alert-danger mt-3" id="pinError" style="display: none; padding: 0.6rem 0.8rem;">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <span id="pinErrorMessage" style="font-size: 0.9rem;"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary btn-sm" id="confirmPinBtn" onclick="verifyPin()" disabled>
                    <i class="bi bi-check-circle me-2"></i>Verify PIN
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.all.min.js"></script>

<script>
// Global variables
let pendingAction = null;
let pendingPromoData = null;
let currentPromoId = null;
let currentStatus = null;
let pinAttempts = 0;
let pinLocked = false;
let pinLockoutTimer = null;
const MAX_PIN_ATTEMPTS = 3;
const PIN_LOCKOUT_SECONDS = 30;

// Update discount label
function updateDiscountLabel(type, mode) {
    const label = document.getElementById('discountLabel' + mode.charAt(0).toUpperCase() + mode.slice(1));
    label.textContent = type === 'percentage' ? '%' : '₱';
}

// Edit promo
function editPromo(promo) {
    document.getElementById('edit_promo_id').value = promo.promo_id;
    document.getElementById('edit_promo_name').value = promo.promo_name;
    document.getElementById('edit_promo_code').value = promo.promo_code;
    document.getElementById('edit_discount_type').value = promo.discount_type;
    document.getElementById('edit_discount_value').value = promo.discount_value;
    document.getElementById('edit_min_purchase').value = promo.min_purchase_amount;
    document.getElementById('edit_max_discount').value = promo.max_discount_amount || '';
    document.getElementById('edit_start_date').value = promo.start_date;
    document.getElementById('edit_end_date').value = promo.end_date;
    document.getElementById('edit_usage_limit').value = promo.usage_limit || '';
    document.getElementById('edit_description').value = promo.description || '';
    
    updateDiscountLabel(promo.discount_type, 'edit');
    
    const modal = new bootstrap.Modal(document.getElementById('editPromoModal'));
    modal.show();
}

// Request PIN
function requestPin(action, promoId = null, newStatus = null) {
    pendingAction = action;
    currentPromoId = promoId;
    currentStatus = newStatus;
    
    // Store form data if add or edit
    if (action === 'add') {
        pendingPromoData = new FormData(document.getElementById('addPromoForm'));
    } else if (action === 'edit') {
        pendingPromoData = new FormData(document.getElementById('editPromoForm'));
    }
    
    // Reset PIN input
    pinAttempts = 0;
    pinLocked = false;
    document.getElementById('pinInput').value = '';
    document.getElementById('pinInput').disabled = false;
    document.getElementById('pinInput').style.opacity = '1';
    updatePinDisplay();
    document.getElementById('pinError').style.display = 'none';
    document.getElementById('confirmPinBtn').disabled = false;
    
    // Show modal
    const pinModal = new bootstrap.Modal(document.getElementById('pinModal'));
    pinModal.show();
    
    // Focus PIN input
    setTimeout(() => {
        document.getElementById('pinInput').focus();
    }, 100);
}

// Update PIN display
function updatePinDisplay() {
    const pin = document.getElementById('pinInput').value;
    const dots = document.querySelectorAll('.pin-dot');
    
    dots.forEach((dot, index) => {
        if (index < pin.length) {
            dot.classList.add('filled');
        } else {
            dot.classList.remove('filled');
        }
    });
    
    // Enable verify button when PIN is 6 digits
    document.getElementById('confirmPinBtn').disabled = pin.length !== 6;
}

// Verify PIN
async function verifyPin() {
    if (pinLocked) {
        Swal.fire({
            icon: 'warning',
            title: 'PIN Locked',
            text: 'Please wait before trying again.',
            timer: 1500,
            showConfirmButton: false
        });
        return;
    }
    
    const pin = document.getElementById('pinInput').value;
    
    if (pin.length !== 6) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid PIN',
            text: 'PIN must be 6 digits.',
            timer: 1500,
            showConfirmButton: false
        });
        return;
    }
    
    // Disable button during verification
    document.getElementById('confirmPinBtn').disabled = true;
    
    try {
        const response = await fetch('../api/verify-pin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ pin: pin })
        });
        
        const text = await response.text();
        console.log('API Response:', text);
        
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('JSON Parse Error:', e, 'Response:', text);
            throw new Error('Invalid API response');
        }
        
        if (result.success) {
            // PIN verified - execute pending action
            pinAttempts = 0;
            document.getElementById('pinError').style.display = 'none';
            
            Swal.fire({
                icon: 'success',
                title: 'PIN Verified',
                text: 'Processing your request...',
                timer: 1000,
                showConfirmButton: false
            });
            
            // Close PIN modal and execute action
            const pinModal = bootstrap.Modal.getInstance(document.getElementById('pinModal'));
            if (pinModal) pinModal.hide();
            
            setTimeout(() => {
                executePendingAction();
            }, 1100);
        } else {
            pinAttempts++;
            console.log('PIN Attempt:', pinAttempts, 'Message:', result.message);
            
            if (pinAttempts >= MAX_PIN_ATTEMPTS) {
                startPinLockout();
            } else {
                const remaining = MAX_PIN_ATTEMPTS - pinAttempts;
                document.getElementById('pinErrorMessage').textContent = 
                    `Invalid PIN. ${remaining} attempt${remaining !== 1 ? 's' : ''} remaining.`;
                document.getElementById('pinError').style.display = 'block';
                
                // Clear input
                document.getElementById('pinInput').value = '';
                updatePinDisplay();
                document.getElementById('pinInput').focus();
                document.getElementById('confirmPinBtn').disabled = false;
            }
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to verify PIN: ' + error.message,
            timer: 2000,
            showConfirmButton: false
        });
        document.getElementById('confirmPinBtn').disabled = false;
    }
}

// Start PIN lockout
function startPinLockout() {
    pinLocked = true;
    let remainingSeconds = PIN_LOCKOUT_SECONDS;
    
    // Disable input
    document.getElementById('pinInput').disabled = true;
    document.getElementById('pinInput').style.opacity = '0.5';
    document.getElementById('confirmPinBtn').disabled = true;
    
    // Show lockout message
    document.getElementById('pinErrorMessage').textContent = 
        `Too many failed attempts. Try again in ${remainingSeconds}s`;
    document.getElementById('pinError').style.display = 'block';
    
    // Update countdown
    pinLockoutTimer = setInterval(() => {
        remainingSeconds--;
        document.getElementById('pinErrorMessage').textContent = 
            `Too many failed attempts. Try again in ${remainingSeconds}s`;
        
        if (remainingSeconds <= 0) {
            clearInterval(pinLockoutTimer);
            pinLocked = false;
            pinAttempts = 0;
            
            // Re-enable input
            document.getElementById('pinInput').disabled = false;
            document.getElementById('pinInput').style.opacity = '1';
            document.getElementById('confirmPinBtn').disabled = false;
            document.getElementById('pinInput').value = '';
            updatePinDisplay();
            
            document.getElementById('pinErrorMessage').textContent = 
                'Lockout period expired. Please try again.';
            document.getElementById('pinError').className = 'alert alert-success mt-3';
            document.getElementById('pinError').style.display = 'block';
            
            setTimeout(() => {
                document.getElementById('pinError').style.display = 'none';
                document.getElementById('pinError').className = 'alert alert-danger mt-3';
            }, 2000);
            
            document.getElementById('pinInput').focus();
        }
    }, 1000);
}

// Execute pending action
async function executePendingAction() {
    try {
        if (pendingAction === 'add') {
            // Add pin_verified flag before sending
            pendingPromoData.append('pin_verified', 'true');
            
            const response = await fetch('promotions.php', {
                method: 'POST',
                body: pendingPromoData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            const result = await response.json();
            
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Promotion added successfully!',
                    timer: 1500,
                    showConfirmButton: false
                });
                
                setTimeout(() => {
                    window.location.reload();
                }, 1600);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.message || 'Failed to add promotion'
                });
            }
        } else if (pendingAction === 'edit') {
            // Add pin_verified flag before sending
            pendingPromoData.append('pin_verified', 'true');
            
            const response = await fetch('promotions.php', {
                method: 'POST',
                body: pendingPromoData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            const result = await response.json();
            
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Promotion updated successfully!',
                    timer: 1500,
                    showConfirmButton: false
                });
                
                setTimeout(() => {
                    window.location.reload();
                }, 1600);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.message || 'Failed to update promotion'
                });
            }
        } else if (pendingAction === 'toggle') {
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('promo_id', currentPromoId);
            formData.append('new_status', currentStatus);
            formData.append('pin_verified', 'true');
            
            const response = await fetch('promotions.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            const result = await response.json();
            
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: `Promotion ${currentStatus === 'active' ? 'activated' : 'deactivated'} successfully!`,
                    timer: 1500,
                    showConfirmButton: false
                });
                
                setTimeout(() => {
                    window.location.reload();
                }, 1600);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.message || 'Failed to update promotion status'
                });
            }
        } else if (pendingAction === 'delete') {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('promo_id', currentPromoId);
            formData.append('pin_verified', 'true');
            
            const response = await fetch('promotions.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            const result = await response.json();
            
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Promotion deleted successfully!',
                    timer: 1500,
                    showConfirmButton: false
                });
                
                setTimeout(() => {
                    window.location.reload();
                }, 1600);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.message || 'Failed to delete promotion'
                });
            }
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An unexpected error occurred'
        });
    }
}

// PIN input event listeners
document.getElementById('pinInput').addEventListener('input', function(e) {
    // Allow only numbers
    this.value = this.value.replace(/\D/g, '');
    
    // Limit to 6 digits
    if (this.value.length > 6) {
        this.value = this.value.slice(0, 6);
    }
    
    updatePinDisplay();
    
    // Hide error when user starts typing
    if (this.value.length > 0) {
        document.getElementById('pinError').style.display = 'none';
    }
});

// Enter key submits PIN
document.getElementById('pinInput').addEventListener('keyup', function(e) {
    if (e.key === 'Enter' && this.value.length === 6) {
        verifyPin();
    }
});

// Auto-focus PIN input when modal opens
document.getElementById('pinModal').addEventListener('shown.bs.modal', function() {
    document.getElementById('pinInput').focus();
});

// Set minimum date to today for date inputs
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.querySelectorAll('input[type="date"]').forEach(input => {
        input.setAttribute('min', today);
    });
});
</script>

<?php
include '../includes/footer.php';
?>
