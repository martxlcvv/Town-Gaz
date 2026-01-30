<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';

if (!isset($_GET['id'])) {
    echo '<div class="alert alert-danger">Invalid request</div>';
    exit;
}

$sale_id = (int)$_GET['id'];

// Get sale details
$sql = "SELECT s.*, c.customer_name, c.address, c.contact, 
        u.full_name as cashier, p.amount_paid, p.reference_number
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.customer_id
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN payments p ON s.sale_id = p.sale_id
        WHERE s.sale_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $sale_id);
mysqli_stmt_execute($stmt);
$sale = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$sale) {
    echo '<div class="alert alert-danger">Sale not found</div>';
    exit;
}

// Get sale items
$items_sql = "SELECT si.*, p.product_name, p.size, p.unit
              FROM sale_items si
              JOIN products p ON si.product_id = p.product_id
              WHERE si.sale_id = ?";
$stmt = mysqli_prepare($conn, $items_sql);
mysqli_stmt_bind_param($stmt, "i", $sale_id);
mysqli_stmt_execute($stmt);
$items_result = mysqli_stmt_get_result($stmt);

$change = $sale['amount_paid'] - $sale['total_amount'];
?>

<style>
.receipt-view {
    animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.receipt-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px 15px 0 0;
    text-align: center;
    animation: slideInDown 0.6s ease-out;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.receipt-body {
    padding: 30px;
    background: white;
}

.info-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    border-left: 4px solid #007bff;
    animation: slideInLeft 0.6s ease-out;
}

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.items-table {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    animation: slideInUp 0.6s ease-out;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.items-table thead {
    background: linear-gradient(135deg, #2d5016 0%, #4a7c2c 100%);
    color: white;
}

.items-table tbody tr {
    transition: all 0.3s ease;
}

.items-table tbody tr:hover {
    background: rgba(45, 80, 22, 0.05);
    transform: scale(1.01);
}

.total-section {
    background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
    border-radius: 12px;
    padding: 25px;
    margin-top: 30px;
    border: 2px solid #4caf50;
    animation: bounceIn 0.8s ease-out;
}

@keyframes bounceIn {
    0% {
        opacity: 0;
        transform: scale(0.3);
    }
    50% {
        transform: scale(1.05);
    }
    70% {
        transform: scale(0.9);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}

.payment-badge {
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

.divider {
    height: 2px;
    background: linear-gradient(to right, transparent, #dee2e6, transparent);
    margin: 20px 0;
}
</style>

<div class="receipt-view">
    <!-- Receipt Header -->
    <div class="receipt-header">
        <h2 class="mb-0"><i class="bi bi-receipt-cutoff me-2"></i>SALES RECEIPT</h2>
        <h4 class="mt-2 mb-0"><?php echo htmlspecialchars($sale['invoice_number']); ?></h4>
    </div>

    <div class="receipt-body">
        <!-- Sale Information -->
        <div class="info-section" style="border-left-color: #ffc107;">
            <h5 class="mb-3"><i class="bi bi-info-circle me-2 text-warning"></i>Transaction Details</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                        <span class="text-muted">Date & Time:</span>
                        <strong><?php echo date('M d, Y h:i A', strtotime($sale['created_at'])); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                        <span class="text-muted">Cashier:</span>
                        <strong><?php echo htmlspecialchars($sale['cashier']); ?></strong>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                        <span class="text-muted">Customer:</span>
                        <strong><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                        <span class="text-muted">Payment Method:</span>
                        <span class="payment-badge badge bg-<?php 
                            echo $sale['payment_method'] == 'cash' ? 'success' : 
                                 ($sale['payment_method'] == 'gcash' ? 'info' : 'primary'); 
                        ?> fs-6">
                            <?php echo strtoupper($sale['payment_method']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <?php if ($sale['customer_name']): ?>
            <div class="divider"></div>
            <div class="row">
                <div class="col-md-6">
                    <small class="text-muted"><i class="bi bi-telephone me-2"></i><?php echo htmlspecialchars($sale['contact']); ?></small>
                </div>
                <div class="col-md-6">
                    <small class="text-muted"><i class="bi bi-geo-alt me-2"></i><?php echo htmlspecialchars($sale['address']); ?></small>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Items Table -->
        <div class="table-responsive">
            <table class="table table-hover items-table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-end">Profit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $item_num = 1;
                    while ($item = mysqli_fetch_assoc($items_result)): 
                    ?>
                    <tr>
                        <td><strong><?php echo $item_num++; ?></strong></td>
                        <td>
                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                            <small class="text-muted">
                                <i class="bi bi-box me-1"></i>
                                <?php echo htmlspecialchars($item['size']); ?> <?php echo htmlspecialchars($item['unit']); ?>
                            </small>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary"><?php echo $item['quantity']; ?></span>
                        </td>
                        <td class="text-end">₱<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="text-end fw-bold">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                        <td class="text-end text-success">₱<?php echo number_format($item['subtotal_profit'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals Section -->
        <div class="total-section">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                        <span>Subtotal:</span>
                        <strong>₱<?php echo number_format($sale['total_amount'], 2); ?></strong>
                    </div>
                    <?php if ($sale['payment_method'] == 'cash'): ?>
                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                        <span>Amount Paid:</span>
                        <strong>₱<?php echo number_format($sale['amount_paid'], 2); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Change:</span>
                        <strong class="text-success">₱<?php echo number_format($change, 2); ?></strong>
                    </div>
                    <?php elseif ($sale['payment_method'] == 'gcash'): ?>
                    <div class="d-flex justify-content-between">
                        <span>Reference #:</span>
                        <strong><?php echo htmlspecialchars($sale['reference_number']); ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                        <span>Total Capital:</span>
                        <strong class="text-danger">₱<?php echo number_format($sale['total_capital'], 2); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                        <span>Total Profit:</span>
                        <strong class="text-success">₱<?php echo number_format($sale['total_profit'], 2); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Profit Margin:</span>
                        <strong class="text-info">
                            <?php echo number_format(($sale['total_profit'] / $sale['total_amount']) * 100, 1); ?>%
                        </strong>
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            <div class="text-center">
                <h3 class="mb-0">
                    <i class="bi bi-cash-coin me-2 text-success"></i>
                    TOTAL: <span class="text-success">₱<?php echo number_format($sale['total_amount'], 2); ?></span>
                </h3>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-4 text-muted">
            <small>
                <i class="bi bi-shield-check me-2"></i>
                This is an official receipt from Town Gas Store<br>
                Thank you for your business!
            </small>
        </div>

        <!-- Action Buttons -->
        <div class="mt-4 d-flex gap-2 justify-content-center">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="bi bi-printer me-2"></i>Print Receipt
            </button>
            <button onclick="downloadReceipt()" class="btn btn-success">
                <i class="bi bi-download me-2"></i>Download
            </button>
        </div>
    </div>
</div>

<script>
function downloadReceipt() {
    alert('Download feature - Connect to PDF generation library');
}
</script>