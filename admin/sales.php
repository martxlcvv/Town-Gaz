<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_admin();
prevent_cache();

$page_title = "Sales";

// Get filter parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$user_filter = isset($_GET['user_id']) ? $_GET['user_id'] : '';

// Build query
$where_clauses = ["s.status = 'completed'"];
$where_clauses[] = "DATE(s.created_at) BETWEEN '$date_from' AND '$date_to'";

if (!empty($user_filter)) {
    $where_clauses[] = "s.user_id = $user_filter";
}

$where_sql = implode(' AND ', $where_clauses);

// Get sales data with item count from sale_items
$sales_sql = "SELECT s.*, 
              c.customer_name, 
              u.full_name as staff_name,
              (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.sale_id) as item_count
              FROM sales s
              LEFT JOIN customers c ON s.customer_id = c.customer_id
              JOIN users u ON s.user_id = u.user_id
              WHERE $where_sql
              ORDER BY s.created_at DESC";
$sales_result = mysqli_query($conn, $sales_sql);

// Get summary - calculate total items from sale_items table
$summary_sql = "SELECT 
                COUNT(*) as total_transactions,
                IFNULL(SUM(s.total_amount), 0) as total_sales,
                IFNULL(SUM(s.total_profit), 0) as total_profit,
                (SELECT IFNULL(SUM(si.quantity), 0) 
                 FROM sale_items si 
                 JOIN sales s2 ON si.sale_id = s2.sale_id 
                 WHERE s2.status = 'completed' 
                 AND DATE(s2.created_at) BETWEEN '$date_from' AND '$date_to'
                 " . (!empty($user_filter) ? "AND s2.user_id = $user_filter" : "") . ") as total_items
                FROM sales s
                WHERE $where_sql";
$summary_result = mysqli_query($conn, $summary_sql);
$summary = mysqli_fetch_assoc($summary_result);

// Get daily sales for chart - FIXED to use s.status
$chart_sql = "SELECT DATE(s.created_at) as date, SUM(s.total_amount) as daily_sales
              FROM sales s
              WHERE $where_sql
              GROUP BY DATE(s.created_at)
              ORDER BY DATE(s.created_at)";
$chart_result = mysqli_query($conn, $chart_sql);
$chart_data = [];
while ($row = mysqli_fetch_assoc($chart_result)) {
    $chart_data[] = [
        'date' => date('M d', strtotime($row['date'])),
        'sales' => $row['daily_sales']
    ];
}

// Get users for filter
$users_sql = "SELECT user_id, full_name FROM users WHERE status = 'active' ORDER BY full_name";
$users_result = mysqli_query($conn, $users_sql);

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

/* Sales Page Styles */
:root {
    --primary-blue: #3498db;
    --primary-green: #27ae60;
    --primary-orange: #e67e22;
    --primary-red: #e74c3c;
    --primary-purple: #9b59b6;
    --primary-yellow: #f1c40f;
    --primary-teal: #1abc9c;
    --primary-gray: #95a5a6;
    --light-bg: #f8f9fa;
    --card-bg: #ffffff;
    --text-dark: #2c3e50;
    --text-light: #7f8c8d;
    --border-color: #e9ecef;
    --shadow-light: 0 2px 10px rgba(0,0,0,0.04);
    --shadow-medium: 0 4px 20px rgba(0,0,0,0.06);
}

body {
    background-color: var(--light-bg);
    color: var(--text-dark);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Stats Cards - FLAT DESIGN */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.summary-card {
    background: var(--card-bg);
    border-radius: 10px;
    padding: 1rem;
    box-shadow: var(--shadow-light);
    transition: all 0.3s ease;
    border-top: 4px solid;
    height: 100%;
}

.summary-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-medium);
}

.summary-card.primary {
    border-top-color: var(--primary-blue);
}

.summary-card.success {
    border-top-color: var(--primary-green);
}

.summary-card.info {
    border-top-color: var(--primary-blue);
}

.summary-card.warning {
    border-top-color: var(--primary-yellow);
}

.summary-card .icon-wrapper {
    width: 45px;
    height: 45px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin-bottom: 0.75rem;
}

.summary-card.primary .icon-wrapper {
    background: rgba(52, 152, 219, 0.1);
    color: var(--primary-blue);
}

.summary-card.success .icon-wrapper {
    background: rgba(39, 174, 96, 0.1);
    color: var(--primary-green);
}

.summary-card.info .icon-wrapper {
    background: rgba(52, 152, 219, 0.1);
    color: var(--primary-blue);
}

.summary-card.warning .icon-wrapper {
    background: rgba(241, 196, 15, 0.1);
    color: var(--primary-yellow);
}

.summary-card h6 {
    color: var(--text-light);
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.35rem;
}

.summary-card h3 {
    font-size: 1.6rem;
    font-weight: 800;
    margin: 0 0 0.35rem 0;
    color: var(--text-dark);
}

.summary-card .trend {
    font-size: 0.75rem;
    color: var(--text-light);
}

/* Cards */
.card {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: var(--shadow-light);
    border: none;
    margin-bottom: 1rem;
    overflow: hidden;
}

.card-header {
    background: var(--light-bg);
    border-bottom: 1px solid var(--border-color);
    padding: 0.9rem 1rem;
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--text-dark);
}

.card-header i {
    color: var(--primary-green);
    margin-right: 0.5rem;
}

.card-body {
    padding: 1rem;
}

/* Form Controls */
.form-label {
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.35rem;
    font-size: 0.85rem;
}

.form-control, .form-select {
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 0.6rem;
    font-size: 0.85rem;
    color: var(--text-dark);
    background: var(--card-bg);
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.btn-primary {
    background: var(--primary-blue);
    border: none;
    color: white;
    padding: 0.6rem 1.25rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: #2980b9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
    color: white;
}

.w-100 {
    width: 100%;
}

.mb-3 {
    margin-bottom: 0.9rem;
}

/* Table - COMPACT */
.table-responsive {
    border-radius: 12px;
    overflow: hidden;
}

.table {
    margin: 0;
    font-size: 0.9rem;
}

.table thead {
    background: var(--light-bg);
}

.table thead th {
    color: var(--text-dark);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.5px;
    padding: 0.7rem 0.8rem;
    border-bottom: 2px solid var(--border-color);
}

.table tbody tr {
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    background: var(--light-bg);
}

.table td {
    padding: 0.7rem 0.8rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-dark);
}

.table td strong {
    color: var(--text-dark);
    font-weight: 600;
}

/* Badges */
.badge {
    padding: 0.4rem 0.75rem;
    font-weight: 600;
    font-size: 0.75rem;
    border-radius: 6px;
}

.bg-success {
    background: var(--primary-green) !important;
    color: white !important;
}

.bg-info {
    background: var(--primary-blue) !important;
    color: white !important;
}

.bg-primary {
    background: var(--primary-blue) !important;
    color: white !important;
}

.bg-secondary {
    background: var(--primary-gray) !important;
    color: white !important;
}

/* Chart Styling */
#salesChart {
    max-height: 300px;
}

.row {
    margin-bottom: 1rem;
}

.col-lg-8, .col-lg-4 {
    margin-bottom: 0;
}

.mb-4 {
    margin-bottom: 1rem;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .main-content {
        padding: 12px;
    }

    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .summary-card {
        padding: 0.85rem;
    }

    .summary-card h3 {
        font-size: 1.35rem;
    }

    .card-header {
        padding: 0.75rem;
        font-size: 0.9rem;
    }

    .card-body {
        padding: 0.85rem;
    }

    .table {
        font-size: 0.8rem;
    }

    .table thead th,
    .table td {
        padding: 0.6rem 0.5rem;
    }

    .form-control, .form-select {
        padding: 0.5rem;
        font-size: 0.8rem;
    }

    .btn-primary {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }

    /* Stack table on mobile */
    .table-responsive {
        border: 0;
    }

    .table thead {
        display: none;
    }

    .table tbody tr {
        display: block;
        margin-bottom: 0.75rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
    }

    .table tbody td {
        display: block;
        text-align: right;
        padding: 0.6rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .table tbody td:last-child {
        border-bottom: none;
    }

    .table tbody td::before {
        content: attr(data-label);
        float: left;
        font-weight: 600;
        color: #475569;
        font-size: 0.75rem;
    }

    #salesChart {
        max-height: 200px;
    }
}

@media (max-width: 576px) {
    .summary-card .icon-wrapper {
        width: 35px;
        height: 35px;
        font-size: 18px;
    }

    .summary-card h3 {
        font-size: 1.15rem;
    }

    .stats-grid {
        gap: 0.75rem;
    }
}

/* Print Styles */
@media print {
    .main-content {
        background: white;
    }

    .card {
        box-shadow: none;
        border: 1px solid #ddd;
    }

    .btn, .form-control, .form-select {
        display: none;
    }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <!-- Welcome Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <h1 class="animate-slide-in">
                    <i class="bi bi-receipt me-2"></i>Sales Management
                </h1>
                <p class="mb-0 animate-fade-in">
                    View and manage all sales transactions • Period: <?php echo date('M d', strtotime($date_from)) . ' - ' . date('M d, Y', strtotime($date_to)); ?>
                </p>
            </div>
            <div class="header-actions">
                <div class="real-time-indicator animate-fade-in">
                    <div class="pulse-dot"></div>
                    <span>Live Updates</span>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="stats-grid">
            <div class="summary-card primary">
                <div class="icon-wrapper">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
                <h6>Total Transactions</h6>
                <h3><?php echo number_format($summary['total_transactions']); ?></h3>
                <div class="trend">
                    <i class="bi bi-graph-up me-1"></i>Sales count
                </div>
            </div>
            <div class="summary-card success">
                <div class="icon-wrapper">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <h6>Total Sales</h6>
                <h3>₱<?php echo number_format($summary['total_sales'], 2); ?></h3>
                <div class="trend">
                    <i class="bi bi-arrow-up-right me-1"></i>Revenue
                </div>
            </div>
            <div class="summary-card info">
                <div class="icon-wrapper">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <h6>Total Profit</h6>
                <h3>₱<?php echo number_format($summary['total_profit'], 2); ?></h3>
                <div class="trend">
                    <i class="bi bi-piggy-bank me-1"></i>Earnings
                </div>
            </div>
            <div class="summary-card warning">
                <div class="icon-wrapper">
                    <i class="bi bi-box-seam"></i>
                </div>
                <h6>Items Sold</h6>
                <h3><?php echo number_format($summary['total_items']); ?></h3>
                <div class="trend">
                    <i class="bi bi-cart-check me-1"></i>Units
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sales Chart -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-bar-chart me-2"></i>Daily Sales Chart
                    </div>
                    <div class="card-body">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-funnel me-2"></i>Filters
                    </div>
                    <div class="card-body">
                        <form method="GET">
                            <div class="mb-3">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" name="date_from" 
                                       value="<?php echo $date_from; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Date To</label>
                                <input type="date" class="form-control" name="date_to" 
                                       value="<?php echo $date_to; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Staff Member</label>
                                <select class="form-select" name="user_id">
                                    <option value="">All Staff</option>
                                    <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                                        <option value="<?php echo $user['user_id']; ?>" 
                                                <?php echo ($user_filter == $user['user_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search me-2"></i>Apply Filters
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Table -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-table me-2"></i>Sales Transactions
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($sales_result) > 0): ?>
                                <?php while ($sale = mysqli_fetch_assoc($sales_result)): ?>
                                    <tr>
                                        <td data-label="Invoice #">
                                            <strong><?php echo htmlspecialchars($sale['invoice_number']); ?></strong>
                                        </td>
                                        <td data-label="Date & Time">
                                            <?php echo date('M d, Y h:i A', strtotime($sale['created_at'])); ?>
                                        </td>
                                        <td data-label="Customer">
                                            <?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?>
                                        </td>
                                        <td data-label="Items">
                                            <span class="badge bg-secondary"><?php echo $sale['item_count']; ?> items</span>
                                        </td>
                                        <td data-label="Amount">
                                            <strong>₱<?php echo number_format($sale['total_amount'], 2); ?></strong>
                                        </td>
                                        <td data-label="Profit">
                                            <span style="color: #10b981; font-weight: 600;">
                                                ₱<?php echo number_format($sale['total_profit'], 2); ?>
                                            </span>
                                        </td>
                                        <td data-label="Payment">
                                            <span class="badge bg-<?php 
                                                echo $sale['payment_method'] == 'cash' ? 'success' : 
                                                     ($sale['payment_method'] == 'gcash' ? 'info' : 'primary'); 
                                            ?>">
                                                <?php echo strtoupper($sale['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td data-label="Staff">
                                            <?php echo htmlspecialchars($sale['staff_name']); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center" style="padding: 40px;">
                                        <i class="bi bi-inbox" style="font-size: 3rem; color: #cbd5e1;"></i>
                                        <p class="mt-3 mb-0" style="color: #64748b;">No sales found for selected filters</p>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Enhanced Sales Bar Chart
const ctx = document.getElementById('salesChart');
if (ctx) {
    const salesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($chart_data, 'date')); ?>,
            datasets: [{
                label: 'Daily Sales (₱)',
                data: <?php echo json_encode(array_column($chart_data, 'sales')); ?>,
                backgroundColor: 'rgba(102, 126, 234, 0.8)',
                borderColor: '#667eea',
                borderWidth: 2,
                borderRadius: 8,
                hoverBackgroundColor: 'rgba(118, 75, 162, 0.9)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: {
                            size: 14,
                            weight: '600'
                        },
                        padding: 15,
                        usePointStyle: true
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
                    },
                    callbacks: {
                        label: function(context) {
                            return ' Sales: ₱' + context.parsed.y.toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
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
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        },
                        font: {
                            size: 12
                        },
                        padding: 10
                    }
                },
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: 12
                        },
                        padding: 10
                    }
                }
            },
            animation: {
                duration: 1000,
                easing: 'easeInOutQuart'
            }
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>