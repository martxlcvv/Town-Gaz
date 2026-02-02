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

$ensure_inv_sql = "INSERT IGNORE INTO inventory (product_id, date, stock_in, stock_out, updated_by, created_at, updated_at)
                   SELECT product_id, '$today', 0, 0, 1, NOW(), NOW()
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

$low_stock_sql = "SELECT p.product_id, p.product_name, p.image_path, p.size, p.unit, 
                         COALESCE(SUM(i.stock_in) - SUM(i.stock_out), 0) as current_stock
                  FROM products p
                  LEFT JOIN inventory i ON p.product_id = i.product_id
                  WHERE p.status = 'active'
                  GROUP BY p.product_id
                  HAVING current_stock < 10
                  ORDER BY current_stock ASC 
                  LIMIT 5";
$low_stock_result = mysqli_query($conn, $low_stock_sql);

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

<!-- Sweet Alert Library -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.all.min.js"></script>

<style>
/* Dashboard Optimized Styles - Compact & Clean */
:root {
    --primary-teal: #1a4d5c;
    --secondary-teal: #0f3543;
    --accent-cyan: #22d3ee;
    --accent-green: #4ade80;
    --accent-blue: #3b82f6;
    --accent-orange: #f59e0b;
    --accent-red: #ef4444;
    --light-bg: #f8fafc;
    --card-bg: #ffffff;
    --text-dark: #1e293b;
    --text-light: #64748b;
}

body {
    background-color: var(--light-bg);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Compact Header */
.dashboard-header {
    background: linear-gradient(135deg, var(--primary-teal) 0%, var(--secondary-teal) 100%);
    border-radius: 10px;
    padding: 1.25rem 1.5rem;
    color: white;
    margin-bottom: 1rem;
    box-shadow: 0 4px 15px rgba(26, 77, 92, 0.2);
    border-left: 4px solid var(--accent-cyan);
}

.dashboard-header h1 {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.dashboard-header p {
    margin: 0;
    font-size: 0.9rem;
    opacity: 0.95;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.real-time-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: rgba(34, 211, 238, 0.15);
    border-radius: 8px;
    font-size: 0.85rem;
    color: #22d3ee;
    font-weight: 600;
}

.pulse-dot {
    width: 8px;
    height: 8px;
    background: #4ade80;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.2); }
}

/* Compact Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 1.25rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border-top: 3px solid;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
}

.stat-card.sales-card { border-top-color: #3b82f6; }
.stat-card.profit-card { border-top-color: #10b981; }
.stat-card.monthly-card { border-top-color: #8b5cf6; }
.stat-card.customers-card { border-top-color: #f59e0b; }

.stat-content {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    flex-shrink: 0;
}

.sales-card .stat-icon { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.profit-card .stat-icon { background: linear-gradient(135deg, #10b981, #059669); }
.monthly-card .stat-icon { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
.customers-card .stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }

.stat-details {
    flex: 1;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-light);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.stat-meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.8rem;
}

.trend {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-weight: 600;
}

.trend.up { background: rgba(74, 222, 128, 0.15); color: #16a34a; }
.trend.down { background: rgba(239, 68, 68, 0.15); color: #dc2626; }
.trend.neutral { background: rgba(107, 114, 128, 0.15); color: #4b5563; }

/* Compact Cards */
.card {
    border-radius: 10px;
    border: none;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    margin-bottom: 1rem;
}

.card-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 1px solid #e2e8f0;
    padding: 0.875rem 1rem;
    border-radius: 10px 10px 0 0 !important;
}

.card-header h6 {
    margin: 0;
    font-weight: 700;
    color: var(--text-dark);
    font-size: 0.95rem;
}

.card-body {
    padding: 1rem;
}

/* Chart Container */
.chart-container {
    position: relative;
    height: 280px;
}

/* Top Products List - Compact */
.top-products-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.product-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border-radius: 8px;
    background: #f8f9fa;
    transition: all 0.3s ease;
}

.product-item:hover {
    background: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transform: translateX(4px);
}

.product-rank {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
}

.product-rank.rank-1 { background: linear-gradient(135deg, #f39c12, #e67e22); }
.product-rank.rank-2 { background: linear-gradient(135deg, #95a5a6, #7f8c8d); }
.product-rank.rank-3 { background: linear-gradient(135deg, #d35400, #e67e22); }

.product-image {
    width: 45px;
    height: 45px;
    border-radius: 8px;
    overflow: hidden;
    background: white;
    border: 2px solid #e9ecef;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.default-image {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    font-size: 1.25rem;
}

.product-info {
    flex: 1;
    min-width: 0;
}

.product-name {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-dark);
    margin-bottom: 0.125rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-stats {
    text-align: right;
}

.sales-count {
    font-size: 0.8rem;
    color: var(--text-light);
    margin-bottom: 0.25rem;
}

.sales-count .counter {
    font-weight: 700;
    color: #667eea;
}

.sales-revenue {
    font-weight: 700;
    color: #27ae60;
    font-size: 0.95rem;
}

/* Low Stock Alert - Compact */
.alert-notification-summary {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
    border-radius: 10px;
    border-left: 4px solid #ffc107;
    margin-bottom: 0.75rem;
}

.alert-summary-icon {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #ffc107, #ff9800);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.alert-summary-number {
    font-size: 1.75rem;
    font-weight: 800;
    color: #d35400;
    line-height: 1;
}

.alert-summary-text {
    font-size: 0.85rem;
    color: #7f8c8d;
    font-weight: 600;
}

.alert-details-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.alert-detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.625rem;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 3px solid #ffc107;
}

.alert-detail-name {
    font-weight: 600;
    color: var(--text-dark);
    font-size: 0.85rem;
}

.alert-detail-stock {
    background: #e74c3c;
    color: white;
    padding: 0.25rem 0.625rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 700;
}

/* Modern Table - Compact */
.modern-table {
    font-size: 0.85rem;
}

.modern-table thead {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.modern-table th {
    padding: 0.75rem 0.5rem;
    font-weight: 700;
    color: var(--text-dark);
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.3px;
    border: none;
}

.modern-table td {
    padding: 0.75rem 0.5rem;
    border-bottom: 1px solid #f1f5f9;
}

.modern-table tbody tr:hover {
    background: #f8f9fa;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 2rem 1rem;
    color: var(--text-light);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 0.75rem;
    opacity: 0.5;
}

/* Responsive */
@media (max-width: 768px) {
    .dashboard-header {
        padding: 1rem;
    }
    
    .dashboard-header h1 {
        font-size: 1.5rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-number {
        font-size: 1.25rem;
    }
    
    .chart-container {
        height: 240px;
    }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h1><i class="bi bi-speedometer2 me-2"></i>Dashboard Overview</h1>
                    <p>Welcome back, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>! Real-time insights for <?php echo date('F d, Y'); ?></p>
                </div>
               
                    <button class="btn btn-sm btn-light" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card sales-card">
                <div class="stat-content">
                    <div class="stat-icon">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number">₱<?php echo number_format($stats['today_sales'], 2); ?></div>
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
                            <span class="text-muted"><?php echo $stats['today_transactions']; ?> trans</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stat-card profit-card">
                <div class="stat-content">
                    <div class="stat-icon">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number">₱<?php echo number_format($stats['today_profit'], 2); ?></div>
                        <div class="stat-label">Today's Profit</div>
                        <div class="stat-meta">
                            <?php 
                            $profit_margin = $stats['today_sales'] > 0 ? 
                                ($stats['today_profit'] / $stats['today_sales']) * 100 : 0;
                            ?>
                            <span class="text-muted"><?php echo number_format($profit_margin, 1); ?>% margin</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stat-card monthly-card">
                <div class="stat-content">
                    <div class="stat-icon">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number">₱<?php echo number_format($stats['month_sales'], 2); ?></div>
                        <div class="stat-label">Monthly Sales</div>
                        <div class="stat-meta">
                            <span class="text-muted"><?php echo date('F Y'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stat-card customers-card">
                <div class="stat-content">
                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($stats['total_customers']); ?></div>
                        <div class="stat-label">Active Customers</div>
                        <div class="stat-meta">
                            <span class="text-muted"><?php echo $stats['pending_deliveries']; ?> pending</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row">
            <!-- Sales Trend Chart -->
            <div class="col-lg-8 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="bi bi-graph-up me-2"></i>Sales Trend (Last 7 Days)</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pie Chart -->
            <div class="col-lg-4 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="bi bi-pie-chart me-2"></i>Sales Distribution</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="height: 250px;">
                            <canvas id="pieChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Products & Low Stock -->
        <div class="row">
            <!-- Top Products -->
            <div class="col-lg-8 mb-3">
                <div class="card">
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
                                            <small class="text-muted"><?php echo htmlspecialchars($product['size']); ?> <?php echo htmlspecialchars($product['unit']); ?></small>
                                        </div>
                                        <div class="product-stats">
                                            <div class="sales-count"><i class="bi bi-bag-check"></i> <span class="counter"><?php echo number_format($product['total_sold']); ?></span> units</div>
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

            <!-- Low Stock Alerts -->
            <div class="col-lg-4 mb-3">
                <div class="card">
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
                <div class="card">
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
                                            <tr>
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
                                            <td colspan="6" class="text-center py-4">
                                                <div class="empty-state">
                                                    <i class="bi bi-inbox"></i>
                                                    <p class="mb-0">No recent sales</p>
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
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Charts
document.addEventListener('DOMContentLoaded', function() {
    const chartData = <?php echo json_encode($chart_data); ?>;
    const pieData = <?php echo json_encode($pie_data); ?>;
    
    // Sales Trend Chart
    if (chartData.length > 0) {
        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.map(d => d.date),
                datasets: [{
                    label: 'Sales (₱)',
                    data: chartData.map(d => d.sales),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2
                }, {
                    label: 'Profit (₱)',
                    data: chartData.map(d => d.profit),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { usePointStyle: true, padding: 12 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
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
        new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: pieData.map(d => d.category),
                datasets: [{
                    data: pieData.map(d => d.total_sales),
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 12, usePointStyle: true }
                    }
                }
            }
        });
    }
});

// Show sweet alert on successful login
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('login') === 'success') {
        Swal.fire({
            icon: 'success',
            title: 'Welcome back!',
            text: 'You have been successfully logged in.',
            confirmButtonColor: '#2d5016',
            timer: 2500,
            showConfirmButton: false
        }).then(() => {
            // Clean URL
            window.history.replaceState({}, document.title, window.location.pathname);
        });
        
        // Reset login attempts on successful login
        localStorage.removeItem('login_attempts');
        localStorage.removeItem('login_lockout_time');
    }
});
</script>

<?php include '../includes/footer.php'; ?>