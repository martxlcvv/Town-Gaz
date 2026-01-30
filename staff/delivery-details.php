<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';

if (!isset($_GET['id'])) {
    echo '<div class="alert alert-danger">Invalid request</div>';
    exit;
}

$delivery_id = (int)$_GET['id'];

// Get delivery details with history
$sql = "SELECT d.*, 
        s.invoice_number, s.total_amount, s.created_at as order_date,
        c.customer_name, c.address, c.contact as phone,
        r.rider_name, r.contact as rider_phone,
        u.full_name as processed_by
        FROM deliveries d
        JOIN sales s ON d.sale_id = s.sale_id
        LEFT JOIN customers c ON d.customer_id = c.customer_id
        LEFT JOIN riders r ON d.rider_id = r.rider_id
        LEFT JOIN users u ON s.user_id = u.user_id
        WHERE d.delivery_id = $delivery_id";
$result = mysqli_query($conn, $sql);
$delivery = mysqli_fetch_assoc($result);

if (!$delivery) {
    echo '<div class="alert alert-danger">Delivery not found</div>';
    exit;
}

// Get sale items
$items_sql = "SELECT si.*, p.product_name, p.size, p.unit
              FROM sale_items si
              JOIN products p ON si.product_id = p.product_id
              WHERE si.sale_id = {$delivery['sale_id']}";
$items_result = mysqli_query($conn, $items_sql);

// Get delivery status history
$history_sql = "SELECT * FROM delivery_history 
                WHERE delivery_id = $delivery_id 
                ORDER BY created_at ASC";
$history_result = mysqli_query($conn, $history_sql);

// Timeline statuses
$timeline = [
    'pending' => ['icon' => 'clock', 'label' => 'Order Placed', 'color' => 'warning', 'desc' => 'Order received and awaiting assignment'],
    'assigned' => ['icon' => 'person-check', 'label' => 'Rider Assigned', 'color' => 'info', 'desc' => 'Delivery assigned to rider'],
    'picked_up' => ['icon' => 'box-seam', 'label' => 'Package Picked Up', 'color' => 'primary', 'desc' => 'Rider has collected the package'],
    'in_transit' => ['icon' => 'truck', 'label' => 'Out for Delivery', 'color' => 'primary', 'desc' => 'Package is on the way to customer'],
    'delivered' => ['icon' => 'check-circle', 'label' => 'Delivered', 'color' => 'success', 'desc' => 'Successfully delivered to customer'],
    'cancelled' => ['icon' => 'x-circle', 'label' => 'Cancelled', 'color' => 'danger', 'desc' => 'Delivery has been cancelled']
];

$current_status = $delivery['delivery_status'];
$statuses = ['pending', 'assigned', 'picked_up', 'in_transit', 'delivered'];
$current_index = array_search($current_status, $statuses);

// Build history array from database
$status_history = [];
if (mysqli_num_rows($history_result) > 0) {
    while ($hist = mysqli_fetch_assoc($history_result)) {
        $status_history[$hist['status']] = $hist['created_at'];
    }
}
?>

<style>
/* Enhanced Timeline Styles */
.timeline-track {
    position: relative;
    padding-left: 60px;
    padding-top: 10px;
}

.timeline-step {
    position: relative;
    padding-bottom: 40px;
    opacity: 0;
    animation: slideInUp 0.6s ease-out forwards;
}

.timeline-step:nth-child(1) { animation-delay: 0.1s; }
.timeline-step:nth-child(2) { animation-delay: 0.2s; }
.timeline-step:nth-child(3) { animation-delay: 0.3s; }
.timeline-step:nth-child(4) { animation-delay: 0.4s; }
.timeline-step:nth-child(5) { animation-delay: 0.5s; }

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

.timeline-step:before {
    content: '';
    position: absolute;
    left: -45px;
    top: 5px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 4px solid #e9ecef;
    background: white;
    z-index: 2;
    transition: all 0.4s ease;
    box-shadow: 0 0 0 0 rgba(0, 123, 255, 0);
}

.timeline-step.completed:before {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border-color: #28a745;
    transform: scale(1.1);
    box-shadow: 0 0 0 4px rgba(40, 167, 69, 0.2);
}

.timeline-step.active:before {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border-color: #007bff;
    animation: pulse 2s ease-in-out infinite;
    box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.3);
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.3);
    }
    50% {
        transform: scale(1.15);
        box-shadow: 0 0 0 8px rgba(0, 123, 255, 0.1);
    }
}

.timeline-step:after {
    content: '';
    position: absolute;
    left: -37px;
    top: 29px;
    width: 4px;
    height: calc(100% - 24px);
    background: linear-gradient(to bottom, #e9ecef 0%, #dee2e6 100%);
    z-index: 1;
    transition: all 0.4s ease;
}

.timeline-step.completed:after {
    background: linear-gradient(to bottom, #28a745 0%, #20c997 100%);
    box-shadow: 0 0 8px rgba(40, 167, 69, 0.3);
}

.timeline-step:last-child:after {
    display: none;
}

/* Info Cards */
.info-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    border-left: 4px solid #007bff;
    transition: all 0.3s ease;
    animation: fadeIn 0.6s ease-out;
}

.info-card:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.info-card.customer { border-left-color: #17a2b8; }
.info-card.rider { border-left-color: #28a745; }
.info-card.order { border-left-color: #ffc107; }

.info-row {
    display: flex;
    padding: 8px 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: #6c757d;
    min-width: 120px;
}

.info-value {
    color: #212529;
    flex: 1;
}

/* Status Badge Enhancement */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    animation: bounceIn 0.6s ease-out;
}

@keyframes bounceIn {
    0% { transform: scale(0.3); opacity: 0; }
    50% { transform: scale(1.05); }
    70% { transform: scale(0.9); }
    100% { transform: scale(1); opacity: 1; }
}

/* Items Table Enhancement */
.items-table {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    animation: fadeIn 0.8s ease-out;
}

.items-table thead {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.items-table tbody tr {
    transition: all 0.3s ease;
}

.items-table tbody tr:hover {
    background: rgba(102, 126, 234, 0.05);
    transform: scale(1.01);
}

/* Timeline Step Content */
.timeline-content {
    background: white;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
}

.timeline-content:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    transform: translateY(-2px);
}

.timeline-step.completed .timeline-content {
    border-left: 3px solid #28a745;
}

.timeline-step.active .timeline-content {
    border-left: 3px solid #007bff;
    animation: glow 2s ease-in-out infinite;
}

@keyframes glow {
    0%, 100% {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08), 0 0 0 0 rgba(0, 123, 255, 0);
    }
    50% {
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12), 0 0 20px rgba(0, 123, 255, 0.3);
    }
}

/* Updated Info Alert */
.update-info {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    border-radius: 10px;
    padding: 15px;
    border-left: 4px solid #2196f3;
    animation: slideInRight 0.6s ease-out;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Section Headers */
.section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 12px;
    margin-bottom: 20px;
    border-bottom: 2px solid #e9ecef;
    font-weight: 600;
    color: #495057;
}

.section-header i {
    color: #007bff;
    font-size: 1.2em;
}

/* Rider Card Special */
.rider-card {
    background: linear-gradient(135deg, #d4fc79 0%, #96e6a1 100%);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
    animation: fadeIn 0.6s ease-out;
}

.rider-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    font-weight: bold;
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

/* Responsive */
@media (max-width: 768px) {
    .timeline-track {
        padding-left: 45px;
    }
    
    .timeline-step:before {
        left: -35px;
        width: 20px;
        height: 20px;
    }
    
    .timeline-step:after {
        left: -29px;
    }
}
</style>

<div class="row">
    <!-- Left Column -->
    <div class="col-md-6">
        <!-- Order Information -->
        <div class="section-header">
            <i class="bi bi-receipt"></i>
            <span>Order Information</span>
        </div>
        <div class="info-card order">
            <div class="info-row">
                <div class="info-label"><i class="bi bi-hash me-2"></i>Invoice #:</div>
                <div class="info-value fw-bold"><?php echo htmlspecialchars($delivery['invoice_number']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label"><i class="bi bi-calendar-event me-2"></i>Order Date:</div>
                <div class="info-value"><?php echo date('M d, Y h:i A', strtotime($delivery['order_date'])); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label"><i class="bi bi-cash me-2"></i>Total Amount:</div>
                <div class="info-value text-success fw-bold fs-5">₱<?php echo number_format($delivery['total_amount'], 2); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label"><i class="bi bi-person-circle me-2"></i>Processed By:</div>
                <div class="info-value"><?php echo htmlspecialchars($delivery['processed_by']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label"><i class="bi bi-flag me-2"></i>Status:</div>
                <div class="info-value">
                    <span class="status-badge bg-<?php echo $timeline[$current_status]['color']; ?> text-white">
                        <i class="bi bi-<?php echo $timeline[$current_status]['icon']; ?>"></i>
                        <?php echo ucwords(str_replace('_', ' ', $current_status)); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Customer Details -->
        <div class="section-header">
            <i class="bi bi-person"></i>
            <span>Customer Details</span>
        </div>
        <div class="info-card customer">
            <div class="info-row">
                <div class="info-label"><i class="bi bi-person-fill me-2"></i>Name:</div>
                <div class="info-value fw-bold"><?php echo htmlspecialchars($delivery['customer_name']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label"><i class="bi bi-telephone me-2"></i>Phone:</div>
                <div class="info-value">
                    <a href="tel:<?php echo htmlspecialchars($delivery['phone']); ?>" class="text-decoration-none">
                        <?php echo htmlspecialchars($delivery['phone']); ?>
                    </a>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label"><i class="bi bi-geo-alt me-2"></i>Address:</div>
                <div class="info-value"><?php echo htmlspecialchars($delivery['address']); ?></div>
            </div>
        </div>

        <!-- Rider Information -->
        <?php if ($delivery['rider_name']): ?>
        <div class="section-header">
            <i class="bi bi-person-badge"></i>
            <span>Rider Information</span>
        </div>
        <div class="rider-card">
            <div class="d-flex align-items-center gap-3">
                <div class="rider-avatar">
                    <?php echo strtoupper(substr($delivery['rider_name'], 0, 1)); ?>
                </div>
                <div class="flex-grow-1">
                    <h5 class="mb-1 text-success fw-bold"><?php echo htmlspecialchars($delivery['rider_name']); ?></h5>
                    <?php if ($delivery['rider_phone']): ?>
                    <p class="mb-0">
                        <i class="bi bi-telephone-fill me-2"></i>
                        <a href="tel:<?php echo htmlspecialchars($delivery['rider_phone']); ?>" class="text-dark text-decoration-none">
                            <?php echo htmlspecialchars($delivery['rider_phone']); ?>
                        </a>
                    </p>
                    <?php endif; ?>
                </div>
                <div>
                    <i class="bi bi-person-check-fill text-success" style="font-size: 2em;"></i>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>No Rider Assigned</strong>
            <p class="mb-0 small">This delivery is waiting for rider assignment.</p>
        </div>
        <?php endif; ?>

        <!-- Items List -->
        <div class="section-header mt-4">
            <i class="bi bi-box-seam"></i>
            <span>Order Items</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover items-table mb-0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $item_count = 0;
                    while ($item = mysqli_fetch_assoc($items_result)): 
                        $item_count++;
                    ?>
                    <tr style="animation-delay: <?php echo $item_count * 0.1; ?>s;">
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
                        <td class="text-end fw-bold text-success">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr class="table-active">
                        <th colspan="3" class="text-end">Grand Total:</th>
                        <th class="text-end text-success fs-5">₱<?php echo number_format($delivery['total_amount'], 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Right Column - Timeline -->
    <div class="col-md-6">
        <div class="section-header">
            <i class="bi bi-clock-history"></i>
            <span>Delivery Timeline</span>
        </div>
        
        <div class="timeline-track">
            <?php 
            foreach ($statuses as $status_key):
                if ($status_key == 'cancelled') continue;
                
                $is_completed = ($current_status == 'delivered' || 
                                ($current_index !== false && array_search($status_key, $statuses) < $current_index));
                $is_active = ($current_status == $status_key);
                $status_info = $timeline[$status_key];
                
                $timestamp = null;
                if (isset($status_history[$status_key])) {
                    $timestamp = $status_history[$status_key];
                } elseif ($status_key == 'pending') {
                    $timestamp = $delivery['created_at'];
                }
            ?>
                <div class="timeline-step <?php echo $is_completed ? 'completed' : ($is_active ? 'active' : ''); ?>">
                    <div class="timeline-content">
                        <div class="d-flex align-items-start justify-content-between mb-2">
                            <div class="flex-grow-1">
                                <h6 class="mb-1 fw-bold">
                                    <i class="bi bi-<?php echo $status_info['icon']; ?> me-2 text-<?php echo $status_info['color']; ?>"></i>
                                    <?php echo $status_info['label']; ?>
                                </h6>
                                <p class="mb-0 small text-muted"><?php echo $status_info['desc']; ?></p>
                            </div>
                            <?php if ($is_completed): ?>
                                <span class="badge bg-success rounded-pill">
                                    <i class="bi bi-check-lg"></i> Done
                                </span>
                            <?php elseif ($is_active): ?>
                                <span class="badge bg-primary rounded-pill">
                                    <i class="bi bi-arrow-right"></i> Current
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary rounded-pill">
                                    <i class="bi bi-hourglass-split"></i> Pending
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($timestamp && ($is_completed || $is_active)): ?>
                            <div class="mt-2 pt-2 border-top">
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>
                                    <?php echo date('M d, Y h:i A', strtotime($timestamp)); ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($current_status == 'cancelled'): ?>
                <div class="timeline-step active">
                    <div class="timeline-content border-danger">
                        <div class="d-flex align-items-start justify-content-between mb-2">
                            <div class="flex-grow-1">
                                <h6 class="mb-1 fw-bold text-danger">
                                    <i class="bi bi-x-circle me-2"></i>
                                    Delivery Cancelled
                                </h6>
                                <p class="mb-0 small text-muted"><?php echo $timeline['cancelled']['desc']; ?></p>
                            </div>
                            <span class="badge bg-danger rounded-pill">
                                <i class="bi bi-x-lg"></i> Cancelled
                            </span>
                        </div>
                        <div class="mt-2 pt-2 border-top">
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i>
                                <?php echo date('M d, Y h:i A', strtotime($delivery['updated_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="update-info mt-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-info-circle fs-4 me-3 text-primary"></i>
                <div>
                    <small class="fw-bold d-block">Last Updated</small>
                    <small class="text-muted">
                        <?php echo date('l, M d, Y \a\t h:i A', strtotime($delivery['updated_at'])); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Add loading animation effect
document.addEventListener('DOMContentLoaded', function() {
    // Animate info cards on load
    const infoCards = document.querySelectorAll('.info-card');
    infoCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
});
</script>