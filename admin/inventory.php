<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_admin();
prevent_cache();

$page_title = "Inventory Management";

// Handle inventory update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_inventory'])) {
    // Verify session token
    if (!verify_csrf_token($_POST['session_token'] ?? '')) {
        $_SESSION['error_message'] = "Invalid session. Please try again.";
        header('Location: inventory.php');
        exit();
    }
    
    $date = clean_input($_POST['date']);
    $user_id = $_SESSION['user_id'];
    
    // Update stock in and stock out for each product
    if (isset($_POST['stock_in']) && is_array($_POST['stock_in'])) {
        foreach ($_POST['stock_in'] as $product_id => $stock_in) {
            $product_id = intval($product_id);
            $stock_in = intval(clean_input($stock_in));
            $stock_out = isset($_POST['stock_out'][$product_id]) ? intval(clean_input($_POST['stock_out'][$product_id])) : 0;
            
            // Check if record exists
            $check_sql = "SELECT inventory_id FROM inventory WHERE product_id = ? AND date = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "is", $product_id, $date);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) > 0) {
                // Update existing record
                $current_calc = $stock_in - $stock_out;
                $update_sql = "UPDATE inventory 
                              SET stock_in = ?,
                                  stock_out = ?,
                                  opening_stock = ?,
                                  closing_stock = ?,
                                  current_stock = ?,
                                  updated_by = ?,
                                  updated_at = NOW()
                              WHERE product_id = ? AND date = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "iiiiiiis", 
                    $stock_in, $stock_out, $stock_in, $stock_out, $current_calc, $user_id, $product_id, $date);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            } else {
                // Insert new record
                $current_calc = $stock_in - $stock_out;
                $insert_sql = "INSERT INTO inventory (product_id, stock_in, stock_out, opening_stock, closing_stock, current_stock, date, updated_by, created_at, updated_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $insert_stmt = mysqli_prepare($conn, $insert_sql);
                mysqli_stmt_bind_param($insert_stmt, "iiiiisi", 
                    $product_id, $stock_in, $stock_out, $stock_in, $stock_out, $current_calc, $date, $user_id);
                mysqli_stmt_execute($insert_stmt);
                mysqli_stmt_close($insert_stmt);
            }
            mysqli_stmt_close($check_stmt);
        }
    }
    
    // Log audit
    log_audit($user_id, 'UPDATE', 'inventory', 0, null, [
        'action' => 'manual_stock_update',
        'date' => $date,
        'updated_by' => $_SESSION['full_name']
    ]);
    
    $_SESSION['success_message'] = "Inventory updated successfully!";
    header('Location: inventory.php?date=' . $date);
    exit();
}

// Get current date
$today = isset($_GET['date']) ? clean_input($_GET['date']) : date('Y-m-d');

// Get category filter
$category_filter = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;

// Get all categories for filter
$categories_sql = "SELECT category_id, name FROM categories ORDER BY name";
$categories_result = mysqli_query($conn, $categories_sql);
$categories = [];
while ($cat = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $cat;
}

// Build WHERE clause for category filter
$where_clause = "";
if ($category_filter) {
    $where_clause = " AND p.category_id = " . $category_filter;
}

// Query to get ALL products with their stock information (ONLY stock_in and stock_out)
$inventory_sql = "SELECT 
                    p.product_id,
                    p.product_name,
                    p.product_code,
                    p.size,
                    p.unit,
                    p.image_path,
                    p.category_id,
                    c.name as category_name,
                    COALESCE(i.stock_in, 0) as stock_in,
                    COALESCE(i.stock_out, 0) as stock_out
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.category_id
                LEFT JOIN inventory i ON p.product_id = i.product_id AND i.date = ?
                WHERE p.status = 'active'" . $where_clause . "
                ORDER BY p.product_name";

$stmt = mysqli_prepare($conn, $inventory_sql);
mysqli_stmt_bind_param($stmt, "s", $today);
mysqli_stmt_execute($stmt);
$inventory_result = mysqli_stmt_get_result($stmt);

// Calculate totals
$total_stock_in = 0;
$total_stock_out = 0;
$net_stock = 0;

// Store results in array
$inventory_data = [];
while ($row = mysqli_fetch_assoc($inventory_result)) {
    $inventory_data[] = $row;
    $total_stock_in += $row['stock_in'];
    $total_stock_out += $row['stock_out'];
}
$net_stock = $total_stock_in - $total_stock_out;

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
/* Inventory Page Styles */
:root {
    --primary-teal: #1a4d5c;
    --secondary-teal: #0f3543;
    --accent-cyan: #22d3ee;
    --accent-green: #4ade80;
    --accent-orange: #f59e0b;
    --accent-red: #ef4444;
    --light-bg: #f8fafc;
    --card-bg: #ffffff;
    --text-dark: #1e293b;
    --text-light: #64748b;
    --text-muted: #94a3b8;
    --border-color: #e2e8f0;
    --shadow-light: 0 2px 12px rgba(26, 77, 92, 0.08);
    --shadow-medium: 0 6px 25px rgba(26, 77, 92, 0.12);
}

body {
    background-color: var(--light-bg);
    color: var(--text-dark);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Page Header */
.page-header {
    background: linear-gradient(135deg, var(--primary-teal) 0%, var(--secondary-teal) 100%);
    border-radius: 10px;
    padding: 1.25rem;
    color: #ffffff;
    margin-bottom: 1rem;
    box-shadow: var(--shadow-medium);
}

.page-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    color: #ffffff;
}

.page-header p {
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 0;
    font-size: 0.875rem;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.stat-card {
    background: linear-gradient(135deg, var(--card-bg) 0%, #f1f5f9 100%);
    border-radius: 10px;
    padding: 0.875rem;
    box-shadow: var(--shadow-light);
    transition: all 0.3s ease;
    border-top: 3px solid;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-medium);
}

.stat-card.stock-in { border-top-color: var(--accent-green); }
.stat-card.stock-out { border-top-color: var(--accent-orange); }
.stat-card.net-stock { border-top-color: var(--primary-teal); }

.stat-card-content {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}

.stat-icon.stock-in {
    background: linear-gradient(135deg, var(--accent-green), #22c55e);
    color: white;
}

.stat-icon.stock-out {
    background: linear-gradient(135deg, var(--accent-orange), #f59e0b);
    color: white;
}

.stat-icon.net-stock {
    background: linear-gradient(135deg, var(--primary-teal), var(--secondary-teal));
    color: white;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-light);
    font-weight: 600;
}

/* Content Card */
.content-card {
    background: var(--card-bg);
    border-radius: 10px;
    box-shadow: var(--shadow-light);
    overflow: hidden;
    margin-bottom: 1rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.card-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 0.875rem 1rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.card-header h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-dark);
    margin: 0;
    display: flex;
    align-items: center;
}

.card-header h3 i {
    margin-right: 0.5rem;
    color: var(--primary-teal);
}

/* Filter Controls */
.filter-controls {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.date-picker, .category-filter {
    background: var(--card-bg);
    border: 1.5px solid var(--border-color);
    border-radius: 6px;
    padding: 0.5rem 0.75rem;
    font-size: 0.8rem;
    color: var(--text-dark);
    cursor: pointer;
    transition: all 0.3s ease;
}

.date-picker:focus, .category-filter:focus {
    outline: none;
    border-color: var(--accent-cyan);
    box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.1);
}

/* Inventory Table */
.table-container {
    padding: 0.875rem;
    overflow-x: auto;
}

.inventory-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.inventory-table thead {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.inventory-table th {
    padding: 0.625rem 0.5rem;
    text-align: left;
    font-weight: 600;
    color: var(--primary-teal);
    border-bottom: 2px solid var(--border-color);
    white-space: nowrap;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.3px;
}

.inventory-table td {
    padding: 0.625rem 0.5rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
}

.inventory-table tbody tr:hover {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

/* Product Image */
.product-img {
    width: 45px;
    height: 45px;
    border-radius: 8px;
    object-fit: cover;
    box-shadow: 0 2px 8px rgba(26, 77, 92, 0.1);
}

.product-img-placeholder {
    width: 45px;
    height: 45px;
    border-radius: 8px;
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-cyan);
    font-size: 1.2rem;
}

/* Stock Input */
.stock-input {
    width: 70px;
    padding: 0.5rem;
    border: 1.5px solid var(--border-color);
    border-radius: 6px;
    text-align: center;
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.stock-input:focus {
    outline: none;
    border-color: var(--accent-cyan);
    box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.1);
}

/* Save Button */
.btn-save {
    background: linear-gradient(135deg, var(--accent-green), #16a34a);
    color: white;
    border: none;
    padding: 0.75rem 1.75rem;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 15px rgba(74, 222, 128, 0.25);
}

.btn-save:hover {
    background: linear-gradient(135deg, #22c55e, #15803d);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(74, 222, 128, 0.35);
}

/* Search */
.search-container {
    position: relative;
    margin-bottom: 0.5rem;
    padding: 0 0.875rem;
}

.search-input {
    width: 100%;
    padding: 0.625rem 0.875rem 0.625rem 2.5rem;
    border: 1.5px solid var(--border-color);
    border-radius: 8px;
    font-size: 0.85rem;
    color: var(--text-dark);
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--accent-cyan);
    box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.1);
}

.search-icon {
    position: absolute;
    left: 1.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--accent-cyan);
    font-size: 1rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-icon {
    font-size: 3rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
}

/* Status Badge */
.badge-status {
    font-weight: 600;
    font-size: 0.8rem;
    padding: 0.5rem 0.75rem !important;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    white-space: nowrap;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.badge-status:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.badge-status .bi {
    font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-controls {
        width: 100%;
        flex-direction: column;
    }
    
    .date-picker, .category-filter {
        width: 100%;
    }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h2><i class="bi bi-box-seam"></i> Inventory Management</h2>
            <p>Track stock movements and monitor inventory levels in real-time</p>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-x-circle me-2"></i>
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card stock-in">
                <div class="stat-card-content">
                    <div class="stat-icon stock-in">
                        <i class="bi bi-arrow-down-circle"></i>
                    </div>
                    <div class="stat-main">
                        <div class="stat-number"><?php echo number_format($total_stock_in); ?></div>
                        <div class="stat-label">Stock In</div>
                    </div>
                </div>
            </div>

            <div class="stat-card stock-out">
                <div class="stat-card-content">
                    <div class="stat-icon stock-out">
                        <i class="bi bi-arrow-up-circle"></i>
                    </div>
                    <div class="stat-main">
                        <div class="stat-number"><?php echo number_format($total_stock_out); ?></div>
                        <div class="stat-label">Stock Out</div>
                    </div>
                </div>
            </div>

            <div class="stat-card net-stock">
                <div class="stat-card-content">
                    <div class="stat-icon net-stock">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="stat-main">
                        <div class="stat-number"><?php echo number_format($net_stock); ?></div>
                        <div class="stat-label">Current Stock</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Inventory Form -->
        <div class="content-card">
            <div class="card-header">
                <h3>
                    <i class="bi bi-calendar3"></i>
                    Inventory for <?php echo date('M d, Y', strtotime($today)); ?>
                </h3>
                <div class="filter-controls">
                    <select class="category-filter" onchange="filterByCategory(this.value)">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>" 
                                <?php echo ($category_filter == $cat['category_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" 
                           class="date-picker" 
                           value="<?php echo $today; ?>" 
                           onchange="changeDate(this.value)">
                </div>
            </div>

            <!-- Search Bar -->
            <div class="search-container">
                <i class="bi bi-search search-icon"></i>
                <input type="text" 
                       class="search-input" 
                       id="inventorySearch" 
                       placeholder="Search products...">
            </div>

            <form method="POST" id="inventoryForm">
                <input type="hidden" name="date" value="<?php echo $today; ?>">
                <input type="hidden" name="update_inventory" value="1">
                <?php echo output_token_field(); ?>

                <div class="table-container">
                    <table class="inventory-table" id="inventoryTable">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Product</th>
                                <th>Size</th>
                                <th>Category</th>
                                <th>Stock In</th>
                                <th>Stock Out</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inventory_data)): ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <div class="empty-icon">
                                                <i class="bi bi-inbox"></i>
                                            </div>
                                            <p>No products found</p>
                                            <?php if ($category_filter): ?>
                                                <p class="text-muted">
                                                    Try selecting a different category or 
                                                    <a href="inventory.php?date=<?php echo $today; ?>">view all products</a>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($inventory_data as $item): ?>
                                    <tr class="inventory-row">
                                        <td>
                                            <?php if ($item['image_path'] && file_exists('../' . $item['image_path'])): ?>
                                                <img src="../<?php echo htmlspecialchars($item['image_path']); ?>" 
                                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                     class="product-img">
                                            <?php else: ?>
                                                <div class="product-img-placeholder">
                                                    <i class="bi bi-droplet"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                            <?php if ($item['product_code']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($item['product_code']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($item['size']); ?> <?php echo htmlspecialchars($item['unit'] ?? 'kg'); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info bg-opacity-10 text-info">
                                                <?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   name="stock_in[<?php echo $item['product_id']; ?>]" 
                                                   class="stock-input" 
                                                   value="<?php echo $item['stock_in']; ?>" 
                                                   min="0">
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   name="stock_out[<?php echo $item['product_id']; ?>]" 
                                                   class="stock-input" 
                                                   value="<?php echo $item['stock_out']; ?>" 
                                                   min="0">
                                        </td>
                                        <td>
                                            <?php 
                                            $net = $item['stock_in'] - $item['stock_out'];
                                            if ($net > 0) {
                                                $badge_class = 'bg-success';
                                                $status_text = '✓ Good Stock';
                                                $icon = 'bi-check-circle-fill';
                                            } else {
                                                $badge_class = 'bg-danger';
                                                $status_text = '⚠ Low Stock';
                                                $icon = 'bi-exclamation-circle-fill';
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?> badge-status">
                                                <i class="bi <?php echo $icon; ?> me-1"></i><?php echo $status_text; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($inventory_data)): ?>
                    <div style="padding: 1rem; text-align: right;">
                        <button type="submit" class="btn-save">
                            <i class="bi bi-check-circle"></i> Save Changes
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<script>
// Search functionality
document.getElementById('inventorySearch').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase().trim();
    const rows = document.querySelectorAll('.inventory-row');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Category filter function
function filterByCategory(categoryId) {
    const currentDate = document.querySelector('.date-picker').value;
    let url = 'inventory.php?date=' + currentDate;
    if (categoryId) {
        url += '&category_id=' + categoryId;
    }
    window.location.href = url;
}

// Date change function
function changeDate(date) {
    const currentCategory = document.querySelector('.category-filter').value;
    let url = 'inventory.php?date=' + date;
    if (currentCategory) {
        url += '&category_id=' + currentCategory;
    }
    window.location.href = url;
}
</script>

<?php include '../includes/footer.php'; ?>