<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_admin();
prevent_cache();

$page_title = "Dashboard";

// Get today's date
$today = date('Y-m-d');
$current_month = date('Y-m');
$current_year = date('Y');

// Ensure all active products have inventory records for today
$ensure_inv_sql = "INSERT IGNORE INTO inventory (product_id, date, opening_stock, current_stock, closing_stock)
                   SELECT product_id, '$today', 0, 0, 0 
                   FROM products 
                   WHERE status = 'active'
                   AND product_id NOT IN (
                     SELECT product_id FROM inventory WHERE date = '$today'
                   )";
mysqli_query($conn, $ensure_inv_sql);

// Get real-time stats
$stats_sql = "SELECT 
    (SELECT IFNULL(SUM(total_amount), 0) FROM sales WHERE DATE(created_at) = ? AND status = 'completed') as today_sales,
    (SELECT IFNULL(SUM(total_profit), 0) FROM sales WHERE DATE(created_at) = ? AND status = 'completed') as today_profit,
    (SELECT COUNT(*) FROM sales WHERE DATE(created_at) = ? AND status = 'completed') as today_transactions,
    (SELECT IFNULL(SUM(total_amount), 0) FROM sales WHERE DATE_FORMAT(created_at, '%Y-%m') = ? AND status = 'completed') as month_sales,
    (SELECT COUNT(*) FROM customers WHERE status = 'active') as total_customers,
    (SELECT COUNT(*) FROM deliveries WHERE delivery_status = 'pending') as pending_deliveries";
    
$stmt = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stmt, "ssss", $today, $today, $today, $current_month);
mysqli_stmt_execute($stmt);
$stats_result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($stats_result);
mysqli_stmt_close($stmt);

// Get yesterday's sales for comparison
$yesterday = date('Y-m-d', strtotime('-1 day'));
$yesterday_sales_sql = "SELECT IFNULL(SUM(total_amount), 0) as yesterday_sales 
                       FROM sales 
                       WHERE DATE(created_at) = ? AND status = 'completed'";
$stmt = mysqli_prepare($conn, $yesterday_sales_sql);
mysqli_stmt_bind_param($stmt, "s", $yesterday);
mysqli_stmt_execute($stmt);
$yesterday_result = mysqli_stmt_get_result($stmt);
$yesterday_data = mysqli_fetch_assoc($yesterday_result);
mysqli_stmt_close($stmt);

// Calculate sales change percentage
$sales_change = 0;
if ($yesterday_data['yesterday_sales'] > 0) {
    $sales_change = (($stats['today_sales'] - $yesterday_data['yesterday_sales']) / $yesterday_data['yesterday_sales']) * 100;
}

// Get low stock items - from today's inventory only
$low_stock_sql = "SELECT p.product_id, p.product_name, p.image_path, p.size, p.unit, 
                         COALESCE(i.current_stock, 0) as current_stock
                  FROM products p
                  LEFT JOIN inventory i ON p.product_id = i.product_id AND i.date = ?
                  WHERE p.status = 'active'
                  AND COALESCE(i.current_stock, 0) < 20
                  ORDER BY COALESCE(i.current_stock, 0) ASC 
                  LIMIT 5";
$stmt = mysqli_prepare($conn, $low_stock_sql);
mysqli_stmt_bind_param($stmt, "s", $today);
mysqli_stmt_execute($stmt);
$low_stock_result = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

// Get recent sales
$recent_sales_sql = "SELECT s.*, c.customer_name, u.full_name 
                    FROM sales s 
                    LEFT JOIN customers c ON s.customer_id = c.customer_id 
                    JOIN users u ON s.user_id = u.user_id 
                    WHERE s.status = 'completed'
                    ORDER BY s.created_at DESC 
                    LIMIT 10";
$recent_sales_result = mysqli_query($conn, $recent_sales_sql);

// Get top selling products with images
$top_products_sql = "SELECT p.product_id, p.product_name, p.image_path, p.size, p.unit,
                     SUM(si.quantity) as total_sold,
                     SUM(si.subtotal) as total_revenue
                     FROM sale_items si
                     JOIN products p ON si.product_id = p.product_id
                     JOIN sales s ON si.sale_id = s.sale_id
                     WHERE DATE_FORMAT(s.created_at, '%Y-%m') = ? 
                     AND s.status = 'completed'
                     GROUP BY p.product_id
                     ORDER BY total_sold DESC
                     LIMIT 5";
$stmt = mysqli_prepare($conn, $top_products_sql);
mysqli_stmt_bind_param($stmt, "s", $current_month);
mysqli_stmt_execute($stmt);
$top_products_result = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

// Get chart data for last 7 days
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $day_sql = "SELECT 
        IFNULL(SUM(total_amount), 0) as daily_sales,
        IFNULL(SUM(total_profit), 0) as daily_profit
        FROM sales 
        WHERE DATE(created_at) = ? AND status = 'completed'";
    $stmt = mysqli_prepare($conn, $day_sql);
    mysqli_stmt_bind_param($stmt, "s", $date);
    mysqli_stmt_execute($stmt);
    $day_result = mysqli_stmt_get_result($stmt);
    $day_data = mysqli_fetch_assoc($day_result);
    mysqli_stmt_close($stmt);
    
    $chart_data[] = [
        'date' => date('M d', strtotime($date)),
        'sales' => $day_data['daily_sales'],
        'profit' => $day_data['daily_profit']
    ];
}

// Get pie chart data - Monthly Sales by Category
$pie_data_sql = "SELECT 
    CASE 
        WHEN p.size LIKE '%kg%' THEN 'Large Tanks'
        WHEN p.size LIKE '%11%' THEN 'Medium Tanks'
        ELSE 'Small Tanks'
    END as category,
    SUM(si.subtotal) as total_sales
    FROM sale_items si
    JOIN products p ON si.product_id = p.product_id
    JOIN sales s ON si.sale_id = s.sale_id
    WHERE DATE_FORMAT(s.created_at, '%Y-%m') = ? 
    AND s.status = 'completed'
    GROUP BY category
    ORDER BY total_sales DESC";
$stmt = mysqli_prepare($conn, $pie_data_sql);
mysqli_stmt_bind_param($stmt, "s", $current_month);
mysqli_stmt_execute($stmt);
$pie_result = mysqli_stmt_get_result($stmt);
$pie_data = [];
$pie_total = 0;
while ($row = mysqli_fetch_assoc($pie_result)) {
    $pie_data[] = $row;
    $pie_total += $row['total_sales'];
}
mysqli_stmt_close($stmt);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Welcome Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <h1 class="animate-slide-in">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard Overview
                </h1>
                <p class="mb-0 animate-fade-in">
                    Welcome back, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>! 
                    Real-time insights for <?php echo date('F d, Y'); ?>
                </p>
            </div>
            <div class="header-actions">
                <div class="real-time-indicator animate-fade-in">
                    <div class="pulse-dot"></div>
                    <span>Live Updates</span>
                </div>
                <button class="btn btn-sm btn-outline-secondary animate-slide-in" onclick="refreshDashboard()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Stats Cards with Animated Numbers -->
        <div class="stats-grid">
            <!-- Today's Sales -->
            <div class="stat-card sales-card animate-scale-in" style="animation-delay: 0.1s">
                <div class="stat-content">
                    <div class="stat-icon">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number" id="todaySales" data-value="<?php echo $stats['today_sales']; ?>">
                            ₱<?php echo number_format($stats['today_sales'], 2); ?>
                        </div>
                        <div class="stat-label">Today's Sales</div>
                        <div class="stat-meta">
                            <?php if ($sales_change > 0): ?>
                                <span class="trend up">
                                    <i class="bi bi-arrow-up"></i><?php echo number_format(abs($sales_change), 1); ?>%
                                </span>
                            <?php elseif ($sales_change < 0): ?>
                                <span class="trend down">
                                    <i class="bi bi-arrow-down"></i><?php echo number_format(abs($sales_change), 1); ?>%
                                </span>
                            <?php else: ?>
                                <span class="trend neutral">
                                    <i class="bi bi-dash"></i>0%
                                </span>
                            <?php endif; ?>
                            <span class="transactions"><?php echo $stats['today_transactions']; ?> trans</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Profit -->
            <div class="stat-card profit-card animate-scale-in" style="animation-delay: 0.2s">
                <div class="stat-content">
                    <div class="stat-icon">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number" id="todayProfit" data-value="<?php echo $stats['today_profit']; ?>">
                            ₱<?php echo number_format($stats['today_profit'], 2); ?>
                        </div>
                        <div class="stat-label">Today's Profit</div>
                        <div class="stat-meta">
                            <?php 
                            $profit_margin = $stats['today_sales'] > 0 ? 
                                ($stats['today_profit'] / $stats['today_sales']) * 100 : 0;
                            ?>
                            <span class="margin"><?php echo number_format($profit_margin, 1); ?>% margin</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Sales -->
            <div class="stat-card monthly-card animate-scale-in" style="animation-delay: 0.3s">
                <div class="stat-content">
                    <div class="stat-icon">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number" id="monthlySales" data-value="<?php echo $stats['month_sales']; ?>">
                            ₱<?php echo number_format($stats['month_sales'], 2); ?>
                        </div>
                        <div class="stat-label">Monthly Sales</div>
                        <div class="stat-meta">
                            <span class="period"><?php echo date('F Y'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Customers -->
            <div class="stat-card customers-card animate-scale-in" style="animation-delay: 0.4s">
                <div class="stat-content">
                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number" id="activeCustomers" data-value="<?php echo $stats['total_customers']; ?>">
                            <?php echo number_format($stats['total_customers']); ?>
                        </div>
                        <div class="stat-label">Active Customers</div>
                        <div class="stat-meta">
                            <span class="pending"><?php echo $stats['pending_deliveries']; ?> pending</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row mb-4">
            <!-- Sales Trend Chart with Year Selector -->
            <div class="col-lg-8 mb-4">
                <div class="card chart-card animate-slide-up">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6><i class="bi bi-graph-up me-2"></i>Sales Trend Analysis</h6>
                            <div class="year-selector">
                                <select class="form-select form-select-sm" id="yearSelector">
                                    <?php for ($y = 2020; $y <= date('Y'); $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y == date('Y') ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pie Chart - Sales Distribution -->
            <div class="col-lg-4 mb-4">
                <div class="card chart-card animate-slide-up" style="animation-delay: 0.1s">
                    <div class="card-header">
                        <h6><i class="bi bi-pie-chart me-2"></i>Sales Distribution</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="height: 300px;">
                            <canvas id="pieChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Products & Low Stock -->
        <div class="row mb-4">
            <!-- Top Products with Images (Full Width) -->
            <div class="col-lg-8 mb-4">
                <div class="card animate-slide-up">
                    <div class="card-header">
                        <h6><i class="bi bi-fire me-2"></i>Top Selling Products This Month</h6>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($top_products_result) > 0): ?>
                            <div class="top-products-list">
                                <?php $rank = 1; while ($product = mysqli_fetch_assoc($top_products_result)): ?>
                                    <div class="product-item">
                                        <div class="product-rank rank-<?php echo $rank <= 3 ? $rank : 3; ?>"><?php echo $rank; ?></div>
                                        <div class="product-image">
                                            <?php if (!empty($product['image_path']) && file_exists('../' . $product['image_path'])): ?>
                                                <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" 
                                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                            <?php else: ?>
                                                <div class="default-image">
                                                    <i class="bi bi-droplet"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="product-info">
                                            <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($product['size']); ?> - <?php echo htmlspecialchars($product['unit']); ?></small>
                                        </div>
                                        <div class="product-stats">
                                            <div class="sales-count"><i class="bi bi-bag-check"></i> <span class="counter" data-count="<?php echo $product['total_sold']; ?>">0</span> units</div>
                                            <div class="sales-revenue">₱<?php echo number_format($product['total_revenue'], 0); ?></div>
                                        </div>
                                    </div>
                                <?php $rank++; endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-graph-up"></i>
                                <p>No sales data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Low Stock Alerts Notifications -->
            <div class="col-lg-4 mb-4">
                <div class="card animate-slide-up" style="animation-delay: 0.1s">
                    <div class="card-header">
                        <h6><i class="bi bi-exclamation-triangle me-2"></i>Low Stock Alerts</h6>
                    </div>
                    <div class="card-body">
                        <?php 
                        $low_stock_count = mysqli_num_rows($low_stock_result);
                        if ($low_stock_count > 0): 
                        ?>
                            <div class="alert-notification-summary">
                                <div class="alert-summary-icon">
                                    <i class="bi bi-exclamation-circle-fill"></i>
                                </div>
                                <div class="alert-summary-content">
                                    <div class="alert-summary-number"><?php echo $low_stock_count; ?></div>
                                    <div class="alert-summary-text">product<?php echo $low_stock_count !== 1 ? 's' : ''; ?> low on stock</div>
                                </div>
                            </div>
                            <div class="alert-details-list">
                                <?php while ($item = mysqli_fetch_assoc($low_stock_result)): ?>
                                    <div class="alert-detail-item">
                                        <span class="alert-detail-name"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                        <span class="alert-detail-stock"><?php echo $item['current_stock']; ?> units</span>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-check-circle-fill text-success"></i>
                                <p>All products have sufficient stock</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Sales -->
        <div class="row">
            <div class="col-12">
                <div class="card animate-slide-up" style="animation-delay: 0.2s">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6><i class="bi bi-clock-history me-2"></i>Recent Sales</h6>
                            <a href="sales.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover modern-table">
                                <thead>
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Profit</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($recent_sales_result) > 0): ?>
                                        <?php while ($sale = mysqli_fetch_assoc($recent_sales_result)): ?>
                                            <tr class="table-row-animate">
                                                <td><span class="badge bg-light text-dark">#<?php echo $sale['invoice_number']; ?></span></td>
                                                <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?></td>
                                                <td class="fw-semibold text-primary">₱<?php echo number_format($sale['total_amount'], 2); ?></td>
                                                <td class="fw-semibold text-success">₱<?php echo number_format($sale['total_profit'], 2); ?></td>
                                                <td><?php echo date('h:i A', strtotime($sale['created_at'])); ?></td>
                                                <td><span class="badge bg-success">Completed</span></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="bi bi-inbox"></i>
                                                <p class="mb-0">No recent sales</p>
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
    </div>
</div>

<!-- Include PIN Modal -->
<?php include '../includes/pin-modal.php'; ?>

<style>
/* Animation Keyframes */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideRight {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideLeft {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Animation Classes */
.animate-slide-in {
    animation: slideIn 0.6s ease-out;
}

.animate-fade-in {
    animation: fadeIn 0.8s ease-out;
}

.animate-scale-in {
    animation: scaleIn 0.5s ease-out;
    animation-fill-mode: both;
}

.animate-slide-up {
    animation: slideUp 0.6s ease-out;
    animation-fill-mode: both;
}

.animate-slide-right {
    animation: slideRight 0.5s ease-out;
    animation-fill-mode: both;
}

.animate-slide-left {
    animation: slideLeft 0.5s ease-out;
    animation-fill-mode: both;
}

/* Dashboard Specific Styles */
.dashboard-header {
    background: linear-gradient(135deg, #1a4d5c 0%, #0f3543 100%);
    border-radius: 12px;
    padding: 1.75rem 1.5rem 1.5rem 1.5rem;
    margin-bottom: 1.25rem;
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

.header-content strong {
    color: #22d3ee;
    font-weight: 700;
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
    background: #4ade80;
    border-radius: 50%;
    animation: dashboardPulse 2s infinite;
}

@keyframes dashboardPulse {
    0%, 100% {
        opacity: 1;
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.7);
    }
    50% {
        opacity: 0.8;
        transform: scale(1.2);
        box-shadow: 0 0 0 8px rgba(74, 222, 128, 0);
    }
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 14px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(26, 77, 92, 0.1);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-top: 4px solid;
    position: relative;
    overflow: hidden;
    border-left: 1px solid rgba(26, 77, 92, 0.1);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, transparent 0%, rgba(34, 211, 238, 0.08) 100%);
    border-radius: 0 0 0 100%;
}

.stat-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 40px rgba(26, 77, 92, 0.18);
}

.sales-card { 
    border-top-color: #3b82f6;
    background: linear-gradient(135deg, #ffffff 0%, #eff6ff 100%);
}
.profit-card { 
    border-top-color: #10b981;
    background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);
}
.monthly-card { 
    border-top-color: #8b5cf6;
    background: linear-gradient(135deg, #ffffff 0%, #faf5ff 100%);
}
.customers-card { 
    border-top-color: #f59e0b;
    background: linear-gradient(135deg, #ffffff 0%, #fffbf0 100%);
}

.stat-content {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.stat-icon {
    width: 70px;
    height: 70px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
}

.sales-card .stat-icon { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.profit-card .stat-icon { background: linear-gradient(135deg, #10b981, #059669); }
.monthly-card .stat-icon { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
.customers-card .stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }

.stat-details {
    flex: 1;
}

.stat-number {
    font-size: 1.85rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    background: linear-gradient(135deg, #1a4d5c, #0f3543);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    transition: all 0.3s ease;
    letter-spacing: -0.3px;
}

.stat-label {
    font-size: 0.875rem;
    color: #64748b;
    margin-bottom: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    font-weight: 700;
}

.stat-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 0.8rem;
}

.trend {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.85rem;
}

.trend.up {
    background: rgba(74, 222, 128, 0.15);
    color: #16a34a;
}

.trend.down {
    background: rgba(239, 68, 68, 0.15);
    color: #dc2626;
}

.trend.neutral {
    background: rgba(107, 114, 128, 0.15);
    color: #4b5563;
}

/* Chart Cards */
.chart-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(26, 77, 92, 0.1);
    transition: all 0.3s ease;
    border: 1px solid rgba(26, 77, 92, 0.05);
}

.chart-card:hover {
    box-shadow: 0 10px 30px rgba(26, 77, 92, 0.15);
}

.chart-card .card-header {
    border-bottom: 2px solid #f1f5f9;
    padding: 1.25rem;
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 12px 12px 0 0;
}

.chart-card .card-header h6 {
    margin: 0;
    font-weight: 700;
    color: #1a4d5c;
    font-size: 1.1rem;
}

/* Year Selector */
.year-selector select {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    padding: 0.375rem 2rem 0.375rem 0.75rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.year-selector select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

/* Chart Container */
.chart-container {
    position: relative;
    height: 320px;
}

/* Top Products List */
.top-products-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.product-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem;
    border-radius: 10px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.product-item:hover {
    background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
    border-color: #667eea;
    transform: translateX(5px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.15);
}

.product-rank {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1rem;
    box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
}

.product-rank.rank-1 {
    background: linear-gradient(135deg, #f39c12, #e67e22);
}

.product-rank.rank-2 {
    background: linear-gradient(135deg, #95a5a6, #7f8c8d);
}

.product-rank.rank-3 {
    background: linear-gradient(135deg, #d35400, #e67e22);
}

/* Alert Notifications */
.alerts-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.alert-notification {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem;
    border-radius: 8px;
    background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
    border-left: 4px solid #ffc107;
    transition: all 0.3s ease;
}

.alert-notification:hover {
    transform: translateX(-5px);
    box-shadow: 0 4px 12px rgba(255, 193, 7, 0.2);
}

.alert-badge {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #ffc107, #ff9800);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
}

.alert-content {
    flex: 1;
}

.alert-product {
    font-weight: 700;
    color: #2c3e50;
    font-size: 0.95rem;
    margin-bottom: 0.25rem;
}

.alert-stock {
    font-size: 0.85rem;
    color: #7f8c8d;
}

.alert-stock strong {
    color: #e74c3c;
    font-weight: 700;
}

.alert-summary {
    padding: 0.75rem 1rem;
    background: rgba(255, 193, 7, 0.1);
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.85rem;
    color: #7f8c8d;
    text-align: center;
}

.alert-summary i {
    color: #ffc107;
    font-size: 1rem;
}

/* Alert Notification Summary */
.alert-notification-summary {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 1.25rem;
    background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
    border-radius: 12px;
    border-left: 5px solid #ffc107;
    margin-bottom: 1rem;
    box-shadow: 0 4px 15px rgba(255, 193, 7, 0.2);
}

.alert-summary-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #ffc107, #ff9800);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
}

.alert-summary-content {
    flex: 1;
}

.alert-summary-number {
    font-size: 2rem;
    font-weight: 800;
    color: #d35400;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.alert-summary-text {
    font-size: 0.95rem;
    color: #7f8c8d;
    font-weight: 600;
}

/* Alert Details List */
.alert-details-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.alert-detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 3px solid #ffc107;
    transition: all 0.3s ease;
}

.alert-detail-item:hover {
    background: #fff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transform: translateX(3px);
}

.alert-detail-name {
    font-weight: 700;
    color: #2c3e50;
    flex: 1;
    font-size: 0.95rem;
}

.alert-detail-stock {
    background: #e74c3c;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
}

.product-image {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    overflow: hidden;
    background: white;
    border: 2px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-image .default-image {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    font-size: 1.5rem;
}

.product-info {
    flex: 1;
}

.product-name {
    font-weight: 700;
    margin-bottom: 0.125rem;
    color: #2c3e50;
}

.product-stats {
    text-align: right;
}

.sales-count {
    font-size: 0.875rem;
    color: #7f8c8d;
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.25rem;
}

.sales-count .counter {
    font-weight: 700;
    color: #667eea;
}

.sales-revenue {
    font-weight: 800;
    color: #27ae60;
    font-size: 1.1rem;
}

/* Alerts List */
.alerts-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.alert-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: 12px;
    background: linear-gradient(135deg, #fff8e6 0%, #ffecb3 100%);
    border: 2px solid #ffd54f;
    transition: all 0.3s ease;
}

.alert-item:hover {
    background: linear-gradient(135deg, #fff 0%, #fff8e6 100%);
    transform: translateX(-5px);
    box-shadow: 0 4px 15px rgba(255, 193, 7, 0.2);
}

.alert-icon {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #ffc107, #ff9800);
    color: white;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    box-shadow: 0 4px 10px rgba(255, 193, 7, 0.3);
}

.alert-info {
    flex: 1;
}

.alert-info div {
    font-weight: 700;
    color: #2c3e50;
}

.pulse-badge {
    animation: badgePulse 2s infinite;
}

@keyframes badgePulse {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
    }
    50% {
        box-shadow: 0 0 0 8px rgba(220, 53, 69, 0);
    }
}

/* Modern Table */
.modern-table {
    margin-bottom: 0;
}

.modern-table thead {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.modern-table thead th {
    border: none;
    padding: 0.85rem;
    font-weight: 700;
    color: #2c3e50;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.4px;
}

.modern-table tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid #f8f9fa;
}

.modern-table tbody tr:hover {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    transform: scale(1.01);
}

.table-row-animate {
    animation: slideUp 0.5s ease-out;
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #7f8c8d;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
    animation: pulse 2s infinite;
}

/* Responsive */
@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
        padding: 1.5rem;
        margin: 0 -20px 1.5rem -20px;
    }
    
    .header-content h1 {
        font-size: 1.5rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: space-between;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .product-item {
        flex-wrap: wrap;
    }
    
    .product-stats {
        width: 100%;
        text-align: left;
        margin-top: 0.5rem;
    }
}

/* Sidebar Collapse Responsive */
@media (min-width: 1201px) {
    .main-content {
        transition: margin-left 0.3s ease;
    }
    
    .main-content.expanded {
        margin-left: 70px;
    }
}

/* Spin Animation */
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.spin {
    animation: spin 1s linear infinite;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Animated Number Counter
class AnimatedCounter {
    constructor(element) {
        this.element = element;
        this.startValue = parseFloat(element.dataset.value) || 0;
        this.currentValue = this.startValue;
        this.targetValue = this.startValue;
        this.duration = 1500;
        this.startTime = null;
        this.isAnimating = false;
    }
    
    update(newValue) {
        this.targetValue = parseFloat(newValue) || 0;
        if (this.targetValue !== this.currentValue) {
            this.startTime = null;
            this.isAnimating = true;
            requestAnimationFrame(this.animate.bind(this));
        }
    }
    
    animate(timestamp) {
        if (!this.startTime) this.startTime = timestamp;
        const progress = Math.min((timestamp - this.startTime) / this.duration, 1);
        
        const easeOutQuart = 1 - Math.pow(1 - progress, 4);
        
        this.currentValue = this.startValue + (this.targetValue - this.startValue) * easeOutQuart;
        
        let formattedValue;
        if (this.element.id.includes('Sales') || this.element.id.includes('Profit')) {
            formattedValue = '₱' + Math.round(this.currentValue).toLocaleString();
        } else {
            formattedValue = Math.round(this.currentValue).toLocaleString();
        }
        
        this.element.textContent = formattedValue;
        
        if (progress < 1) {
            requestAnimationFrame(this.animate.bind(this));
        } else {
            this.startValue = this.targetValue;
            this.isAnimating = false;
            
            this.element.style.transform = 'scale(1.1)';
            this.element.style.background = 'linear-gradient(135deg, #667eea, #764ba2)';
            this.element.style.webkitBackgroundClip = 'text';
            this.element.style.webkitTextFillColor = 'transparent';
            setTimeout(() => {
                this.element.style.transform = 'scale(1)';
                this.element.style.background = 'linear-gradient(135deg, #2c3e50, #34495e)';
                this.element.style.webkitBackgroundClip = 'text';
            }, 300);
        }
    }
}

// Initialize counters
const counters = {};
document.querySelectorAll('.stat-number').forEach(element => {
    counters[element.id] = new AnimatedCounter(element);
});

// Counter animation for product sales count
document.addEventListener('DOMContentLoaded', function() {
    const productCounters = document.querySelectorAll('.sales-count .counter');
    productCounters.forEach((counter, index) => {
        setTimeout(() => {
            const target = parseInt(counter.dataset.count);
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    counter.textContent = target;
                    clearInterval(timer);
                } else {
                    counter.textContent = Math.floor(current);
                }
            }, 20);
        }, index * 100);
    });
});

// Refresh dashboard data
async function refreshDashboard() {
    const refreshBtn = event.target.closest('button');
    const originalHTML = refreshBtn.innerHTML;
    
    refreshBtn.disabled = true;
    refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise" style="animation: spin 1s linear infinite;"></i> Refreshing...';
    
    try {
        // Reload the page to get fresh data
        setTimeout(() => {
            location.reload();
        }, 500);
        
    } catch (error) {
        console.error('Error refreshing dashboard:', error);
        refreshBtn.innerHTML = '<i class="bi bi-exclamation-circle"></i> Error';
        refreshBtn.classList.remove('btn-outline-secondary');
        refreshBtn.classList.add('btn-danger');
        
        setTimeout(() => {
            refreshBtn.innerHTML = originalHTML;
            refreshBtn.disabled = false;
            refreshBtn.classList.remove('btn-danger');
            refreshBtn.classList.add('btn-outline-secondary');
        }, 2000);
    }
}

// Charts
let salesChart, pieChart;

document.addEventListener('DOMContentLoaded', function() {
    const chartData = <?php echo json_encode($chart_data); ?>;
    const pieData = <?php echo json_encode($pie_data); ?>;
    
    // Sales Trend Chart
    if (chartData.length > 0) {
        const ctx = document.getElementById('salesChart').getContext('2d');
        salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.map(d => d.date),
                datasets: [{
                    label: 'Sales (₱)',
                    data: chartData.map(d => d.sales),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }, {
                    label: 'Profit (₱)',
                    data: chartData.map(d => d.profit),
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#27ae60',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: {
                                weight: 'bold'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        cornerRadius: 8,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            },
                            font: {
                                weight: 'bold'
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                weight: 'bold'
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Pie Chart
    if (pieData.length > 0) {
        const ctxPie = document.getElementById('pieChart').getContext('2d');
        const pieTotal = <?php echo json_encode($pie_total); ?>;
        
        pieChart = new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: pieData.map(d => d.category),
                datasets: [{
                    data: pieData.map(d => d.total_sales),
                    backgroundColor: [
                        'rgba(52, 152, 219, 0.85)',
                        'rgba(155, 89, 182, 0.85)',
                        'rgba(46, 204, 113, 0.85)'
                    ],
                    borderColor: [
                        '#3498db',
                        '#9b59b6',
                        '#2ecc71'
                    ],
                    borderWidth: 3,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: {
                                weight: 'bold',
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.9)',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed;
                                const percentage = ((value / pieTotal) * 100).toFixed(1);
                                return '₱' + value.toLocaleString() + ' (' + percentage + '%)';
                            },
                            title: function(context) {
                                return context[0].label + ' Sales';
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Year selector change event
    document.getElementById('yearSelector')?.addEventListener('change', async function() {
        const year = this.value;
        // Here you would fetch new data for the selected year
        // For now, we'll just show a loading state
        this.disabled = true;
        setTimeout(() => {
            this.disabled = false;
            alert('Year data loading functionality would be implemented here');
        }, 500);
    });
});

// Add spin animation for refresh icon
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .spin {
        animation: spin 1s linear infinite;
    }
`;
document.head.appendChild(style);

// Auto-refresh every 60 seconds
let autoRefreshInterval = setInterval(refreshDashboard, 60000);

// Stop auto-refresh when page is hidden
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        clearInterval(autoRefreshInterval);
    } else {
        autoRefreshInterval = setInterval(refreshDashboard, 60000);
    }
});
</script>

<?php include '../includes/footer.php'; ?>