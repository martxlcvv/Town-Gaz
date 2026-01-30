<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_admin();
prevent_cache();

$page_title = "Inventory Management";

// Handle inventory update with PIN verification
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify session token
    if (!verify_csrf_token($_POST['session_token'] ?? '')) {
        $_SESSION['error_message'] = "Invalid session. Please try again.";
        header('Location: inventory.php');
        exit();
    }
    
    // Check which action is being performed
    if (isset($_POST['update_inventory'])) {
        // Check if PIN is required
        if ($_SESSION['role_name'] == 'Admin') {
            // Store form data temporarily
            $_SESSION['pending_inventory_data'] = $_POST;
            
            // Return JSON for AJAX
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode([
                    'requires_pin' => true,
                    'message' => 'Admin PIN verification required for inventory update'
                ]);
                exit;
            }
        } else {
            // Staff can update without PIN
            processInventoryUpdate($_POST);
            $_SESSION['success_message'] = "Inventory updated successfully!";
            header('Location: inventory.php');
            exit();
        }
    }
    
    // Handle PIN verified update
    if (isset($_POST['update_with_pin'])) {
        $pin = $_POST['pin'] ?? '';
        
        // Verify PIN
        if (verifyAdminPin($pin)) {
            if (isset($_SESSION['pending_inventory_data'])) {
                processInventoryUpdate($_SESSION['pending_inventory_data']);
                unset($_SESSION['pending_inventory_data']);
                $_SESSION['success_message'] = "Inventory updated successfully!";
                
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    echo json_encode(['success' => true, 'message' => 'Inventory updated successfully!']);
                    exit;
                } else {
                    header('Location: inventory.php');
                    exit();
                }
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid PIN']);
            exit;
        }
    }
}

function verifyAdminPin($pin) {
    global $conn;
    
    // Check if PIN is correct (in real app, this should be hashed)
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT pin_hash FROM admin_pins WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    
    if ($data && password_verify($pin, $data['pin_hash'])) {
        $_SESSION['pin_verified'] = true;
        $_SESSION['pin_verified_time'] = time();
        return true;
    }
    
    return false;
}

function processInventoryUpdate($data) {
    global $conn;
    $date = clean_input($data['date']);
    $user_id = $_SESSION['user_id'];
    
    foreach ($data['opening_stock'] as $product_id => $opening_stock) {
        $product_id = intval($product_id);
        $opening_stock = intval(clean_input($opening_stock));
        $closing_stock = isset($data['closing_stock'][$product_id]) ? 
                        intval(clean_input($data['closing_stock'][$product_id])) : 0;
        
        // Check if record exists
        $check_sql = "SELECT * FROM inventory WHERE product_id = ? AND date = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "is", $product_id, $date);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            // Update existing record
            $update_sql = "UPDATE inventory 
                          SET opening_stock = ?, 
                              closing_stock = ?,
                              current_stock = ?,
                              updated_by = ?
                          WHERE product_id = ? AND date = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "iiiiss", 
                $opening_stock, $closing_stock, $opening_stock, $user_id, $product_id, $date);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);
        } else {
            // Insert new record
            $insert_sql = "INSERT INTO inventory (product_id, opening_stock, closing_stock, current_stock, date, updated_by) 
                          VALUES (?, ?, ?, ?, ?, ?)";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "iiiisi", 
                $product_id, $opening_stock, $closing_stock, $opening_stock, $date, $user_id);
            mysqli_stmt_execute($insert_stmt);
            mysqli_stmt_close($insert_stmt);
        }
        mysqli_stmt_close($check_stmt);
    }
    
    // Log audit
    log_audit($user_id, 'UPDATE', 'inventory', 0, null, [
        'action' => 'manual_stock_update',
        'date' => $date,
        'updated_by' => $_SESSION['full_name']
    ]);
}

// Get current date inventory
$today = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Ensure all active products have inventory records for today
$ensure_inv_sql = "INSERT IGNORE INTO inventory (product_id, date, opening_stock, current_stock, closing_stock)
                   SELECT product_id, '$today', 0, 0, 0 
                   FROM products 
                   WHERE status = 'active'
                   AND product_id NOT IN (
                     SELECT product_id FROM inventory WHERE date = '$today'
                   )";
mysqli_query($conn, $ensure_inv_sql);

$inventory_sql = "SELECT p.*, 
                         p.image_path,
                         IFNULL(i.opening_stock, 0) as opening_stock,
                         IFNULL(i.current_stock, 0) as current_stock,
                         IFNULL(i.closing_stock, 0) as closing_stock,
                         (IFNULL(i.opening_stock, 0) - IFNULL(i.current_stock, 0)) as sold_today
                  FROM products p
                  LEFT JOIN inventory i ON p.product_id = i.product_id AND i.date = '$today'
                  WHERE p.status = 'active'
                  ORDER BY p.product_name";
$inventory_result = mysqli_query($conn, $inventory_sql);

// Calculate totals
$total_opening = 0;
$total_current = 0;
$total_sold = 0;
$low_stock_count = 0;

$temp_result = mysqli_query($conn, $inventory_sql);
while ($row = mysqli_fetch_assoc($temp_result)) {
    $total_opening += $row['opening_stock'];
    $total_current += $row['current_stock'];
    $total_sold += $row['sold_today'];
    if ($row['current_stock'] < 20) {
        $low_stock_count++;
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- Sweet Alert Library -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.all.min.js"></script>

<style>
/* Inventory Styles - Dark Teal Theme (Dashboard Design) */
:root {
    --primary-teal: #1a4d5c;
    --secondary-teal: #0f3543;
    --dark-teal: #082a33;
    --accent-cyan: #22d3ee;
    --accent-green: #4ade80;
    --accent-blue: #3b82f6;
    --accent-orange: #f59e0b;
    --accent-red: #ef4444;
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

body {
    background-color: var(--light-bg);
    color: var(--text-dark);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Page Header */
.page-header {
    background: linear-gradient(135deg, var(--primary-teal) 0%, var(--secondary-teal) 100%);
    border-radius: 12px;
    padding: 2rem 1.5rem 1.5rem 1.5rem;
    color: #ffffff;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-medium);
    border: none;
    border-left: 5px solid var(--accent-cyan);
    backdrop-filter: blur(10px);
}}

.page-header h2 {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    color: #ffffff;
    letter-spacing: -0.5px;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
}

.page-header h2 i {
    color: var(--accent-cyan);
}

.page-header p {
    color: rgba(255, 255, 255, 0.95);
    margin-bottom: 0;
    font-size: 1.05rem;
    letter-spacing: 0.3px;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.stat-card {
    background: linear-gradient(135deg, var(--card-bg) 0%, #f1f5f9 100%);
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: var(--shadow-light);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-top: 3px solid;
    height: 100%;
    border-left: 1px solid rgba(26, 77, 92, 0.05);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, transparent 0%, rgba(34, 211, 238, 0.05) 100%);
    border-radius: 0 0 0 100%;
}

.stat-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-heavy);
}

.stat-card.opening {
    border-top-color: var(--accent-blue);
}

.stat-card.current {
    border-top-color: var(--accent-green);
}

.stat-card.sold {
    border-top-color: #8b5cf6;
}

.stat-card.low {
    border-top-color: var(--accent-red);
}

.stat-card-content {
    display: flex;
    align-items: flex-start;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    margin-right: 12px;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.stat-icon.opening {
    background: linear-gradient(135deg, var(--accent-blue), #1d4ed8);
    color: white;
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
}

.stat-icon.current {
    background: linear-gradient(135deg, var(--accent-green), #22c55e);
    color: white;
    box-shadow: 0 6px 20px rgba(74, 222, 128, 0.3);
}

.stat-icon.sold {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    box-shadow: 0 6px 20px rgba(139, 92, 246, 0.3);
}

.stat-icon.low {
    background: linear-gradient(135deg, var(--accent-red), #dc2626);
    color: white;
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.3);
}

.stat-main {
    flex-grow: 1;
}

.stat-number {
    font-size: 1.85rem;
    font-weight: 800;
    color: var(--text-dark);
    margin-bottom: 0.5rem;
    letter-spacing: -0.3px;
    background: linear-gradient(135deg, var(--primary-teal), var(--secondary-teal));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stat-label {
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--text-light);
    font-weight: 700;
    margin-bottom: 0.75rem;
}

/* Main Content Card */
.content-card {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: var(--shadow-light);
    overflow: hidden;
    margin-bottom: 1.5rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    transition: all 0.3s ease;
}

.content-card:hover {
    box-shadow: var(--shadow-medium);
}

.card-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 1.25rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.card-header h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0;
    display: flex;
    align-items: center;
    letter-spacing: -0.3px;
}

.card-header h3 i {
    margin-right: 0.75rem;
    color: var(--primary-teal);
    font-size: 1.4rem;
}

.date-picker {
    background: var(--card-bg);
    border: 1.5px solid var(--border-color);
    border-radius: 8px;
    padding: 0.65rem 1rem;
    font-size: 0.9rem;
    color: var(--text-dark);
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
}

.date-picker:focus {
    outline: none;
    border-color: var(--accent-cyan);
    box-shadow: 0 0 0 4px rgba(34, 211, 238, 0.15);
    background: #f0feff;
}

/* Inventory Table */
.table-container {
    padding: 1.25rem;
    overflow-x: auto;
}

.inventory-table {
    width: 100%;
    border-collapse: collapse;
}

.inventory-table thead {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.inventory-table th {
    padding: 0.85rem;
    text-align: left;
    font-weight: 700;
    color: var(--primary-teal);
    border-bottom: 2px solid var(--border-color);
    white-space: nowrap;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.4px;
}

.inventory-table td {
    padding: 0.85rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
    color: var(--text-dark);
}

.inventory-table tbody tr {
    transition: all 0.3s ease;
}

.inventory-table tbody tr:hover {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.inventory-table tbody tr:last-child td {
    border-bottom: none;
}

/* Product Image */
.product-img {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    object-fit: cover;
    display: block;
    box-shadow: 0 4px 12px rgba(26, 77, 92, 0.15);
}

.product-img-placeholder {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-cyan);
    font-size: 1.5rem;
    box-shadow: 0 4px 12px rgba(26, 77, 92, 0.08);
}

/* Stock Input */
.stock-input {
    width: 75px;
    padding: 0.65rem;
    border: 1.5px solid var(--border-color);
    border-radius: 8px;
    text-align: center;
    font-size: 0.9rem;
    color: var(--text-dark);
    font-weight: 600;
    transition: all 0.3s ease;
    background: white;
}

.stock-input:focus {
    outline: none;
    border-color: var(--accent-cyan);
    box-shadow: 0 0 0 4px rgba(34, 211, 238, 0.15);
    background: #f0feff;
}

/* Stock Badges */
.stock-badge {
    padding: 0.5rem 1rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 700;
    display: inline-block;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.stock-critical {
    background: rgba(239, 68, 68, 0.15);
    color: #dc2626;
}

.stock-low {
    background: rgba(245, 158, 11, 0.15);
    color: #d97706;
}

.stock-good {
    background: rgba(74, 222, 128, 0.15);
    color: #15803d;
}

/* Save Button */
.btn-save {
    background: linear-gradient(135deg, var(--accent-green), #16a34a);
    color: white;
    border: none;
    padding: 1rem 2.5rem;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    box-shadow: 0 6px 20px rgba(74, 222, 128, 0.3);
    letter-spacing: 0.3px;
}

.btn-save:hover {
    background: linear-gradient(135deg, #22c55e, #15803d);
    transform: translateY(-4px);
    box-shadow: 0 10px 30px rgba(74, 222, 128, 0.4);
}

.btn-save:disabled {
    background: linear-gradient(135deg, #cbd5e1, #94a3b8);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* History Table */
.history-table {
    width: 100%;
    border-collapse: collapse;
}

.history-table thead {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.history-table th {
    padding: 0.85rem;
    text-align: left;
    font-weight: 700;
    color: var(--primary-teal);
    border-bottom: 2px solid var(--border-color);
    white-space: nowrap;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.4px;
}

.history-table td {
    padding: 0.85rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
    color: var(--text-dark);
}

.history-table tbody tr {
    transition: all 0.3s ease;
}

.history-table tbody tr:hover {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

/* Search */
.search-container {
    position: relative;
    margin-bottom: 0.75rem;
    padding: 0 1.25rem;
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 3rem;
    border: 1.5px solid var(--border-color);
    border-radius: 10px;
    font-size: 0.9rem;
    color: var(--text-dark);
    background: white;
    transition: all 0.3s ease;
    font-weight: 500;
}

.search-input:focus {
    outline: none;
    border-color: var(--accent-cyan);
    box-shadow: 0 0 0 4px rgba(34, 211, 238, 0.15);
    background: #f0feff;
}

.search-input::placeholder {
    color: var(--text-muted);
}

.search-icon {
    position: absolute;
    left: 2.25rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--accent-cyan);
    font-size: 1.1rem;
}

/* Alert Messages */
.alert-custom {
    border-radius: 12px;
    border: none;
    box-shadow: var(--shadow-light);
    border-left: 5px solid;
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

/* PIN Modal Styles */
.pin-input-container {
    position: relative;
    margin: 25px 0;
}

.pin-input {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 55px;
    cursor: default;
}

.pin-display {
    display: flex;
    justify-content: center;
    gap: 12px;
    pointer-events: none;
}

.pin-dot {
    width: 45px;
    height: 45px;
    border: 2.5px solid var(--border-color);
    border-radius: 50%;
    transition: all 0.3s ease;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    box-shadow: var(--shadow-light);
}

.pin-dot.filled {
    background: linear-gradient(135deg, var(--accent-cyan), #06b6d4);
    border-color: var(--accent-cyan);
    transform: scale(1.15);
    box-shadow: 0 6px 20px rgba(34, 211, 238, 0.4);
}

.pin-dot.filled::after {
    content: '•';
    color: white;
    font-size: 28px;
    line-height: 45px;
    text-align: center;
    display: block;
    font-weight: bold;
}

/* Responsive Design */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .date-picker {
        width: 100%;
    }
    
    .inventory-table {
        font-size: 0.85rem;
    }
    
    .stock-input {
        width: 60px;
    }
}

@media (max-width: 576px) {
    .page-header h2 {
        font-size: 1.5rem;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 1.25rem;
    }
    
    .inventory-table {
        display: block;
        overflow-x: auto;
    }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h2><i class="bi bi-box-seam me-2"></i>Inventory Management</h2>
            <p class="mb-0">Track and manage your product inventory in real-time</p>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: '<?php echo addslashes($_SESSION['success_message']); ?>',
                        confirmButtonColor: '#27ae60'
                    });
                });
            </script>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <!-- Summary Cards -->
        <div class="stats-grid">
            <div class="stat-card opening">
                <div class="stat-card-content">
                    <div class="stat-icon opening">
                        <i class="bi bi-box-arrow-in-right"></i>
                    </div>
                    <div class="stat-main">
                        <div class="stat-number"><?php echo number_format($total_opening); ?></div>
                        <div class="stat-label">Opening Stock</div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card current">
                <div class="stat-card-content">
                    <div class="stat-icon current">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-main">
                        <div class="stat-number"><?php echo number_format($total_current); ?></div>
                        <div class="stat-label">Current Stock</div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card sold">
                <div class="stat-card-content">
                    <div class="stat-icon sold">
                        <i class="bi bi-cart-check"></i>
                    </div>
                    <div class="stat-main">
                        <div class="stat-number"><?php echo number_format($total_sold); ?></div>
                        <div class="stat-label">Sold Today</div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card low">
                <div class="stat-card-content">
                    <div class="stat-icon low">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="stat-main">
                        <div class="stat-number"><?php echo $low_stock_count; ?></div>
                        <div class="stat-label">Low Stock Items</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Inventory Form -->
        <div class="content-card">
            <div class="card-header">
                <h3>
                    <i class="bi bi-calendar3 me-2"></i>
                    Inventory for <?php echo date('F d, Y', strtotime($today)); ?>
                </h3>
                <input type="date" 
                       class="date-picker" 
                       value="<?php echo $today; ?>" 
                       onchange="window.location.href='inventory.php?date='+this.value">
            </div>
            
            <!-- Search Bar -->
            <div class="search-container px-3 pt-3">
                <div class="position-relative">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" 
                           id="inventorySearch" 
                           class="search-input" 
                           placeholder="Search products...">
                </div>
            </div>
            
            <div class="table-container">
                <form method="POST" id="inventoryForm">
                    <input type="hidden" name="date" value="<?php echo $today; ?>">
                    
                    <table class="inventory-table" id="inventoryTable">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Product</th>
                                <th>Size</th>
                                <th>Opening Stock</th>
                                <th>Current Stock</th>
                                <th>Sold Today</th>
                                <th>Closing Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($inventory_result, 0);
                            while ($item = mysqli_fetch_assoc($inventory_result)): 
                            ?>
                                <tr class="inventory-row">
                                    <td>
                                        <?php if ($item['image_path'] && file_exists('../' . $item['image_path'])): ?>
                                            <img src="../<?php echo htmlspecialchars($item['image_path']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                 class="product-img">
                                        <?php else: ?>
                                            <div class="product-img-placeholder">
                                                <i class="bi bi-droplet"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($item['size']); ?> <?php echo htmlspecialchars($item['unit'] ?? 'kg'); ?>
                                    </td>
                                    <td>
                                        <input type="number" 
                                               name="opening_stock[<?php echo $item['product_id']; ?>]" 
                                               class="stock-input" 
                                               value="<?php echo $item['opening_stock']; ?>" 
                                               min="0" 
                                               required>
                                    </td>
                                    <td>
                                        <span class="stock-badge <?php 
                                            echo $item['current_stock'] < 10 ? 'stock-critical' : 
                                                 ($item['current_stock'] < 20 ? 'stock-low' : 'stock-good'); 
                                        ?>">
                                            <?php echo $item['current_stock']; ?> units
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="color: var(--primary-purple);"><?php echo $item['sold_today']; ?></strong>
                                    </td>
                                    <td>
                                        <input type="number" 
                                               name="closing_stock[<?php echo $item['product_id']; ?>]" 
                                               class="stock-input" 
                                               value="<?php echo $item['closing_stock']; ?>" 
                                               min="0" 
                                               required>
                                    </td>
                                    <td>
                                        <?php if ($item['current_stock'] < 10): ?>
                                            <span class="stock-badge stock-critical">Low Stock</span>
                                        <?php elseif ($item['current_stock'] < 20): ?>
                                            <span class="stock-badge stock-low">Low Stock</span>
                                        <?php else: ?>
                                            <span class="stock-badge stock-good">Good</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    
                    <div class="text-end mt-4">
                        <button type="button" id="saveInventoryBtn" class="btn-save" onclick="saveInventory()">
                            <i class="bi bi-save"></i> Save Inventory
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Inventory History -->
        <div class="content-card">
            <div class="card-header">
                <h3>
                    <i class="bi bi-clock-history me-2"></i>
                    Inventory History (Last 7 Days)
                </h3>
            </div>
            <div class="table-container">
                <?php
                $history_sql = "SELECT 
                    i.*, 
                    p.product_name, 
                    p.size, 
                    p.unit, 
                    p.image_path, 
                    u.full_name
                    FROM inventory i
                    JOIN products p ON i.product_id = p.product_id
                    JOIN users u ON i.updated_by = u.user_id
                    WHERE i.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    ORDER BY i.date DESC, p.product_name";
                $history_result = mysqli_query($conn, $history_sql);
                ?>
                
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Opening</th>
                            <th>Closing</th>
                            <th>Sold</th>
                            <th>Updated By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($history = mysqli_fetch_assoc($history_result)): 
                            $sold = $history['opening_stock'] - $history['closing_stock'];
                        ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($history['date'])); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($history['image_path'] && file_exists('../' . $history['image_path'])): ?>
                                            <img src="../<?php echo htmlspecialchars($history['image_path']); ?>" 
                                                 alt="<?php echo htmlspecialchars($history['product_name']); ?>"
                                                 style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px; margin-right: 0.75rem;">
                                        <?php else: ?>
                                            <div style="width: 40px; height: 40px; background: var(--light-bg); border-radius: 6px; display: flex; align-items: center; justify-content: center; margin-right: 0.75rem;">
                                                <i class="bi bi-droplet" style="color: var(--text-light);"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div><?php echo htmlspecialchars($history['product_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($history['size']); ?><?php echo htmlspecialchars($history['unit'] ?? 'kg'); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $history['opening_stock']; ?></td>
                                <td><?php echo $history['closing_stock']; ?></td>
                                <td><strong><?php echo $sold; ?></strong></td>
                                <td><?php echo htmlspecialchars($history['full_name']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <?php if (mysqli_num_rows($history_result) == 0): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox display-4 mb-3"></i>
                        <p>No inventory history found</p>
                    </div>
                <?php endif; ?>
            </div>
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
                <p class="text-muted mb-4">Enter your 6-digit PIN to save inventory changes:</p>
                <div class="pin-input-container">
                    <input type="text" id="pinInput" maxlength="6" pattern="\d*" 
                           class="pin-input" autocomplete="off">
                    <div class="pin-display" id="pinDisplay">
                        <?php for($i = 0; $i < 6; $i++): ?>
                        <div class="pin-dot"></div>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="alert alert-danger mt-3" id="pinError" style="display: none;">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <span id="pinErrorMessage"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" id="confirmPinBtn" onclick="verifyPin()">
                    <i class="bi bi-check-circle me-2"></i>Verify PIN
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('inventorySearch');
    const inventoryRows = document.querySelectorAll('.inventory-row');
    
    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase().trim();
        
        inventoryRows.forEach(row => {
            const productName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const size = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            const status = row.querySelector('td:nth-child(8)').textContent.toLowerCase();
            
            const matchFound = productName.includes(searchTerm) || 
                             size.includes(searchTerm) || 
                             status.includes(searchTerm);
            
            row.style.display = matchFound ? '' : 'none';
        });
    });
    
    // Auto-focus on opening stock inputs
    const openingInputs = document.querySelectorAll('input[name^="opening_stock"]');
    openingInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Auto-calculate closing stock based on current stock
            const row = this.closest('tr');
            const openingStock = parseFloat(this.value) || 0;
            const soldToday = parseFloat(row.querySelector('td:nth-child(6) strong').textContent) || 0;
            const closingInput = row.querySelector('input[name^="closing_stock"]');
            
            // Set closing stock = opening stock - sold today
            closingInput.value = Math.max(0, openingStock - soldToday);
        });
    });
});

// Real-time clock
function updateClock() {
    const now = new Date();
    const hours = now.getHours();
    const minutes = now.getMinutes();
    const seconds = now.getSeconds();
    const ampm = hours >= 12 ? 'PM' : 'AM';
    const displayHours = hours % 12 || 12;
    const displayMinutes = minutes < 10 ? '0' + minutes : minutes;
    const displaySeconds = seconds < 10 ? '0' + seconds : seconds;
    
    const clockElement = document.querySelector('.page-header small');
    if (clockElement) {
        clockElement.textContent = 'Last updated: ' + displayHours + ':' + displayMinutes + ':' + displaySeconds + ' ' + ampm;
    }
}

setInterval(updateClock, 1000);
updateClock();

// Smooth animations
document.addEventListener('DOMContentLoaded', function() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    document.querySelectorAll('.stat-card, .content-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        observer.observe(card);
    });
});

// Save inventory with PIN verification
async function saveInventory() {
    const saveBtn = document.getElementById('saveInventoryBtn');
    const originalText = saveBtn.innerHTML;
    
    // Disable button and show loading
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
    
    try {
        const formData = new FormData(document.getElementById('inventoryForm'));
        formData.append('update_inventory', '1');
        
        const response = await fetch('inventory.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        const result = await response.json();
        
        if (result.requires_pin) {
            // Show PIN modal
            showPinModal();
        } else if (result.success) {
            // Success without PIN
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: result.message || 'Inventory updated successfully!',
                confirmButtonColor: '#27ae60'
            }).then(() => {
                window.location.reload();
            });
        } else {
            throw new Error(result.message || 'Failed to save inventory');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: error.message || 'Error saving inventory. Please try again.',
            confirmButtonColor: '#e74c3c'
        });
    } finally {
        // Re-enable button
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
    }
}

// PIN Verification Functions
let pinAttempts = 0;
const MAX_PIN_ATTEMPTS = 3;

function showPinModal() {
    pinAttempts = 0;
    
    // Reset PIN input
    document.getElementById('pinInput').value = '';
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
}

async function verifyPin() {
    const pin = document.getElementById('pinInput').value;
    const errorDiv = document.getElementById('pinError');
    const errorMessage = document.getElementById('pinErrorMessage');
    const confirmBtn = document.getElementById('confirmPinBtn');
    
    if (pin.length !== 6) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid PIN',
            text: 'PIN must be 6 digits',
            confirmButtonColor: '#e67e22'
        });
        return;
    }
    
    // Disable button during verification
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Verifying...';
    
    try {
        const formData = new FormData(document.getElementById('inventoryForm'));
        formData.append('update_with_pin', '1');
        formData.append('pin', pin);
        
        const response = await fetch('inventory.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        const result = await response.json();
        
        if (result.success) {
            // PIN verified successfully
            const pinModal = bootstrap.Modal.getInstance(document.getElementById('pinModal'));
            pinModal.hide();
            
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: result.message || 'Inventory updated successfully!',
                confirmButtonColor: '#27ae60'
            }).then(() => {
                window.location.reload();
            });
        } else {
            // PIN verification failed
            pinAttempts++;
            
            if (pinAttempts >= MAX_PIN_ATTEMPTS) {
                const pinModal = bootstrap.Modal.getInstance(document.getElementById('pinModal'));
                pinModal.hide();
                
                Swal.fire({
                    icon: 'error',
                    title: 'Maximum Attempts Exceeded',
                    text: 'You have exceeded the maximum number of PIN verification attempts.',
                    confirmButtonColor: '#e74c3c'
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid PIN',
                    text: `${MAX_PIN_ATTEMPTS - pinAttempts} attempt(s) remaining`,
                    confirmButtonColor: '#e74c3c'
                });
                
                // Clear PIN input
                document.getElementById('pinInput').value = '';
                updatePinDisplay();
                
                // Re-enable button
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Verify PIN';
                
                // Refocus PIN input
                setTimeout(() => {
                    document.getElementById('pinInput').focus();
                }, 300);
            }
        }
    } catch (error) {
        console.error('Error verifying PIN:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Error verifying PIN. Please try again.',
            confirmButtonColor: '#e74c3c'
        });
        
        // Re-enable button
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Verify PIN';
    }
}

// PIN input handling
document.getElementById('pinInput').addEventListener('input', function(e) {
    // Allow only numbers
    this.value = this.value.replace(/\D/g, '');
    
    // Limit to 6 digits
    if (this.value.length > 6) {
        this.value = this.value.slice(0, 6);
    }
    
    updatePinDisplay();
    
    // Hide error when user starts typing
    document.getElementById('pinError').style.display = 'none';
    
    // Enable verify button when PIN is 6 digits
    document.getElementById('confirmPinBtn').disabled = this.value.length !== 6;
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

// Notification function using Sweet Alert
function showNotification(message, type = 'info') {
    const iconMap = {
        'success': 'success',
        'danger': 'error',
        'warning': 'warning',
        'info': 'info'
    };
    
    const colorMap = {
        'success': '#27ae60',
        'danger': '#e74c3c',
        'warning': '#e67e22',
        'info': '#3498db'
    };
    
    Swal.fire({
        icon: iconMap[type] || 'info',
        title: type === 'success' ? 'Success!' : type === 'danger' ? 'Error!' : 'Notification',
        text: message,
        confirmButtonColor: colorMap[type] || '#3498db',
        toast: false,
        timer: 4000,
        timerProgressBar: true,
        showConfirmButton: true
    });
}
</script>

<style>
/* PIN Modal Styles */
.pin-input-container {
    position: relative;
    margin: 20px 0;
}

.pin-input {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 50px;
    cursor: default;
}

.pin-display {
    display: flex;
    justify-content: center;
    gap: 10px;
    pointer-events: none;
}

.pin-dot {
    width: 40px;
    height: 40px;
    border: 2px solid #e0e0e0;
    border-radius: 50%;
    transition: all 0.3s ease;
    background: #f8f9fa;
}

.pin-dot.filled {
    background: #3498db;
    border-color: #3498db;
    transform: scale(1.1);
}

.pin-dot.filled::after {
    content: '•';
    color: white;
    font-size: 24px;
    line-height: 40px;
    text-align: center;
    display: block;
}
</style>

<?php include '../includes/footer.php'; ?>