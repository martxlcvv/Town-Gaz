<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';

// Check if PIN was verified (from session)
if (!isset($_SESSION['pin_verified']) || $_SESSION['pin_verified'] !== true) {
    $_SESSION['error_message'] = 'Admin PIN verification required';
    header('Location: pos.php');
    exit();
}

// Check if PIN verification is still valid (expires after 5 minutes)
$pin_expiry = 300; // 5 minutes in seconds
if ((time() - $_SESSION['pin_verified_at']) > $pin_expiry) {
    unset($_SESSION['pin_verified']);
    unset($_SESSION['pin_verified_at']);
    $_SESSION['error_message'] = 'PIN verification expired. Please verify again.';
    header('Location: pos.php');
    exit();
}

// Clear PIN verification after use (one-time use)
unset($_SESSION['pin_verified']);
unset($_SESSION['pin_verified_at']);

$page_title = "Void Transaction";

if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = 'Invalid transaction ID';
    header('Location: sales.php');
    exit();
}

$sale_id = intval($_GET['id']);

// Get sale details
$sale_sql = "SELECT s.*, c.customer_name, u.full_name as cashier
             FROM sales s
             LEFT JOIN customers c ON s.customer_id = c.customer_id
             JOIN users u ON s.user_id = u.user_id
             WHERE s.sale_id = ?";
$stmt = mysqli_prepare($conn, $sale_sql);
mysqli_stmt_bind_param($stmt, "i", $sale_id);
mysqli_stmt_execute($stmt);
$sale = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$sale) {
    $_SESSION['error_message'] = 'Transaction not found';
    header('Location: sales.php');
    exit();
}

// Check if already voided
if ($sale['status'] == 'voided') {
    $_SESSION['error_message'] = 'Transaction already voided';
    header('Location: sales.php');
    exit();
}

// Get sale items
$items_sql = "SELECT si.*, p.product_name, p.size, p.unit
              FROM sale_items si
              JOIN products p ON si.product_id = p.product_id
              WHERE si.sale_id = ?";
$stmt = mysqli_prepare($conn, $items_sql);
mysqli_stmt_bind_param($stmt, "i", $sale_id);
mysqli_stmt_execute($stmt);
$items = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

// Handle void confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_void'])) {
    $void_reason = trim($_POST['void_reason']);
    
    if (empty($void_reason)) {
        $_SESSION['error_message'] = 'Please provide a reason for voiding';
        header('Location: void_transaction.php?id=' . $sale_id);
        exit();
    }
    
    mysqli_begin_transaction($conn);
    
    try {
        // Update sale status to voided
        $update_sql = "UPDATE sales SET status = 'voided', updated_at = NOW() WHERE sale_id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "i", $sale_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Failed to void transaction');
        }
        
        // Restore inventory for each item
        foreach ($items as $item) {
            $restore_sql = "UPDATE inventory 
                           SET current_stock = current_stock + ? 
                           WHERE product_id = ? AND date = CURDATE()";
            $inv_stmt = mysqli_prepare($conn, $restore_sql);
            mysqli_stmt_bind_param($inv_stmt, "ii", $item['quantity'], $item['product_id']);
            
            if (!mysqli_stmt_execute($inv_stmt)) {
                throw new Exception('Failed to restore inventory');
            }
        }
        
        // Log the void action
        $log_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) 
                   VALUES (?, 'VOID_TRANSACTION', 'sales', ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_sql);
        $details = "Voided transaction {$sale['invoice_number']}. Reason: {$void_reason}";
        mysqli_stmt_bind_param($log_stmt, "iis", $_SESSION['user_id'], $sale_id, $details);
        mysqli_stmt_execute($log_stmt);
        
        mysqli_commit($conn);
        
        $_SESSION['success_message'] = "Transaction {$sale['invoice_number']} has been voided successfully";
        header('Location: sales.php');
        exit();
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header('Location: void_transaction.php?id=' . $sale_id);
        exit();
    }
}

include '../includes/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col">
                <h4 class="mb-0">
                    <i class="bi bi-x-circle text-danger me-2"></i>Void Transaction
                </h4>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-danger text-white">
                        <h6 class="mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>Confirm Transaction Void
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This action will void the transaction and restore inventory. This cannot be undone.
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Invoice Number:</strong></p>
                                <p class="text-muted"><?php echo htmlspecialchars($sale['invoice_number']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Date:</strong></p>
                                <p class="text-muted"><?php echo date('M d, Y h:i A', strtotime($sale['created_at'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Customer:</strong></p>
                                <p class="text-muted"><?php echo $sale['customer_name'] ? htmlspecialchars($sale['customer_name']) : 'Walk-in'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Cashier:</strong></p>
                                <p class="text-muted"><?php echo htmlspecialchars($sale['cashier']); ?></p>
                            </div>
                        </div>
                        
                        <h6 class="mb-3">Items:</h6>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-center">Quantity</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($item['size']); ?> <?php echo htmlspecialchars($item['unit']); ?></small>
                                        </td>
                                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                                        <td class="text-end">₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td class="text-end">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-end">Total:</th>
                                        <th class="text-end">₱<?php echo number_format($sale['total_amount'], 2); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label"><strong>Reason for Voiding:</strong> <span class="text-danger">*</span></label>
                                <textarea name="void_reason" class="form-control" rows="3" required 
                                          placeholder="Enter detailed reason for voiding this transaction..."></textarea>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" name="confirm_void" class="btn btn-danger">
                                    <i class="bi bi-x-circle me-2"></i>Confirm Void
                                </button>
                                <a href="sales.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>