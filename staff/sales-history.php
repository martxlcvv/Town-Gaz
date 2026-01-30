<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_staff();

$page_title = "Sales History";

// Get filter parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';

// Build query
$where_clauses = ["s.status = 'completed'"];
$where_clauses[] = "DATE(s.created_at) BETWEEN '$date_from' AND '$date_to'";

if (!empty($payment_method)) {
    $where_clauses[] = "s.payment_method = '$payment_method'";
}

$where_sql = implode(' AND ', $where_clauses);

// Get sales data
$sales_sql = "SELECT s.*, c.customer_name, u.full_name as staff_name,
              (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.sale_id) as total_items
              FROM sales s
              LEFT JOIN customers c ON s.customer_id = c.customer_id
              JOIN users u ON s.user_id = u.user_id
              WHERE $where_sql
              ORDER BY s.created_at DESC";
$sales_result = mysqli_query($conn, $sales_sql);
// Get summary
$summary_sql = "SELECT 
                COUNT(*) as total_transactions,
                IFNULL(SUM(total_amount), 0) as total_sales,
                IFNULL(SUM(total_profit), 0) as total_profit
                FROM sales s
                WHERE $where_sql";
$summary_result = mysqli_query($conn, $summary_sql);
$summary = mysqli_fetch_assoc($summary_result);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col">
                <h2><i class="bi bi-clock-history me-2"></i>Sales History</h2>
                <p class="text-muted">View your sales transactions</p>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-primary">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Total Transactions</h6>
                        <h3 class="mb-0"><?php echo number_format($summary['total_transactions']); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-success">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Total Sales</h6>
                        <h3 class="mb-0 text-success">₱<?php echo number_format($summary['total_sales'], 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-info">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Total Profit</h6>
                        <h3 class="mb-0 text-info">₱<?php echo number_format($summary['total_profit'], 2); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Date From</label>
                        <input type="date" class="form-control" name="date_from" 
                               value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date To</label>
                        <input type="date" class="form-control" name="date_to" 
                               value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-select" name="payment_method">
                            <option value="">All Methods</option>
                            <option value="cash" <?php echo ($payment_method == 'cash') ? 'selected' : ''; ?>>Cash</option>
                            <option value="gcash" <?php echo ($payment_method == 'gcash') ? 'selected' : ''; ?>>GCash</option>
                            <option value="card" <?php echo ($payment_method == 'card') ? 'selected' : ''; ?>>Card</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-filter"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sales Table -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-receipt me-2"></i>Sales Transactions
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Date & Time</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Amount</th>
                                <th>Profit</th>
                                <th>Payment</th>
                                <th>Staff</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($sales_result) > 0): ?>
                                <?php while ($sale = mysqli_fetch_assoc($sales_result)): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($sale['invoice_number']); ?></strong></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($sale['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?></td>
                                        <td><?php echo $sale['total_items']; ?> items</td>
                                        <td>₱<?php echo number_format($sale['total_amount'], 2); ?></td>
                                        <td class="text-success">₱<?php echo number_format($sale['total_profit'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $sale['payment_method'] == 'cash' ? 'success' : 
                                                     ($sale['payment_method'] == 'gcash' ? 'info' : 'primary'); 
                                            ?>">
                                                <?php echo strtoupper($sale['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($sale['staff_name']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewReceipt(<?php echo $sale['sale_id']; ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center">No sales found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sales Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="receiptContent">
                <!-- Receipt content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function viewReceipt(saleId) {
    fetch(`view-receipt.php?id=${saleId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('receiptContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('receiptModal')).show();
        });
}
</script>

<?php include '../includes/footer.php'; ?>