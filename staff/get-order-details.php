<?php
session_start();
require_once '../config/database.php';

if (!isset($_GET['sale_id'])) {
    echo '<div class="p-4 text-center"><p class="text-red-600">Invalid request</p></div>';
    exit;
}

$sale_id = intval($_GET['sale_id']);

// Get order details with all related info
$query = "
    SELECT 
        s.sale_id,
        s.invoice_number,
        s.total_amount,
        s.payment_method,
        s.created_at,
        c.customer_name,
        c.contact as customer_phone,
        d.delivery_address,
        r.rider_name,
        r.contact as rider_phone,
        u.full_name as cashier_name,
        d.delivery_status
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.customer_id
    LEFT JOIN deliveries d ON s.sale_id = d.sale_id
    LEFT JOIN riders r ON d.rider_id = r.rider_id
    LEFT JOIN users u ON s.user_id = u.user_id
    WHERE s.sale_id = $sale_id
    LIMIT 1
";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    echo '<div class="p-4 text-center"><p class="text-red-600">Order not found</p></div>';
    exit;
}

$order = mysqli_fetch_assoc($result);

// Get items in this order
$items_query = "
    SELECT 
        si.sale_item_id,
        si.quantity,
        si.unit_price,
        si.subtotal,
        p.product_name,
        p.product_code
    FROM sale_items si
    JOIN products p ON si.product_id = p.product_id
    WHERE si.sale_id = $sale_id
    ORDER BY si.sale_item_id ASC
";

$items_result = mysqli_query($conn, $items_query);
?>

<div class="space-y-4">
    <!-- Header Info -->
    <div class="grid grid-cols-2 gap-4 pb-4 border-b">
        <div>
            <p class="text-xs font-semibold text-gray-600 uppercase">Invoice</p>
            <p class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($order['invoice_number']); ?></p>
        </div>
        <div>
            <p class="text-xs font-semibold text-gray-600 uppercase">Date & Time</p>
            <p class="text-sm text-gray-900"><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></p>
        </div>
    </div>

    <!-- Customer Info -->
    <div class="bg-blue-50 p-3 rounded border border-blue-200">
        <p class="text-xs font-semibold text-blue-700 uppercase mb-1">Customer</p>
        <p class="text-sm font-medium text-gray-900">
            <?php echo htmlspecialchars($order['customer_name'] ?? 'Walk-In Customer'); ?>
        </p>
        <?php if ($order['customer_phone']): ?>
            <p class="text-xs text-gray-600 mt-1"><i class="bi bi-telephone mr-1"></i><?php echo htmlspecialchars($order['customer_phone']); ?></p>
        <?php endif; ?>
    </div>

    <!-- Delivery Address (if delivery order) -->
    <?php if ($order['delivery_address']): ?>
    <div class="bg-green-50 p-3 rounded border border-green-200">
        <p class="text-xs font-semibold text-green-700 uppercase mb-1">Delivery Address</p>
        <p class="text-sm text-gray-900"><?php echo htmlspecialchars($order['delivery_address']); ?></p>
    </div>
    <?php endif; ?>

    <!-- Rider Info (if assigned) -->
    <?php if ($order['rider_name']): ?>
    <div class="bg-purple-50 p-3 rounded border border-purple-200">
        <p class="text-xs font-semibold text-purple-700 uppercase mb-1">Rider Assigned</p>
        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($order['rider_name']); ?></p>
        <?php if ($order['rider_phone']): ?>
            <p class="text-xs text-gray-600 mt-1"><i class="bi bi-telephone mr-1"></i><?php echo htmlspecialchars($order['rider_phone']); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Items Ordered -->
    <div>
        <p class="text-xs font-semibold text-gray-600 uppercase mb-2 flex items-center gap-1">
            <i class="bi bi-basket"></i>Items Ordered
        </p>
        <div class="space-y-2 bg-gray-50 p-3 rounded">
            <?php 
            $item_count = 0;
            while ($item = mysqli_fetch_assoc($items_result)): 
                $item_count++;
            ?>
                <div class="flex justify-between items-start text-sm pb-2 <?php echo ($item_count < mysqli_num_rows($items_result)) ? 'border-b' : ''; ?>">
                    <div>
                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($item['product_name']); ?></p>
                        <p class="text-xs text-gray-600"><?php echo htmlspecialchars($item['product_code']); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-gray-900 font-medium">
                            <?php echo $item['quantity']; ?>x @ ₱<?php echo number_format($item['unit_price'], 2); ?>
                        </p>
                        <p class="text-xs text-gray-600">₱<?php echo number_format($item['subtotal'], 2); ?></p>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Summary -->
    <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-4 rounded border border-gray-200 space-y-2">
        <div class="flex justify-between text-sm">
            <span class="text-gray-700 font-semibold">Total Amount:</span>
            <span class="text-gray-900 font-bold text-lg">₱<?php echo number_format($order['total_amount'], 2); ?></span>
        </div>
        <div class="flex justify-between text-sm">
            <span class="text-gray-700 font-semibold">Payment Method:</span>
            <span class="text-gray-900 font-medium"><?php echo ucfirst($order['payment_method']); ?></span>
        </div>
        <?php if ($order['delivery_status']): ?>
        <div class="flex justify-between text-sm">
            <span class="text-gray-700 font-semibold">Status:</span>
            <span class="inline-block px-2 py-1 text-xs font-semibold rounded 
                <?php 
                    $status_color = [
                        'pending' => 'bg-yellow-100 text-yellow-700',
                        'completed' => 'bg-green-100 text-green-700',
                        'delivered' => 'bg-green-100 text-green-700',
                        'in_transit' => 'bg-blue-100 text-blue-700'
                    ];
                    echo $status_color[$order['delivery_status']] ?? 'bg-gray-100 text-gray-700';
                ?>">
                <?php echo ucwords(str_replace('_', ' ', $order['delivery_status'])); ?>
            </span>
        </div>
        <?php endif; ?>
        <div class="flex justify-between text-sm pt-2 border-t">
            <span class="text-gray-700 font-semibold">Cashier:</span>
            <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($order['cashier_name']); ?></span>
        </div>
    </div>
</div>
