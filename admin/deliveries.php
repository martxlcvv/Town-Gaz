<?php
// Regular page load
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_admin();
prevent_cache();

$page_title = "Deliveries";

// Get deliveries separated by type
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

// Get history
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_type = isset($_GET['filter']) ? mysqli_real_escape_string($conn, $_GET['filter']) : 'all';
$search_date = isset($_GET['search_date']) ? mysqli_real_escape_string($conn, $_GET['search_date']) : '';

$history_sql = "SELECT d.*, 
                s.invoice_number, s.total_amount, s.payment_method, s.created_at as sale_date,
                c.customer_name, c.address, c.contact as customer_phone,
                r.rider_name, r.contact as rider_phone,
                u.full_name as cashier
                FROM deliveries d
                JOIN sales s ON d.sale_id = s.sale_id
                LEFT JOIN customers c ON s.customer_id = c.customer_id
                LEFT JOIN riders r ON d.rider_id = r.rider_id
                LEFT JOIN users u ON s.user_id = u.user_id
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
/* Dashboard Header Styles - compact */
.dashboard-header {
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

.header-content h1 {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    color: #ffffff;
    letter-spacing: 0.3px;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
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

                            <?php if ($item['rider_name']): ?>
                                <div class="bg-gradient-to-r from-emerald-800 to-emerald-900 p-3 rounded-lg shadow-md border-2 border-yellow-400">
                                    <p class="text-xs font-bold text-yellow-300 uppercase tracking-wide mb-1 flex items-center gap-1">
                                        <i class="bi bi-person-check-fill"></i> Rider Assigned
                                    </p>
                                    <p class="text-yellow-400 font-bold text-sm md:text-base flex items-center gap-2">
                                        <i class="bi bi-bicycle"></i>
                                        <?php echo htmlspecialchars($item['rider_name']); ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <div class="flex flex-col gap-2 mt-2">
                                <button onclick="viewOrderDetails(<?php echo $item['sale_id']; ?>)" class="w-full px-3 py-2.5 bg-gradient-to-r from-green-600 to-green-700 text-white text-sm font-bold rounded-lg hover:from-green-700 hover:to-green-800 transition shadow-md flex items-center justify-center gap-2">
                                    <i class="bi bi-eye-fill"></i>
                                    <span>View Details</span>
                                </button>
                            </div>
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
                    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition border-t-4 border-t-blue-500">
                        <div class="bg-gradient-to-r from-blue-50 to-blue-100 p-3 md:p-4 border-b">
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
                                <div class="bg-gradient-to-r from-emerald-800 to-emerald-900 p-3 rounded-lg shadow-md border-2 border-yellow-400">
                                    <p class="text-xs font-bold text-yellow-300 uppercase tracking-wide mb-1 flex items-center gap-1">
                                        <i class="bi bi-person-check-fill"></i> Rider Assigned
                                    </p>
                                    <p class="text-yellow-400 font-bold text-sm md:text-base flex items-center gap-2">
                                        <i class="bi bi-bicycle"></i>
                                        <?php echo htmlspecialchars($item['rider_name']); ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <div class="flex flex-col gap-2 mt-2">
                                <button onclick="assignRider(<?php echo $item['delivery_id']; ?>)" class="w-full px-3 py-2.5 bg-gradient-to-r from-blue-900 to-blue-800 text-white text-sm font-bold rounded-lg hover:from-blue-800 hover:to-blue-700 transition shadow-md flex items-center justify-center gap-2">
                                    <i class="bi bi-person-plus-fill"></i>
                                    <span>Assign Rider</span>
                                </button>
                                <button onclick="viewOrderDetails(<?php echo $item['sale_id']; ?>)" class="w-full px-3 py-2.5 bg-gradient-to-r from-green-600 to-green-700 text-white text-sm font-bold rounded-lg hover:from-green-700 hover:to-green-800 transition shadow-md flex items-center justify-center gap-2">
                                    <i class="bi bi-eye-fill"></i>
                                    <span>View Details</span>
                                </button>
                            </div>
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
                        <tr class="border-b border-gray-300 bg-gray-50">
                            <th class="px-2 md:px-3 py-2 text-left font-semibold text-gray-700 text-xs md:text-sm">Invoice</th>
                            <th class="px-2 md:px-3 py-2 text-left font-semibold text-gray-700 text-xs md:text-sm">Customer</th>
                            <th class="px-2 md:px-3 py-2 text-left font-semibold text-gray-700 hidden sm:table-cell text-xs md:text-sm">Type</th>
                            <th class="px-2 md:px-3 py-2 text-left font-semibold text-gray-700 hidden md:table-cell text-xs md:text-sm">Date</th>
                            <th class="px-2 md:px-3 py-2 text-right font-semibold text-gray-700 text-xs md:text-sm">Amount</th>
                            <th class="px-2 md:px-3 py-2 text-center font-semibold text-gray-700 text-xs md:text-sm">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($history_result) > 0) {
                            while ($row = mysqli_fetch_assoc($history_result)): 
                        ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="px-2 md:px-3 py-1.5 font-medium text-gray-900 text-xs md:text-sm"><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                                <td class="px-2 md:px-3 py-1.5 text-gray-700 text-xs md:text-sm">
                                    <?php echo htmlspecialchars($row['customer_name'] ?? 'Walk-In'); ?>
                                </td>
                                <td class="px-2 md:px-3 py-1.5 hidden sm:table-cell">
                                    <?php if ($row['customer_name'] == NULL): ?>
                                        <span class="inline-block px-2 py-1 text-xs font-semibold text-red-700 bg-red-100 rounded">Walk In</span>
                                    <?php else: ?>
                                        <span class="inline-block px-2 py-1 text-xs font-semibold text-blue-700 bg-blue-100 rounded">Delivery</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-2 md:px-3 py-1.5 text-gray-700 text-xs md:text-sm hidden md:table-cell">
                                    <?php echo date('M d, Y', strtotime($row['sale_date'])); ?>
                                </td>
                                <td class="px-2 md:px-3 py-1.5 text-right font-bold text-gray-900 text-xs md:text-sm">
                                    ₱<?php echo number_format($row['total_amount'], 2); ?>
                                </td>
                                <td class="px-2 md:px-3 py-1.5 text-center">
                                    <button onclick="viewOrderDetails(<?php echo $row['sale_id']; ?>)" class="px-3 py-1.5 text-xs font-bold text-white bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 active:from-green-800 active:to-green-900 rounded-md transition whitespace-nowrap shadow-md">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php 
                            endwhile;
                        } else {
                        ?>
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                    <i class="bi bi-inbox text-2xl text-gray-300"></i>
                                    <p class="mt-1 text-sm">No orders found</p>
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
<div id="detailsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-start justify-center pt-4 overflow-y-auto">
    <div class="bg-white rounded-lg max-w-md w-full shadow-lg">
        <div class="sticky top-0 bg-gradient-to-r from-teal-700 to-teal-600 px-4 py-3 border-b flex justify-between items-center z-10">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="bi bi-receipt"></i>Order Details
            </h3>
            <button onclick="closeModal()" class="bg-red-500 hover:bg-red-600 text-white text-3xl font-bold leading-none w-9 h-9 rounded-full flex items-center justify-center transition shadow-lg" title="Close">×</button>
        </div>
        <div id="modalContent" class="p-4 max-h-[calc(100vh-150px)] overflow-y-auto"></div>
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
    fetch(`../staff/get-order-details.php?sale_id=${saleId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('modalContent').innerHTML = html;
            document.getElementById('detailsModal').classList.remove('hidden');
            // Scroll modal to top
            document.getElementById('detailsModal').scrollTop = 0;
        })
        .catch(err => {
            Swal.fire('Error', 'Failed to load order details', 'error');
        });
}

function closeModal() {
    document.getElementById('detailsModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('detailsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

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
            
            // Show loading
            Swal.fire({
                title: 'Assigning Rider...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('ajax-assign-rider.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `assign_rider=1&delivery_id=${deliveryId}&rider_id=${riderId}`
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response ok:', response.ok);
                
                // Try to get response text first to see what's actually returned
                return response.text().then(text => {
                    console.log('Response text:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        throw new Error('Server returned invalid JSON: ' + text.substring(0, 100));
                    }
                });
            })
            .then(data => {
                console.log('Parsed data:', data);
                if (data.success) {
                    Swal.fire('Success', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', data.message || 'Failed to assign rider', 'error');
                }
            })
            .catch(err => {
                console.error('Fetch error:', err);
                Swal.fire('Error', 'Failed to assign rider: ' + err.message, 'error');
            });
        }
    });
}

function updateStatus(deliveryId, currentStatus) {
    const statusOptions = {
        'pending': 'Pending - Awaiting assignment',
        'assigned': 'Assigned - Rider assigned',
        'picked_up': 'Picked Up - Package collected',
        'in_transit': 'In Transit - On the way',
        'delivered': 'Delivered - Successfully delivered',
        'completed': 'Completed - Order complete',
        'cancelled': 'Cancelled - Delivery cancelled'
    };
    
    let statusSelect = '<option value="">Select new status...</option>';
    for (const [status, label] of Object.entries(statusOptions)) {
        statusSelect += `<option value="${status}" ${status === currentStatus ? 'selected' : ''}>${label}</option>`;
    }
    
    Swal.fire({
        title: 'Update Status',
        html: `
            <select id="statusSelect" class="swal2-input" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%; font-size: 0.95rem;">
                ${statusSelect}
            </select>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Update',
        confirmButtonColor: '#15803d',
        cancelButtonText: 'Cancel'
    }).then(result => {
        if (result.isConfirmed) {
            const status = document.getElementById('statusSelect').value;
            if (!status) {
                Swal.fire('Error', 'Please select a status', 'error');
                return;
            }
            
            fetch('ajax-assign-rider.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `update_status=1&delivery_id=${deliveryId}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success', data.message, 'success').then(() => {
                        location.reload();
                    });
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Failed to update status', 'error');
            });
        }
    });
}
</script>

    </div>
</div>

<?php
include '../includes/footer.php';
?>