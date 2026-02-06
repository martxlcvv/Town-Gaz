<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_admin();
prevent_cache();

$page_title = "Customer Management";

// Helper function to clean all output buffers for JSON responses
function json_response($data, $http_code = 200) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($http_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    die();
}

// Function to verify admin PIN for sensitive operations
function verifyAdminPin($pin) {
    global $conn;
    
    $user_id = $_SESSION['user_id'];
    
    // Validate PIN format first
    if (!is_numeric($pin) || strlen($pin) !== 6) {
        return false;
    }
    
    $sql = "SELECT pin_hash FROM admin_pins WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        return false;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$data) {
        return false;
    }
    
    // Use password_verify with the stored hash
    return password_verify($pin, $data['pin_hash']);
}

// Handle customer operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify session token
    if (!verify_csrf_token($_POST['session_token'] ?? '')) {
        $_SESSION['error_message'] = "Invalid session. Please try again.";
        header('Location: customers.php');
        exit();
    }
    
    if (isset($_POST['add_customer'])) {
        $name = clean_input($_POST['customer_name']);
        $contact = clean_input($_POST['contact']);
        $address = clean_input($_POST['address']);
        $type = clean_input($_POST['customer_type']);
        
        $sql = "INSERT INTO customers (customer_name, contact, address, customer_type) 
                VALUES ('$name', '$contact', '$address', '$type')";
        
        if (mysqli_query($conn, $sql)) {
            log_audit($_SESSION['user_id'], 'CREATE', 'customers', mysqli_insert_id($conn), null, 
                     ['name' => $name]);
            $_SESSION['success_message'] = "Customer added successfully!";
        }
        header('Location: customers.php');
        exit();
    }
    
    if (isset($_POST['edit_customer'])) {
        $id = clean_input($_POST['customer_id']);
        $name = clean_input($_POST['customer_name']);
        $contact = clean_input($_POST['contact']);
        $address = clean_input($_POST['address']);
        $type = clean_input($_POST['customer_type']);
        
        // Get old values for audit
        $old_sql = "SELECT * FROM customers WHERE customer_id = $id";
        $old_data = mysqli_fetch_assoc(mysqli_query($conn, $old_sql));
        
        $sql = "UPDATE customers 
                SET customer_name = '$name', contact = '$contact',
                    address = '$address', customer_type = '$type'
                WHERE customer_id = $id";
        
        if (mysqli_query($conn, $sql)) {
            log_audit($_SESSION['user_id'], 'UPDATE', 'customers', $id, $old_data, 
                     ['name' => $name, 'contact' => $contact]);
            $_SESSION['success_message'] = "Customer updated successfully!";
        }
        header('Location: customers.php');
        exit();
    }
    
    if (isset($_POST['delete_customer'])) {
        $id = clean_input($_POST['customer_id']);
        $pin = clean_input($_POST['delete_pin'] ?? '');
        
        // Verify PIN before deletion
        if (empty($pin)) {
            $_SESSION['error_message'] = "PIN verification required!";
            header('Location: customers.php');
            exit();
        }
        
        // Verify PIN using database
        if (!verifyAdminPin($pin)) {
            $_SESSION['error_message'] = "Invalid PIN!";
            header('Location: customers.php');
            exit();
        }
        
        $sql = "UPDATE customers SET status = 'inactive' WHERE customer_id = $id";
        if (mysqli_query($conn, $sql)) {
            log_audit($_SESSION['user_id'], 'DELETE', 'customers', $id, null, ['status' => 'inactive']);
            $_SESSION['success_message'] = "Customer deactivated successfully!";
        }
        header('Location: customers.php');
        exit();
    }
    
    if (isset($_POST['activate_customer'])) {
        $id = clean_input($_POST['customer_id']);
        
        $sql = "UPDATE customers SET status = 'active' WHERE customer_id = $id";
        if (mysqli_query($conn, $sql)) {
            log_audit($_SESSION['user_id'], 'UPDATE', 'customers', $id, null, ['status' => 'active']);
            $_SESSION['success_message'] = "Customer activated successfully!";
        }
        header('Location: customers.php');
        exit();
    }
}

// Get statistics
$stats_sql = "SELECT 
              COUNT(*) as total_customers,
              SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_customers,
              SUM(CASE WHEN customer_type = 'commercial' THEN 1 ELSE 0 END) as commercial_customers,
              SUM(CASE WHEN customer_type = 'regular' THEN 1 ELSE 0 END) as regular_customers
              FROM customers";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get all customers with their purchase data
$customers_sql = "SELECT c.*, 
                  COUNT(DISTINCT s.sale_id) as total_orders,
                  IFNULL(SUM(s.total_amount), 0) as total_spent,
            MAX(s.created_at) as last_purchase
                  FROM customers c
                  LEFT JOIN sales s ON c.customer_id = s.customer_id AND s.status = 'completed'
                  GROUP BY c.customer_id
                  ORDER BY c.created_at DESC";
$customers_result = mysqli_query($conn, $customers_sql);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <div class="header-content">
                <h1 class="mb-0">
                    <i class="bi bi-people-fill me-2"></i>Customer Management
                </h1>
                <p class="mb-0">
                    Manage and track all your customers
                </p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                    <i class="bi bi-plus-circle me-2"></i>Add New Customer
                </button>
            </div>
        </div>
        
        <!-- Alert Messages (shown via SweetAlert in JavaScript) -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showSuccess('<?php echo htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8'); ?>');
                });
            </script>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="d-flex align-items-start">
                    <div class="stat-icon-wrapper bg-primary">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="stat-number">
                            <?php echo number_format($stats['total_customers']); ?>
                        </div>
                        <div class="stat-label">Total Customers</div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card active">
                <div class="d-flex align-items-start">
                    <div class="stat-icon-wrapper bg-success">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="stat-number">
                            <?php echo number_format($stats['active_customers']); ?>
                        </div>
                        <div class="stat-label">Active Customers</div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card commercial">
                <div class="d-flex align-items-start">
                    <div class="stat-icon-wrapper bg-info">
                        <i class="bi bi-building"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="stat-number">
                            <?php echo number_format($stats['commercial_customers']); ?>
                        </div>
                        <div class="stat-label">Commercial</div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card regular">
                <div class="d-flex align-items-start">
                    <div class="stat-icon-wrapper bg-warning">
                        <i class="bi bi-person"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="stat-number">
                            <?php echo number_format($stats['regular_customers']); ?>
                        </div>
                        <div class="stat-label">Regular</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customers Table -->
        <div class="table-container">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0 d-flex align-items-center">
                            <i class="bi bi-list-ul text-primary me-2"></i>All Customers
                            <span class="badge bg-primary ms-2 rounded-pill"><?php echo mysqli_num_rows($customers_result); ?></span>
                        </h5>
                    </div>
                    <div class="col-md-6 mt-3 mt-md-0">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" id="searchInput" class="form-control border-start-0" 
                                   placeholder="Search customers...">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="customersTable">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 60px;">
                                    <i class="bi bi-hash text-muted"></i> ID
                                </th>
                                <th style="width: 200px;">
                                    <i class="bi bi-person text-muted me-1"></i>Customer
                                </th>
                                <th style="width: 150px;">
                                    <i class="bi bi-telephone text-muted me-1"></i>Contact
                                </th>
                                <th class="d-none d-lg-table-cell">
                                    <i class="bi bi-geo-alt text-muted me-1"></i>Address
                                </th>
                                <th class="text-center" style="width: 120px;">
                                    <i class="bi bi-tag text-muted me-1"></i>Type
                                </th>
                                <th class="text-center d-none d-xl-table-cell" style="width: 100px;">
                                    <i class="bi bi-cart text-muted me-1"></i>Orders
                                </th>
                                <th class="text-end d-none d-xl-table-cell" style="width: 120px;">
                                    <i class="bi bi-currency-dollar text-muted me-1"></i>Total Spent
                                </th>
                                <th class="text-center" style="width: 100px;">
                                    <i class="bi bi-toggle-on text-muted me-1"></i>Status
                                </th>
                                <th class="text-center" style="width: 200px;">
                                    <i class="bi bi-gear text-muted me-1"></i>Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($customers_result) > 0): ?>
                                <?php while ($customer = mysqli_fetch_assoc($customers_result)): ?>
                                    <tr class="customer-row">
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border">#<?php echo $customer['customer_id']; ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="customer-avatar me-2">
                                                    <?php echo strtoupper(substr($customer['customer_name'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($customer['customer_name']); ?></div>
                                                    <small class="text-muted">
                                                        ID #<?php echo $customer['customer_id']; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-phone text-success me-2"></i>
                                                <span><?php echo htmlspecialchars($customer['contact']); ?></span>
                                            </div>
                                        </td>
                                        <td class="d-none d-lg-table-cell">
                                            <small class="text-muted">
                                                <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                                                <?php echo htmlspecialchars(substr($customer['address'], 0, 40)) . (strlen($customer['address']) > 40 ? '...' : ''); ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($customer['customer_type'] == 'commercial'): ?>
                                                <span class="badge bg-primary d-inline-flex align-items-center gap-1">
                                                    <i class="bi bi-building"></i> Commercial
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary d-inline-flex align-items-center gap-1">
                                                    <i class="bi bi-person"></i> Regular
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center d-none d-xl-table-cell">
                                            <span class="badge bg-info text-white px-3 py-2">
                                                <i class="bi bi-cart-fill me-1"></i><?php echo $customer['total_orders']; ?>
                                            </span>
                                        </td>
                                        <td class="text-end d-none d-xl-table-cell">
                                            <strong class="text-success fs-6">
                                                <?php echo format_currency($customer['total_spent']); ?>
                                            </strong>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($customer['status'] == 'active'): ?>
                                                <span class="badge bg-success d-inline-flex align-items-center gap-1 px-3 py-2">
                                                    <i class="bi bi-check-circle-fill"></i> Active
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger d-inline-flex align-items-center gap-1 px-3 py-2">
                                                    <i class="bi bi-x-circle-fill"></i> Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-info" 
                                                        onclick="viewCustomer(<?php echo htmlspecialchars(json_encode($customer)); ?>)"
                                                        title="View Details">
                                                    <i class="bi bi-eye-fill"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick="editCustomer(<?php echo htmlspecialchars(json_encode($customer)); ?>)"
                                                        title="Edit Customer">
                                                    <i class="bi bi-pencil-fill"></i>
                                                </button>
                                                <?php if ($customer['status'] == 'active'): ?>
                                                    <button class="btn btn-sm btn-danger" 
                                                            onclick="deleteCustomerConfirm(<?php echo $customer['customer_id']; ?>)"
                                                            title="Deactivate">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <form method="POST" style="display: inline;">
                                                        <?php echo output_token_field(); ?>
                                                        <input type="hidden" name="customer_id" value="<?php echo $customer['customer_id']; ?>">
                                                        <button type="submit" name="activate_customer" 
                                                                class="btn btn-sm btn-success" 
                                                                title="Activate">
                                                            <i class="bi bi-check-circle-fill"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <div class="empty-state">
                                            <div class="empty-icon">
                                                <i class="bi bi-people"></i>
                                            </div>
                                            <h5 class="mt-3">No Customers Found</h5>
                                            <p class="text-muted">Start by adding your first customer!</p>
                                            <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                                                <i class="bi bi-plus-circle me-2"></i>Add Customer
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus-fill me-2"></i>Add New Customer
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addCustomerForm">
                <div class="modal-body p-3">
                    <?php echo output_token_field(); ?>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size: 0.85rem;">
                                <i class="bi bi-person text-primary me-1"></i>Customer Name *
                            </label>
                            <input type="text" name="customer_name" class="form-control form-control-sm" 
                                   placeholder="Enter full name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size: 0.85rem;">
                                <i class="bi bi-telephone text-success me-1"></i>Contact Number *
                            </label>
                            <input type="text" name="contact" class="form-control form-control-sm" 
                                   placeholder="09XX XXX XXXX" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size: 0.85rem;">
                                <i class="bi bi-tag text-warning me-1"></i>Customer Type *
                            </label>
                            <select name="customer_type" class="form-select form-select-sm" required>
                                <option value="regular">Regular - Individual Customer</option>
                                <option value="commercial">Commercial - Business Account</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size: 0.85rem;">
                                <i class="bi bi-geo-alt text-danger me-1"></i>Complete Address *
                            </label>
                            <textarea name="address" class="form-control form-control-sm" rows="2" 
                                      placeholder="Enter complete address" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 0.75rem 1rem;">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" name="add_customer" class="btn btn-primary btn-sm">
                        <i class="bi bi-save me-1"></i>Save Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Customer Modal -->
<div class="modal fade" id="editCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-fill me-2"></i>Edit Customer
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-3">
                    <?php echo output_token_field(); ?>
                    <input type="hidden" name="customer_id" id="edit_customer_id">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size: 0.85rem;">
                                <i class="bi bi-person text-primary me-1"></i>Customer Name *
                            </label>
                            <input type="text" name="customer_name" id="edit_customer_name" 
                                   class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size: 0.85rem;">
                                <i class="bi bi-telephone text-success me-1"></i>Contact Number *
                            </label>
                            <input type="text" name="contact" id="edit_contact" 
                                   class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size: 0.85rem;">
                                <i class="bi bi-tag text-warning me-1"></i>Customer Type *
                            </label>
                            <select name="customer_type" id="edit_customer_type" 
                                    class="form-select form-select-sm" required>
                                <option value="regular">Regular - Individual Customer</option>
                                <option value="commercial">Commercial - Business Account</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size: 0.85rem;">
                                <i class="bi bi-geo-alt text-danger me-1"></i>Complete Address *
                            </label>
                            <textarea name="address" id="edit_address" 
                                      class="form-control form-control-sm" rows="2" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 0.75rem 1rem;">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" name="edit_customer" class="btn btn-warning btn-sm">
                        <i class="bi bi-save me-1"></i>Update Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Customer Modal - COMPACT & CLEAN -->
<div class="modal fade" id="viewCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-gradient-to-r from-blue-600 to-blue-700" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); padding: 0.75rem 1rem;">
                <h5 class="modal-title text-white mb-0" style="font-size: 1rem; font-weight: 600;">
                    <i class="bi bi-person-circle me-2"></i>Customer Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="customerDetails" style="padding: 1rem;">
                <!-- Customer details will be loaded here -->
            </div>
            <div class="modal-footer" style="padding: 0.6rem 1rem; background: #f8f9fa;">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Customers Page - SAME DESIGN as Dashboard, Inventory, Products & Promotions */
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

/* Welcome Header - compact */
.welcome-header {
    background: linear-gradient(135deg, #1a4d5c 0%, #0f3543 100%);
    border-radius: 10px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 15px rgba(26, 77, 92, 0.2);
    color: white;
}

.welcome-header h1 {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    color: #ffffff;
    letter-spacing: 0.3px;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
}

.welcome-header p {
    color: rgba(255, 255, 255, 0.95);
    margin-bottom: 0;
    font-size: 0.9rem;
    letter-spacing: 0.3px;
}

.header-content {
    flex: 1;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

/* Stats Cards - FLAT DESIGN */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: var(--card-bg);
    border-radius: 10px;
    padding: 1.25rem;
    box-shadow: var(--shadow-light);
    transition: all 0.3s ease;
    border-top: 4px solid;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-medium);
}

.stat-card.total {
    border-top-color: var(--primary-blue);
}

.stat-card.active {
    border-top-color: var(--primary-green);
}

.stat-card.commercial {
    border-top-color: var(--primary-purple);
}

.stat-card.regular {
    border-top-color: var(--primary-yellow);
}

.stat-icon-wrapper {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.9rem;
    margin-right: 1rem;
    flex-shrink: 0;
    box-shadow: 0 4px 15px rgba(26, 77, 92, 0.2);
    transition: all 0.3s ease;
}

.stat-card:hover .stat-icon-wrapper {
    transform: scale(1.1);
}

.stat-icon-wrapper.bg-primary {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: var(--text-primary);
}

.stat-icon-wrapper.bg-success {
    background: linear-gradient(135deg, #4ade80, #22c55e);
    color: var(--text-primary);
}

.stat-icon-wrapper.bg-info {
    background: linear-gradient(135deg, #06b6d4, #0891b2);
    color: var(--text-primary);
}

.stat-icon-wrapper.bg-warning {
    background: linear-gradient(135deg, #f97316, #ea580c);
    color: var(--text-primary);
}

/* Customer Avatar */
.customer-avatar {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
    background: var(--primary-blue);
    color: white;
    box-shadow: var(--shadow-light);
}

/* Table Styles */
.table-container {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: var(--shadow-light);
    overflow: hidden;
}

.table thead {
    background: var(--light-bg);
}

.table thead th {
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border-color);
    padding: 1rem;
    white-space: nowrap;
    color: var(--text-dark);
}

.customer-row {
    transition: all 0.3s ease;
}

.customer-row:hover {
    background: var(--light-bg);
    transform: scale(1.01);
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.table tbody td {
    padding: 1rem;
    vertical-align: middle;
}

/* Badges */
.badge {
    font-weight: 600;
    font-size: 0.85rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    border: 1px solid transparent;
}

.bg-success {
    background: var(--primary-green) !important;
    color: white !important;
}

.bg-danger {
    background: var(--primary-red) !important;
    color: white !important;
}

.bg-primary {
    background: var(--primary-blue) !important;
    color: white !important;
}

.bg-secondary {
    background: var(--primary-gray) !important;
    color: white !important;
}

.bg-info {
    background: var(--primary-blue) !important;
    color: white !important;
}

/* Buttons */
.btn {
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    padding: 0.5rem 1rem;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-primary {
    background: var(--primary-blue);
    border: none;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
    color: white;
}

.btn-info {
    background: var(--primary-blue);
    border: none;
    color: white;
}

.btn-info:hover {
    background: #2980b9;
    color: white;
}

.btn-warning {
    background: var(--primary-yellow);
    border: none;
    color: #d68910;
}

.btn-warning:hover {
    background: #f39c12;
    color: white;
}

.btn-danger {
    background: var(--primary-red);
    border: none;
    color: white;
}

.btn-danger:hover {
    background: #c0392b;
    color: white;
}

.btn-success {
    background: var(--primary-green);
    border: none;
    color: white;
}

.btn-success:hover {
    background: #219653;
    color: white;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    background: rgba(52, 152, 219, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: var(--primary-blue);
}

/* Modal Styles */
.modal-header {
    background: var(--light-bg);
    border-bottom: 1px solid var(--border-color);
    padding: 1.25rem;
}

.modal-title {
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
}

.modal-title i {
    color: var(--primary-blue);
    margin-right: 0.5rem;
}

.modal-body {
    padding: 1.25rem;
}

.modal-footer {
    background: var(--light-bg);
    border-top: 1px solid var(--border-color);
    padding: 1.25rem;
}

/* Form Controls */
.form-control, .form-select {
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 0.75rem;
    font-size: 0.9rem;
    color: var(--text-dark);
    background: var(--card-bg);
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.input-group-text {
    background: var(--light-bg);
    border: 1px solid var(--border-color);
    color: var(--text-light);
}

/* Alert Messages */
.alert-custom {
    border-radius: 8px;
    border: none;
    box-shadow: var(--shadow-light);
    border-left: 4px solid;
}

.alert-success {
    background: rgba(39, 174, 96, 0.1);
    border-left-color: var(--primary-green);
    color: var(--text-dark);
}

/* Card Styles */
.card {
    border-radius: 12px;
    border: none;
    box-shadow: var(--shadow-light);
}

.card-header {
    background: var(--light-bg);
    border-bottom: 1px solid var(--border-color);
    padding: 1.25rem;
}

/* COMPACT VIEW CUSTOMER MODAL */
#viewCustomerModal .modal-dialog {
    max-width: 600px;
}

#viewCustomerModal .customer-info-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
}

#viewCustomerModal .info-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 0.75rem;
    border-left: 3px solid #3b82f6;
}

#viewCustomerModal .info-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
}

#viewCustomerModal .info-value {
    font-size: 0.95rem;
    font-weight: 600;
    color: #2c3e50;
}

#viewCustomerModal .stats-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    margin-top: 1rem;
}

#viewCustomerModal .stat-box {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    border-radius: 8px;
    padding: 0.75rem;
    text-align: center;
    color: white;
}

#viewCustomerModal .stat-value {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

#viewCustomerModal .stat-label {
    font-size: 0.75rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

#viewCustomerModal .customer-header {
    text-align: center;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e9ecef;
    margin-bottom: 1rem;
}

#viewCustomerModal .customer-avatar-large {
    width: 70px;
    height: 70px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    font-weight: 700;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
    margin: 0 auto 0.75rem;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

#viewCustomerModal .customer-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 0.25rem;
}

#viewCustomerModal .customer-id {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 0.5rem;
}

#viewCustomerModal .badge {
    font-size: 0.75rem;
    padding: 0.35rem 0.75rem;
}

/* Responsive */
@media (max-width: 768px) {
    .welcome-header h1 {
        font-size: 1.5rem;
    }
    
    .stat-card h3 {
        font-size: 1.5rem;
    }
    
    .table {
        font-size: 0.85rem;
    }
    
    .table thead th,
    .table tbody td {
        padding: 0.75rem 0.5rem;
    }
    
    .btn-group {
        flex-direction: column;
    }
    
    .btn-group .btn {
        width: 100%;
        margin-bottom: 0.25rem;
    }
    
    #viewCustomerModal .modal-dialog {
        max-width: 95%;
        margin: 0.5rem;
    }
    
    #viewCustomerModal .stats-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Display success/error messages using SweetAlert
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['success_message'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?php echo htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8'); ?>',
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: false
        });
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: '<?php echo htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8'); ?>',
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: false
        });
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
});

function editCustomer(customer) {
    document.getElementById('edit_customer_id').value = customer.customer_id;
    document.getElementById('edit_customer_name').value = customer.customer_name;
    document.getElementById('edit_contact').value = customer.contact;
    document.getElementById('edit_address').value = customer.address;
    document.getElementById('edit_customer_type').value = customer.customer_type;
    
    new bootstrap.Modal(document.getElementById('editCustomerModal')).show();
}

function deleteCustomerConfirm(customerId) {
    Swal.fire({
        title: 'Deactivate Customer?',
        text: 'This customer will be marked as inactive. Enter your PIN to confirm.',
        icon: 'warning',
        input: 'password',
        inputPlaceholder: 'Enter PIN',
        inputAttributes: {
            maxlength: '6',
            autocapitalize: 'off',
            autocorrect: 'off'
        },
        showCancelButton: true,
        confirmButtonText: 'Confirm',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        allowOutsideClick: false,
        allowEscapeKey: false,
        preConfirm: (pin) => {
            if (!pin) {
                Swal.showValidationMessage('Please enter your PIN');
                return false;
            }
            if (pin.length !== 4 && pin.length !== 6) {
                Swal.showValidationMessage('PIN must be 4 or 6 digits');
                return false;
            }
            return pin;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Get CSRF token from meta tag
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
            
            // Create hidden form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="customer_id" value="${customerId}">
                <input type="hidden" name="delete_pin" value="${result.value}">
                <input type="hidden" name="delete_customer" value="1">
                <input type="hidden" name="session_token" value="${csrfToken}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function viewCustomer(customer) {
    const lastPurchase = customer.last_purchase ? new Date(customer.last_purchase).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    }) : 'No purchases yet';
    
    const avgOrderValue = customer.total_orders > 0 
        ? (parseFloat(customer.total_spent) / customer.total_orders).toFixed(2)
        : '0.00';
    
    const memberSince = new Date(customer.created_at).toLocaleDateString('en-US', {
        year: 'numeric', 
        month: 'short', 
        day: 'numeric'
    });
    
    const html = `
        <div class="customer-header">
            <div class="customer-avatar-large">
                ${customer.customer_name.substring(0, 2).toUpperCase()}
            </div>
            <div class="customer-name">${customer.customer_name}</div>
            <div class="customer-id">Customer ID: #${customer.customer_id}</div>
            <span class="badge bg-${customer.status == 'active' ? 'success' : 'danger'}">
                <i class="bi bi-${customer.status == 'active' ? 'check-circle-fill' : 'x-circle-fill'}"></i>
                ${customer.status.toUpperCase()}
            </span>
            <span class="badge bg-${customer.customer_type == 'commercial' ? 'primary' : 'secondary'} ms-2">
                <i class="bi bi-${customer.customer_type == 'commercial' ? 'building' : 'person'}"></i>
                ${customer.customer_type.toUpperCase()}
            </span>
        </div>
        
        <div class="customer-info-grid">
            <div class="info-card">
                <div class="info-label"><i class="bi bi-telephone-fill me-1"></i>Contact Number</div>
                <div class="info-value">${customer.contact}</div>
            </div>
            
            <div class="info-card">
                <div class="info-label"><i class="bi bi-geo-alt-fill me-1"></i>Address</div>
                <div class="info-value">${customer.address}</div>
            </div>
            
            <div class="info-card">
                <div class="info-label"><i class="bi bi-calendar-check-fill me-1"></i>Member Since</div>
                <div class="info-value">${memberSince}</div>
            </div>
        </div>
        
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-value"><i class="bi bi-cart-fill"></i> ${customer.total_orders}</div>
                <div class="stat-label">Total Orders</div>
            </div>
            
            <div class="stat-box">
                <div class="stat-value">₱${parseFloat(customer.total_spent).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                <div class="stat-label">Total Spent</div>
            </div>
            
            <div class="stat-box">
                <div class="stat-value">₱${parseFloat(avgOrderValue).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                <div class="stat-label">Avg Order Value</div>
            </div>
            
            <div class="stat-box">
                <div class="stat-value" style="font-size: 0.9rem;">${lastPurchase}</div>
                <div class="stat-label">Last Purchase</div>
            </div>
        </div>
    `;
    
    document.getElementById('customerDetails').innerHTML = html;
    new bootstrap.Modal(document.getElementById('viewCustomerModal')).show();
}

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#customersTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});
</script>

<?php include '../includes/footer.php'; ?>