<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_staff();
prevent_cache();

$page_title = "Deliveries";

// Handle status update with AJAX
if (isset($_POST['update_status'])) {
    $delivery_id = (int)$_POST['delivery_id'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $sql = "UPDATE deliveries SET delivery_status = '$status', 
            updated_at = NOW() WHERE delivery_id = $delivery_id";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Delivery status updated successfully!']);
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating status']);
        exit();
    }
}

// Handle rider assignment
if (isset($_POST['assign_rider'])) {
    $delivery_id = (int)$_POST['delivery_id'];
    $rider_id = (int)$_POST['rider_id'];
    
    $sql = "UPDATE deliveries SET rider_id = $rider_id, 
            delivery_status = 'assigned', updated_at = NOW() 
            WHERE delivery_id = $delivery_id";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Rider assigned successfully!']);
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'Error assigning rider']);
        exit();
    }
}

// Get deliveries separated by type
// Walkin: where customer_id is NULL
$walkin_sql = "SELECT d.*, 
               s.invoice_number, s.total_amount,
               c.customer_name, c.address, c.contact as customer_phone,
               r.rider_name, r.contact as rider_phone
               FROM deliveries d
               JOIN sales s ON d.sale_id = s.sale_id
               LEFT JOIN customers c ON s.customer_id = c.customer_id
               LEFT JOIN riders r ON d.rider_id = r.rider_id
               WHERE s.customer_id IS NULL
               AND DATE(d.created_at) = CURDATE()
               ORDER BY d.delivery_status ASC, d.created_at DESC";
$walkin_result = mysqli_query($conn, $walkin_sql);

// Delivery: where customer_id is NOT NULL
$delivery_sql = "SELECT d.*, 
                 s.invoice_number, s.total_amount,
                 c.customer_name, c.address, c.contact as customer_phone,
                 r.rider_name, r.contact as rider_phone
                 FROM deliveries d
                 JOIN sales s ON d.sale_id = s.sale_id
                 LEFT JOIN customers c ON s.customer_id = c.customer_id
                 LEFT JOIN riders r ON d.rider_id = r.rider_id
                 WHERE s.customer_id IS NOT NULL
                 AND DATE(d.created_at) = CURDATE()
                 ORDER BY d.delivery_status ASC, d.created_at DESC";
$delivery_result = mysqli_query($conn, $delivery_sql);

// Get statistics
$stats_sql = "SELECT 
              SUM(CASE WHEN s.customer_id IS NULL THEN 1 ELSE 0 END) as walkin_total,
              SUM(CASE WHEN s.customer_id IS NULL AND d.delivery_status = 'completed' THEN 1 ELSE 0 END) as walkin_completed,
              SUM(CASE WHEN s.customer_id IS NOT NULL THEN 1 ELSE 0 END) as delivery_total,
              SUM(CASE WHEN s.customer_id IS NOT NULL AND d.delivery_status = 'delivered' THEN 1 ELSE 0 END) as delivery_delivered
              FROM deliveries d
              JOIN sales s ON d.sale_id = s.sale_id
              WHERE DATE(d.created_at) = CURDATE()";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get history - all orders from previous dates and today
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_type = isset($_GET['filter']) ? mysqli_real_escape_string($conn, $_GET['filter']) : 'all';
$search_date = isset($_GET['search_date']) ? mysqli_real_escape_string($conn, $_GET['search_date']) : '';

$history_sql = "SELECT d.*, 
                s.invoice_number, s.total_amount, s.payment_method, s.created_at as sale_date,
                c.customer_name, c.address, c.contact as customer_phone,
                r.rider_name, r.contact as rider_phone,
                u.full_name as cashier,
                GROUP_CONCAT(CONCAT(p.product_name, ' (', si.quantity, ')') SEPARATOR ', ') as items_list
                FROM deliveries d
                JOIN sales s ON d.sale_id = s.sale_id
                LEFT JOIN customers c ON s.customer_id = c.customer_id
                LEFT JOIN riders r ON d.rider_id = r.rider_id
                LEFT JOIN users u ON s.user_id = u.user_id
                LEFT JOIN sale_items si ON s.sale_id = si.sale_id
                LEFT JOIN products p ON si.product_id = p.product_id
                WHERE 1=1";

if ($filter_type == 'walkin') {
    $history_sql .= " AND s.customer_id IS NULL";
} elseif ($filter_type == 'delivery') {
    $history_sql .= " AND s.customer_id IS NOT NULL";
}

if ($search_query) {
    $history_sql .= " AND (c.customer_name LIKE '%$search_query%' OR s.invoice_number LIKE '%$search_query%')";
}

if ($search_date) {
    $history_sql .= " AND DATE(d.created_at) = '$search_date'";
}

$history_sql .= " GROUP BY d.delivery_id ORDER BY d.created_at DESC LIMIT 100";
$history_result = mysqli_query($conn, $history_sql);

// Get riders
$riders_sql = "SELECT * FROM riders WHERE status = 'active' ORDER BY rider_name";
$riders_result = mysqli_query($conn, $riders_sql);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.all.min.js"></script>

<style>
/* Dashboard Header Styles */
.dashboard-header {
    background: linear-gradient(135deg, #1a4d5c 0%, #0f3543 100%);
    border-radius: 12px;
    padding: 2rem 1.5rem 1.5rem 1.5rem;
    margin-bottom: 1.5rem;
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

.real-time-indicator {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.25rem;
    background: rgba(34, 211, 238, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    font-size: 0.875rem;
    color: #22d3ee;
    font-weight: 600;
    border: 1px solid rgba(34, 211, 238, 0.3);
}

.pulse-dot {
    width: 8px;
    height: 8px;
    background: #22d3ee;
    border-radius: 50%;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
        box-shadow: 0 0 0 0 rgba(34, 211, 238, 0.7);
    }
    50% {
        opacity: 0.7;
    }
    100% {
        opacity: 1;
        box-shadow: 0 0 0 8px rgba(34, 211, 238, 0);
    }
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

.animate-slide-in {
    animation: slideLeft 0.5s ease-out;
    animation-fill-mode: both;
}

.animate-fade-in {
    animation: fadeIn 0.8s ease-out;
    animation-fill-mode: both;
}

.me-2 {
    margin-right: 0.5rem;
}

.mb-0 {
    margin-bottom: 0;
}
</style>

<div class="main-content">
    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 p-3 sm:p-4 md:p-6">
        <div class="max-w-7xl mx-auto">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <h1 class="animate-slide-in">
                    <i class="bi bi-truck me-2"></i>Delivery Management
                </h1>
                <p class="mb-0 animate-fade-in">
                    Track walk-in pickups and delivery orders in real-time
                </p>
            </div>
            <div class="header-actions">
                <div class="real-time-indicator animate-fade-in">
                    <div class="pulse-dot"></div>
                    <span>Live Updates</span>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 md:gap-4 mb-6">
            <div class="bg-white rounded-lg shadow-sm p-4 md:p-6 border-t-4 border-red-500">
                <h3 class="text-xs md:text-sm font-semibold text-gray-600 uppercase tracking-wide">Walk In (Pick Up)</h3>
                <p class="text-3xl md:text-4xl font-bold text-gray-900 mt-2"><?php echo ($stats['walkin_total'] ?? 0); ?></p>
                <p class="text-xs md:text-sm text-gray-500 mt-1">
                    <strong><?php echo ($stats['walkin_completed'] ?? 0); ?></strong> completed today
                </p>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-4 md:p-6 border-t-4 border-blue-500">
                <h3 class="text-xs md:text-sm font-semibold text-gray-600 uppercase tracking-wide">Deliveries</h3>
                <p class="text-3xl md:text-4xl font-bold text-gray-900 mt-2"><?php echo ($stats['delivery_total'] ?? 0); ?></p>
                <p class="text-xs md:text-sm text-gray-500 mt-1">
                    <strong><?php echo ($stats['delivery_delivered'] ?? 0); ?></strong> delivered today
                </p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-t-lg shadow-sm border-b overflow-x-auto">
            <div class="flex border-b min-w-max md:min-w-0">
                <button class="section-tab-btn active px-3 md:px-6 py-3 md:py-4 font-semibold text-sm md:text-base text-gray-900 border-b-2 border-green-700 whitespace-nowrap" data-tab="walkin">
                    <i class="bi bi-person-check mr-2"></i>Walk In (Pick Up)
                </button>
                <button class="section-tab-btn px-3 md:px-6 py-3 md:py-4 font-semibold text-sm md:text-base text-gray-600 border-b-2 border-transparent hover:text-gray-900 whitespace-nowrap" data-tab="delivery">
                    <i class="bi bi-truck mr-2"></i>Deliveries
                </button>
            </div>
        </div>

        <!-- Walk In Section -->
        <div id="walkin-tab" class="tab-content bg-white shadow-sm rounded-b-lg p-3 md:p-6 mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4">
                <?php 
                if (mysqli_num_rows($walkin_result) > 0) {
                    while ($item = mysqli_fetch_assoc($walkin_result)): 
                ?>
                    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition">
                        <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-3 md:p-4 border-b">
                            <h4 class="font-semibold text-gray-900 text-sm md:text-base"><?php echo htmlspecialchars($item['invoice_number']); ?></h4>
                            <p class="text-xs md:text-sm text-gray-600">
                                <i class="bi bi-clock mr-1"></i><?php echo date('M d, Y h:i A', strtotime($item['created_at'])); ?>
                            </p>
                        </div>
                        
                        <div class="p-3 md:p-4 space-y-3">
                            <div>
                                <span class="inline-block px-2 md:px-3 py-1 text-xs font-semibold text-white bg-green-600 rounded-full">
                                    <?php echo ucwords(str_replace('_', ' ', $item['delivery_status'])); ?>
                                </span>
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-gray-600 uppercase">Customer</p>
                                <p class="text-sm md:text-base text-gray-900 font-medium">Walk-In</p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-gray-600 uppercase">Amount</p>
                                <p class="text-lg md:text-xl font-bold text-green-600">₱<?php echo number_format($item['total_amount'], 2); ?></p>
                            </div>

                            <button onclick="viewOrderDetails(<?php echo $item['sale_id']; ?>)" class="w-full mt-2 px-3 py-2 bg-green-700 text-white text-sm font-semibold rounded hover:bg-green-800 transition">
                                <i class="bi bi-eye mr-1"></i>View Details
                            </button>
                        </div>
                    </div>
                <?php 
                    endwhile;
                } else {
                ?>
                    <div class="col-span-full text-center py-8 md:py-12">
                        <i class="bi bi-inbox text-3xl md:text-4xl text-gray-300 mb-2"></i>
                        <h4 class="text-base md:text-lg font-semibold text-gray-500">No Walk-In Orders</h4>
                        <p class="text-sm md:text-base text-gray-400">No pending walk-in orders for today</p>
                    </div>
                <?php } ?>
            </div>
        </div>

        <!-- Delivery Section -->
        <div id="delivery-tab" class="tab-content hidden bg-white shadow-sm rounded-b-lg p-3 md:p-6 mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4">
                <?php 
                if (mysqli_num_rows($delivery_result) > 0) {
                    while ($item = mysqli_fetch_assoc($delivery_result)): 
                ?>
                    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition">
                        <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-3 md:p-4 border-b">
                            <h4 class="font-semibold text-gray-900 text-sm md:text-base"><?php echo htmlspecialchars($item['invoice_number']); ?></h4>
                            <p class="text-xs md:text-sm text-gray-600">
                                <i class="bi bi-clock mr-1"></i><?php echo date('M d, Y h:i A', strtotime($item['created_at'])); ?>
                            </p>
                        </div>
                        
                        <div class="p-3 md:p-4 space-y-3">
                            <div>
                                <span class="inline-block px-2 md:px-3 py-1 text-xs font-semibold text-white bg-blue-600 rounded-full">
                                    <?php echo ucwords(str_replace('_', ' ', $item['delivery_status'])); ?>
                                </span>
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-gray-600 uppercase">Customer</p>
                                <p class="text-sm md:text-base text-gray-900 font-medium"><?php echo htmlspecialchars($item['customer_name']); ?></p>
                                <?php if ($item['customer_phone']): ?>
                                    <p class="text-xs text-gray-600"><i class="bi bi-telephone mr-1"></i><?php echo htmlspecialchars($item['customer_phone']); ?></p>
                                <?php endif; ?>
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-gray-600 uppercase">Amount</p>
                                <p class="text-lg md:text-xl font-bold text-blue-600">₱<?php echo number_format($item['total_amount'], 2); ?></p>
                            </div>

                            <?php if ($item['rider_name']): ?>
                                <div class="bg-green-50 p-2 rounded text-sm">
                                    <p class="text-xs font-semibold text-green-700">Rider Assigned</p>
                                    <p class="text-green-900 font-medium text-xs md:text-sm"><?php echo htmlspecialchars($item['rider_name']); ?></p>
                                </div>
                            <?php else: ?>
                                <div class="bg-yellow-50 p-2 rounded text-sm">
                                    <p class="text-xs font-semibold text-yellow-700">No Rider Yet</p>
                                    <button onclick="assignRider(<?php echo $item['delivery_id']; ?>)" class="text-yellow-700 font-medium hover:underline mt-1 text-xs">
                                        Assign Rider
                                    </button>
                                </div>
                            <?php endif; ?>

                            <button onclick="viewOrderDetails(<?php echo $item['sale_id']; ?>)" class="w-full mt-2 px-3 py-2 bg-blue-700 text-white text-sm font-semibold rounded hover:bg-blue-800 transition">
                                <i class="bi bi-eye mr-1"></i>View Details
                            </button>
                        </div>
                    </div>
                <?php 
                    endwhile;
                } else {
                ?>
                    <div class="col-span-full text-center py-8 md:py-12">
                        <i class="bi bi-inbox text-3xl md:text-4xl text-gray-300 mb-2"></i>
                        <h4 class="text-base md:text-lg font-semibold text-gray-500">No Deliveries</h4>
                        <p class="text-sm md:text-base text-gray-400">No pending deliveries for today</p>
                    </div>
                <?php } ?>
            </div>
        </div>

        <!-- History Section -->
        <div class="bg-white rounded-lg shadow-sm p-3 md:p-6">
            <h2 class="text-xl md:text-2xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                <i class="bi bi-clock-history"></i>Order History
            </h2>

            <!-- Search and Filter -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-2 md:gap-4 mb-4 md:mb-6">
                <form method="GET" class="flex flex-col md:flex-row gap-2 col-span-1 md:col-span-3">
                    <input type="text" name="search" placeholder="Search customer/invoice..." value="<?php echo htmlspecialchars($search_query); ?>" class="flex-1 px-3 md:px-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-700">
                    <input type="date" name="search_date" value="<?php echo htmlspecialchars($search_date); ?>" class="px-3 md:px-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-700">
                    <select name="filter" class="px-3 md:px-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-700">
                        <option value="all">All Orders</option>
                        <option value="walkin" <?php echo ($filter_type == 'walkin') ? 'selected' : ''; ?>>Walk In Only</option>
                        <option value="delivery" <?php echo ($filter_type == 'delivery') ? 'selected' : ''; ?>>Deliveries Only</option>
                    </select>
                    <button type="submit" class="px-4 md:px-6 py-2 bg-green-700 text-white font-semibold text-sm rounded-lg hover:bg-green-800 transition whitespace-nowrap">
                        <i class="bi bi-search mr-1"></i>Search
                    </button>
                </form>
            </div>

            <!-- History Table - Responsive -->
            <div class="overflow-x-auto">
                <table class="w-full text-xs md:text-sm">
                    <thead>
                        <tr class="border-b-2 border-gray-200 bg-gray-50">
                            <th class="px-2 md:px-4 py-2 md:py-3 text-left font-semibold text-gray-700">Invoice</th>
                            <th class="px-2 md:px-4 py-2 md:py-3 text-left font-semibold text-gray-700">Customer</th>
                            <th class="px-2 md:px-4 py-2 md:py-3 text-left font-semibold text-gray-700 hidden sm:table-cell">Type</th>
                            <th class="px-2 md:px-4 py-2 md:py-3 text-left font-semibold text-gray-700 hidden md:table-cell">Date</th>
                            <th class="px-2 md:px-4 py-2 md:py-3 text-right font-semibold text-gray-700">Amount</th>
                            <th class="px-2 md:px-4 py-2 md:py-3 text-center font-semibold text-gray-700 hidden sm:table-cell">Status</th>
                            <th class="px-2 md:px-4 py-2 md:py-3 text-center font-semibold text-gray-700">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($history_result) > 0) {
                            while ($row = mysqli_fetch_assoc($history_result)): 
                        ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="px-2 md:px-4 py-2 md:py-3 font-medium text-gray-900 text-xs md:text-sm"><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                                <td class="px-2 md:px-4 py-2 md:py-3 text-gray-700 text-xs md:text-sm">
                                    <?php echo htmlspecialchars($row['customer_name'] ?? 'Walk-In'); ?>
                                </td>
                                <td class="px-2 md:px-4 py-2 md:py-3 hidden sm:table-cell">
                                    <?php if ($row['customer_name'] == NULL): ?>
                                        <span class="inline-block px-2 py-1 text-xs font-semibold text-red-700 bg-red-100 rounded">Walk In</span>
                                    <?php else: ?>
                                        <span class="inline-block px-2 py-1 text-xs font-semibold text-blue-700 bg-blue-100 rounded">Delivery</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-2 md:px-4 py-2 md:py-3 text-gray-700 text-xs md:text-sm hidden md:table-cell">
                                    <?php echo date('M d, Y', strtotime($row['sale_date'])); ?>
                                </td>
                                <td class="px-2 md:px-4 py-2 md:py-3 text-right font-bold text-gray-900 text-xs md:text-sm">
                                    ₱<?php echo number_format($row['total_amount'], 2); ?>
                                </td>
                                <td class="px-2 md:px-4 py-2 md:py-3 text-center hidden sm:table-cell">
                                    <?php 
                                    $status_color = [
                                        'pending' => 'bg-yellow-100 text-yellow-700',
                                        'completed' => 'bg-green-100 text-green-700',
                                        'delivered' => 'bg-green-100 text-green-700',
                                        'in_transit' => 'bg-blue-100 text-blue-700'
                                    ];
                                    $color = $status_color[$row['delivery_status']] ?? 'bg-gray-100 text-gray-700';
                                    ?>
                                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded <?php echo $color; ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $row['delivery_status'])); ?>
                                    </span>
                                </td>
                                <td class="px-2 md:px-4 py-2 md:py-3 text-center">
                                    <button onclick="viewOrderDetails(<?php echo $row['sale_id']; ?>)" class="px-2 md:px-3 py-1 text-xs md:text-sm font-semibold text-white bg-gray-700 rounded hover:bg-gray-800 transition whitespace-nowrap">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php 
                            endwhile;
                        } else {
                        ?>
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    <i class="bi bi-inbox text-2xl md:text-3xl text-gray-300"></i>
                                    <p class="mt-2 text-sm md:text-base">No orders found</p>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Order Details Modal -->
<div id="detailsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-2xl w-full max-h-96 overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-gray-50 to-gray-100 p-6 border-b flex justify-between items-center">
            <h3 class="text-xl font-bold text-gray-900">Order Details</h3>
            <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-2xl">×</button>
        </div>
        <div id="modalContent" class="p-6"></div>
    </div>
</div>

<script>
// Tab switching
document.querySelectorAll('.section-tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tab = this.dataset.tab;
        
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(t => t.classList.add('hidden'));
        
        // Show selected tab
        document.getElementById(tab + '-tab').classList.remove('hidden');
        
        // Update button styling
        document.querySelectorAll('.section-tab-btn').forEach(b => {
            b.classList.remove('text-gray-900', 'border-green-700');
            b.classList.add('text-gray-600', 'border-transparent');
        });
        this.classList.remove('text-gray-600', 'border-transparent');
        this.classList.add('text-gray-900', 'border-green-700');
    });
});

function viewOrderDetails(saleId) {
    fetch(`get-order-details.php?sale_id=${saleId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('modalContent').innerHTML = html;
            document.getElementById('detailsModal').classList.remove('hidden');
        })
        .catch(err => {
            Swal.fire('Error', 'Failed to load order details', 'error');
        });
}

function closeModal() {
    document.getElementById('detailsModal').classList.add('hidden');
}

function assignRider(deliveryId) {
    Swal.fire({
        title: 'Assign Rider',
        html: `
            <select id="riderSelect" class="swal2-input" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%; font-size: 0.95rem;">
                <option value="">Choose a rider...</option>
                <?php 
                mysqli_data_seek($riders_result, 0);
                while ($rider = mysqli_fetch_assoc($riders_result)): 
                ?>
                    <option value="<?php echo $rider['rider_id']; ?>">
                        <?php echo htmlspecialchars($rider['rider_name']); ?> - <?php echo htmlspecialchars($rider['contact']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Assign',
        confirmButtonColor: '#15803d',
        cancelButtonText: 'Cancel'
    }).then(result => {
        if (result.isConfirmed) {
            const riderId = document.getElementById('riderSelect').value;
            if (!riderId) {
                Swal.fire('Error', 'Please select a rider', 'error');
                return;
            }
            
            fetch('delivery.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `assign_rider=1&delivery_id=${deliveryId}&rider_id=${riderId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }
    });
}

// Auto-refresh every 30 seconds
setInterval(() => {
    location.reload();
}, 30000);

// Close modal when clicking outside
document.getElementById('detailsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});</script>

        </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>