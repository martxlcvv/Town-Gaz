<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_admin();
prevent_cache();

$page_title = "Reports & Analytics";

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$filter_payment = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$filter_product = isset($_GET['product_id']) ? $_GET['product_id'] : '';

// Build WHERE clause for summary
$where_conditions = ["s.status = 'completed'", "DATE(s.created_at) BETWEEN '$start_date' AND '$end_date'"];

if ($filter_payment) {
    $where_conditions[] = "s.payment_method = '" . mysqli_real_escape_string($conn, $filter_payment) . "'";
}

$where_clause = implode(' AND ', $where_conditions);

// Summary Statistics
$summary_sql = "SELECT 
                COUNT(DISTINCT s.sale_id) as total_transactions,
                IFNULL(SUM(s.total_amount), 0) as total_sales,
                IFNULL(SUM(s.total_capital), 0) as total_capital,
                IFNULL(SUM(s.total_profit), 0) as total_profit,
                IFNULL(AVG(s.total_amount), 0) as avg_transaction
                FROM sales s
                WHERE $where_clause";
$summary_result = mysqli_query($conn, $summary_sql);
$summary = mysqli_fetch_assoc($summary_result);

// Payment Method Breakdown
$payment_sql = "SELECT payment_method, 
                COUNT(*) as count,
                SUM(total_amount) as total
                FROM sales
                WHERE status = 'completed' AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'
                GROUP BY payment_method";
$payment_result = mysqli_query($conn, $payment_sql);

// Best Selling Products
$best_products_where = ["s.status = 'completed'", "DATE(s.created_at) BETWEEN '$start_date' AND '$end_date'"];

if ($filter_payment) {
    $best_products_where[] = "s.payment_method = '" . mysqli_real_escape_string($conn, $filter_payment) . "'";
}

if ($filter_product) {
    $best_products_where[] = "si.product_id = " . intval($filter_product);
}

$best_products_clause = implode(' AND ', $best_products_where);

$best_products_sql = "SELECT p.product_name, 
                      SUM(si.quantity) as total_sold,
                      SUM(si.subtotal) as total_revenue
                      FROM sale_items si
                      JOIN products p ON si.product_id = p.product_id
                      JOIN sales s ON si.sale_id = s.sale_id
                      WHERE $best_products_clause
                      GROUP BY si.product_id
                      ORDER BY total_sold DESC
                      LIMIT 10";
$best_products_result = mysqli_query($conn, $best_products_sql);

// Daily Sales Trend
$daily_sales_sql = "SELECT DATE(created_at) as date,
                    COUNT(*) as transactions,
                    SUM(total_amount) as daily_sales,
                    SUM(total_profit) as daily_profit
                    FROM sales
                    WHERE status = 'completed' AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'
                    GROUP BY DATE(created_at)
                    ORDER BY date";
$daily_sales_result = mysqli_query($conn, $daily_sales_sql);

$daily_data = [];
while ($day = mysqli_fetch_assoc($daily_sales_result)) {
    $daily_data[] = $day;
}

// Get all products for filter
$products_sql = "SELECT * FROM products WHERE status = 'active' ORDER BY product_name";
$products_result = mysqli_query($conn, $products_sql);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

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

/* Reports Page Styling */
.stat-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 25px rgba(0,0,0,0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-size: 1.8rem;
}

.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
}

.card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    color: #2c3e50;
    border: none;
    border-radius: 0;
    padding: 1.25rem;
    font-weight: 700;
}

.chart-container {
    position: relative;
    height: 350px;
    padding: 1rem;
}

/* Print Styles */
@media print {
    .sidebar,
    .mobile-menu-toggle,
    .sidebar-overlay,
    .card-header button,
    form button {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    
    .card {
        box-shadow: none;
        border: 1px solid #dee2e6;
        page-break-inside: avoid;
    }
}

/* Responsive Styles */
@media (max-width: 768px) {
    .stat-card {
        margin-bottom: 1rem;
        padding: 1rem;
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }
    
    .stat-card h4 {
        font-size: 1.3rem;
    }
    
    .stat-card h6 {
        font-size: 0.85rem;
    }
    
    .chart-container {
        height: 250px;
        padding: 0.5rem;
    }
    
    .table-responsive {
        font-size: 0.85rem;
    }
    
    .card-header {
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }
}

@media (max-width: 576px) {
    .stat-card h4 {
        font-size: 1.1rem;
    }
    
    .stat-card h6 {
        font-size: 0.75rem;
    }
    
    .chart-container {
        height: 200px;
    }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <h1 class="animate-slide-in">
                    <i class="bi bi-bar-chart me-2"></i>Sales Reports & Analytics
                </h1>
                <p class="mb-0 animate-fade-in">
                    Period: <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?>
                </p>
            </div>
            <div class="header-actions">
                <div class="real-time-indicator animate-fade-in">
                    <div class="pulse-dot"></div>
                    <span>Live Updates</span>
                </div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-funnel me-2"></i>Filters
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" 
                               value="<?php echo $start_date; ?>" required>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" 
                               value="<?php echo $end_date; ?>" required>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="">All Methods</option>
                            <option value="cash" <?php echo $filter_payment == 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="gcash" <?php echo $filter_payment == 'gcash' ? 'selected' : ''; ?>>GCash</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label">Product</label>
                        <select name="product_id" class="form-select">
                            <option value="">All Products</option>
                            <?php while ($product = mysqli_fetch_assoc($products_result)): ?>
                                <option value="<?php echo $product['product_id']; ?>" 
                                        <?php echo $filter_product == $product['product_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($product['product_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-2"></i>Apply Filters
                        </button>
                        <a href="reports.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-2"></i>Reset
                        </a>
                        <button type="button" class="btn btn-success" onclick="window.print()">
                            <i class="bi bi-printer me-2"></i>Print Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6 col-sm-6">
                <div class="stat-card" style="border-left-color: #0d6efd;">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Sales</h6>
                            <h4 class="mb-0">₱<?php echo number_format($summary['total_sales'], 2); ?></h4>
                            <small class="text-muted"><?php echo $summary['total_transactions']; ?> transactions</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 col-sm-6">
                <div class="stat-card" style="border-left-color: #dc3545;">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                            <i class="bi bi-wallet2"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Capital</h6>
                            <h4 class="mb-0">₱<?php echo number_format($summary['total_capital'], 2); ?></h4>
                            <small class="text-muted">Cost of goods</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 col-sm-6">
                <div class="stat-card" style="border-left-color: #198754;">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Profit</h6>
                            <h4 class="mb-0">₱<?php echo number_format($summary['total_profit'], 2); ?></h4>
                            <small class="text-success">
                                <?php 
                                $profit_margin = $summary['total_sales'] > 0 ? 
                                    ($summary['total_profit'] / $summary['total_sales']) * 100 : 0;
                                echo number_format($profit_margin, 1); 
                                ?>% margin
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 col-sm-6">
                <div class="stat-card" style="border-left-color: #0dcaf0;">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Avg Transaction</h6>
                            <h4 class="mb-0">₱<?php echo number_format($summary['avg_transaction'], 2); ?></h4>
                            <small class="text-muted">Per sale</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Sales Trend Chart -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-graph-up me-2"></i>Daily Sales Trend
                    </div>
                    <div class="card-body">
                        <?php if (count($daily_data) > 0): ?>
                            <div class="chart-container">
                                <canvas id="salesTrendChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-graph-up display-1"></i>
                                <p class="mt-3">No sales data available for the selected period</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Payment Methods Chart -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-pie-chart me-2"></i>Payment Methods
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($payment_result) > 0): ?>
                            <div style="height: 250px; display: flex; align-items: center; justify-content: center;">
                                <canvas id="paymentChart"></canvas>
                            </div>
                            <div class="mt-3">
                                <?php 
                                mysqli_data_seek($payment_result, 0);
                                while ($payment = mysqli_fetch_assoc($payment_result)): 
                                ?>
                                    <div class="d-flex justify-content-between mb-2 p-2 bg-light rounded">
                                        <span class="fw-semibold"><?php echo strtoupper($payment['payment_method']); ?>:</span>
                                        <strong class="text-success">₱<?php echo number_format($payment['total'], 2); ?></strong>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-pie-chart display-1"></i>
                                <p class="mt-3">No payment data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Best Selling Products -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-trophy me-2"></i>Best Selling Products
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Product</th>
                                        <th>Quantity Sold</th>
                                        <th>Revenue</th>
                                        <th class="d-none d-md-table-cell">Performance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if (mysqli_num_rows($best_products_result) > 0):
                                        $rank = 1;
                                        $max_sold = 0;
                                        $products_array = [];
                                        
                                        while ($product = mysqli_fetch_assoc($best_products_result)) {
                                            if ($rank == 1) $max_sold = $product['total_sold'];
                                            $products_array[] = $product;
                                        }
                                        
                                        foreach ($products_array as $product):
                                            $percentage = $max_sold > 0 ? ($product['total_sold'] / $max_sold) * 100 : 0;
                                    ?>
                                        <tr>
                                            <td>
                                                <?php if ($rank == 1): ?>
                                                    <i class="bi bi-trophy-fill text-warning fs-5"></i>
                                                <?php elseif ($rank == 2): ?>
                                                    <i class="bi bi-trophy-fill text-secondary fs-5"></i>
                                                <?php elseif ($rank == 3): ?>
                                                    <i class="bi bi-trophy-fill fs-5" style="color: #CD7F32;"></i>
                                                <?php else: ?>
                                                    <span class="fw-bold"><?php echo $rank; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($product['product_name']); ?></strong></td>
                                            <td><?php echo number_format($product['total_sold']); ?> units</td>
                                            <td class="fw-bold text-success">₱<?php echo number_format($product['total_revenue'], 2); ?></td>
                                            <td class="d-none d-md-table-cell">
                                                <div class="progress" style="height: 25px;">
                                                    <div class="progress-bar bg-success" 
                                                         role="progressbar" 
                                                         style="width: <?php echo $percentage; ?>%">
                                                        <?php echo number_format($percentage, 0); ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php 
                                            $rank++;
                                        endforeach;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                <i class="bi bi-inbox display-4 d-block mb-3"></i>
                                                No product sales data for the selected period
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Check if Chart.js is loaded
if (typeof Chart === 'undefined') {
    console.error('Chart.js library failed to load');
} else {
    console.log('Chart.js loaded successfully');
}

// Sales Trend Chart
<?php if (count($daily_data) > 0): ?>
const salesDates = <?php echo json_encode(array_column($daily_data, 'date')); ?>;
const salesAmounts = <?php echo json_encode(array_map('floatval', array_column($daily_data, 'daily_sales'))); ?>;
const profitAmounts = <?php echo json_encode(array_map('floatval', array_column($daily_data, 'daily_profit'))); ?>;

console.log('Sales Data:', { salesDates, salesAmounts, profitAmounts });

const ctxTrend = document.getElementById('salesTrendChart');
if (ctxTrend) {
    const salesTrendChart = new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: salesDates.map(date => {
                const d = new Date(date);
                return d.toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
            }),
            datasets: [{
                label: 'Sales (₱)',
                data: salesAmounts,
                borderColor: '#4a7c2c',
                backgroundColor: 'rgba(74, 124, 44, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 3,
                pointRadius: 5,
                pointHoverRadius: 7,
                pointBackgroundColor: '#4a7c2c',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }, {
                label: 'Profit (₱)',
                data: profitAmounts,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 3,
                pointRadius: 5,
                pointHoverRadius: 7,
                pointBackgroundColor: '#28a745',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            size: window.innerWidth <= 768 ? 11 : 13,
                            weight: '600'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    },
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += '₱' + context.parsed.y.toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: window.innerWidth <= 768 ? 10 : 12
                        },
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: window.innerWidth <= 768 ? 10 : 12
                        }
                    }
                }
            }
        }
    });
    
    console.log('Sales Trend Chart created successfully');
} else {
    console.error('Canvas element for sales trend chart not found');
}
<?php endif; ?>

// Payment Methods Chart
<?php
if (mysqli_num_rows($payment_result) > 0):
    mysqli_data_seek($payment_result, 0);
    $payment_labels = [];
    $payment_amounts = [];
    $payment_colors = [
        'cash' => '#28a745',
        'gcash' => '#17a2b8',
        'bank' => '#007bff'
    ];
    $chart_colors = [];

    while ($payment = mysqli_fetch_assoc($payment_result)) {
        $payment_labels[] = strtoupper($payment['payment_method']);
        $payment_amounts[] = floatval($payment['total']);
        $chart_colors[] = $payment_colors[$payment['payment_method']] ?? '#6c757d';
    }
?>

const paymentLabels = <?php echo json_encode($payment_labels); ?>;
const paymentAmounts = <?php echo json_encode($payment_amounts); ?>;
const chartColors = <?php echo json_encode($chart_colors); ?>;

console.log('Payment Data:', { paymentLabels, paymentAmounts, chartColors });

const ctxPayment = document.getElementById('paymentChart');
if (ctxPayment) {
    const paymentChart = new Chart(ctxPayment, {
        type: 'doughnut',
        data: {
            labels: paymentLabels,
            datasets: [{
                data: paymentAmounts,
                backgroundColor: chartColors,
                borderWidth: 3,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: {
                            size: 12,
                            weight: '600'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += '₱' + context.parsed.toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                            
                            // Calculate percentage
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            label += ' (' + percentage + '%)';
                            
                            return label;
                        }
                    }
                }
            }
        }
    });
    
    console.log('Payment Chart created successfully');
} else {
    console.error('Canvas element for payment chart not found');
}
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>