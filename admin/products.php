<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_admin();
prevent_cache();

$page_title = "Products";

// Helper function to clean all output buffers for JSON responses
function json_response($data, $http_code = 200) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($http_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    die();
}

// Function to verify admin PIN for products
function verifyAdminPinProduct($pin) {
    global $conn;
    
    $user_id = $_SESSION['user_id'];
    error_log('verifyAdminPinProduct called: user_id=' . $user_id . ', pin length=' . strlen($pin));
    
    // Validate PIN format first
    if (!is_numeric($pin) || strlen($pin) !== 6) {
        error_log('PIN format invalid: not numeric or wrong length');
        return false;
    }
    
    $sql = "SELECT pin_hash FROM admin_pins WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        error_log('Prepare failed: ' . mysqli_error($conn));
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        error_log('Execute failed: ' . mysqli_stmt_error($stmt));
        return false;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$data) {
        error_log('No PIN record found for user_id: ' . $user_id);
        return false;
    }
    
    $pin_hash = $data['pin_hash'];
    error_log('PIN hash found, attempting verification');
    
    // Use password_verify with explicit hash
    $verify_result = password_verify($pin, $pin_hash);
    error_log('password_verify result: ' . ($verify_result ? 'TRUE' : 'FALSE'));
    error_log('Hash algorithm check: ' . password_algos()[0]);
    
    if (!$verify_result) {
        error_log('PIN verification failed for user: ' . $user_id);
    }
    
    return $verify_result;
}

// Handle Add/Edit Product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_product'])) {
    // Verify session token
    if (!verify_csrf_token($_POST['session_token'] ?? '')) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid session. Please try again.']);
        exit();
    }
    
    // Clear any previous output
    ob_clean();
    
    // Log debug info
    error_log('Product POST received. product_id: ' . (isset($_POST['product_id']) ? $_POST['product_id'] : 'not set'));
    error_log('product_pin: ' . (isset($_POST['product_pin']) ? 'PROVIDED' : 'NOT PROVIDED'));
    error_log('role: ' . $_SESSION['role_name']);
    
    // Check if this is a new product requiring PIN verification
    if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
        // New product - PIN verification required
        if (!isset($_POST['product_pin']) || empty($_POST['product_pin'])) {
            error_log('New product without PIN - rejecting');
            ob_clean();
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'PIN verification required']);
            exit;
        }
        
        // Verify PIN
        if (!verifyAdminPinProduct($_POST['product_pin'])) {
            error_log('PIN verification failed for new product');
            ob_clean();
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid PIN']);
            exit;
        }
        
        error_log('PIN verified for new product');
    }
    
    $product_name = clean_input($_POST['product_name']);
    // Product code - not generating for now
    $product_code = '';
    
    $size = clean_input($_POST['size']);
    $unit = clean_input($_POST['unit']);
    $capital_cost = (float)$_POST['capital_cost'];
    $current_price = (float)$_POST['current_price'];
    $status = clean_input($_POST['status']);
    
    // Handle image upload
    $image_path = '';
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['product_image']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($filetype), $allowed)) {
            $new_filename = uniqid() . '.' . $filetype;
            $upload_path = '../uploads/products/';
            
            if (!file_exists($upload_path)) {
                mkdir($upload_path, 0777, true);
            }
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path . $new_filename)) {
                $image_path = 'uploads/products/' . $new_filename;
            }
        }
    }
    
    if (isset($_POST['product_id']) && !empty($_POST['product_id'])) {
        $product_id = (int)$_POST['product_id'];
        
        if ($image_path != '') {
            $old_image_sql = "SELECT image_path FROM products WHERE product_id = $product_id";
            $old_image_result = mysqli_query($conn, $old_image_sql);
            $old_image = mysqli_fetch_assoc($old_image_result);
            if ($old_image && $old_image['image_path'] && file_exists('../' . $old_image['image_path'])) {
                unlink('../' . $old_image['image_path']);
            }
        }
        
        $sql = "UPDATE products SET 
                product_name = '$product_name',
                product_code = '$product_code',
                size = '$size',
                unit = '$unit',
                capital_cost = $capital_cost,
                current_price = $current_price,
                status = '$status'";
        
        if ($image_path != '') {
            $sql .= ", image_path = '$image_path'";
        }
        
        $sql .= " WHERE product_id = $product_id";
        
        if (mysqli_query($conn, $sql)) {
            if (function_exists('log_audit')) {
                log_audit($_SESSION['user_id'], 'UPDATE', 'products', $product_id, null, 
                         ['name' => $product_name, 'price' => $current_price]);
            }
            $_SESSION['success'] = "Product updated successfully!";
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Product updated successfully!']);
                exit;
            }
        } else {
            $_SESSION['error'] = "Error updating product: " . mysqli_error($conn);
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error updating product']);
                exit;
            }
        }
    } else {
        $sql = "INSERT INTO products (product_name, product_code, size, unit, capital_cost, current_price, status, image_path) 
                VALUES ('$product_name', '$product_code', '$size', '$unit', $capital_cost, $current_price, '$status', '$image_path')";
        
        if (mysqli_query($conn, $sql)) {
            $new_product_id = mysqli_insert_id($conn);
            
            // Create initial inventory record for today
            $today = date('Y-m-d');
            $current_user_id = $_SESSION['user_id'];
            $inv_sql = "INSERT INTO inventory (product_id, date, opening_stock, current_stock, closing_stock, updated_by) 
                       VALUES ($new_product_id, '$today', 0, 0, 0, $current_user_id)
                       ON DUPLICATE KEY UPDATE date = '$today', updated_by = $current_user_id";
            mysqli_query($conn, $inv_sql);
            
            if (function_exists('log_audit')) {
                log_audit($_SESSION['user_id'], 'CREATE', 'products', $new_product_id, null, 
                         ['name' => $product_name]);
            }
            $_SESSION['success'] = "Product added successfully!";
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Product added successfully!']);
                exit;
            }
        } else {
            $_SESSION['error'] = "Error adding product: " . mysqli_error($conn);
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error adding product']);
                exit;
            }
        }
    }
    
    header('Location: products.php');
    exit();
}

// Handle Delete Product with PIN verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_product'])) {
    error_log('[DELETE_HANDLER] Started - session_token provided: ' . (isset($_POST['session_token']) ? 'YES' : 'NO'));
    
    // Clear all output buffers to ensure clean JSON response
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Verify session token
    if (!verify_csrf_token($_POST['session_token'] ?? '')) {
        error_log('[DELETE_HANDLER] CSRF verification failed');
        json_response(['success' => false, 'message' => 'Invalid session. Please try again.'], 400);
    }
    
    // Start output buffering to prevent issues with headers
    ob_start();
    
    try {
        $product_id = (int)$_POST['product_id'];
        $delete_pin = isset($_POST['delete_pin']) ? clean_input($_POST['delete_pin']) : '';
        
        error_log('Delete product handler: product_id=' . $product_id . ', pin provided=' . (empty($delete_pin) ? 'NO' : 'YES'));
        
        // Verify PIN
        if (!verifyAdminPinProduct($delete_pin)) {
            error_log('PIN verification failed for delete');
            json_response(['success' => false, 'message' => 'Invalid PIN'], 400);
        }
        
        error_log('PIN verified successfully for delete');
        
        // Get product details before deletion
        $product_sql = "SELECT product_name, image_path FROM products WHERE product_id = ?";
        $stmt = mysqli_prepare($conn, $product_sql);
        
        if (!$stmt) {
            error_log('Failed to prepare product select statement: ' . mysqli_error($conn));
            ob_clean();
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
            exit;
        }
        
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            error_log('Failed to execute product select: ' . mysqli_stmt_error($stmt));
            ob_clean();
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error fetching product: ' . mysqli_stmt_error($stmt)]);
            exit;
        }
        
        $product_result = mysqli_stmt_get_result($stmt);
        $product = mysqli_fetch_assoc($product_result);
        mysqli_stmt_close($stmt);
        
        if (!$product) {
            error_log('Product not found with ID: ' . $product_id);
            ob_clean();
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit;
        }
        
        error_log('Product found: ' . $product['product_name']);
        
        // Delete sale_items records first (remove references from sales)
        $sale_items_sql = "DELETE FROM sale_items WHERE product_id = ?";
        $stmt_sale_items = mysqli_prepare($conn, $sale_items_sql);
        
        if (!$stmt_sale_items) {
            error_log('Failed to prepare sale_items delete statement: ' . mysqli_error($conn));
            ob_clean();
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
            exit;
        }
        
        mysqli_stmt_bind_param($stmt_sale_items, "i", $product_id);
        
        if (!mysqli_stmt_execute($stmt_sale_items)) {
            error_log('Failed to delete sale_items: ' . mysqli_stmt_error($stmt_sale_items));
            ob_clean();
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error deleting sales data: ' . mysqli_stmt_error($stmt_sale_items)]);
            exit;
        }
        
        mysqli_stmt_close($stmt_sale_items);
        error_log('Sale items deleted for product: ' . $product_id);
        
        // Delete inventory records (due to foreign key constraint)
        $inventory_sql = "DELETE FROM inventory WHERE product_id = ?";
        $stmt_inventory = mysqli_prepare($conn, $inventory_sql);
        
        if (!$stmt_inventory) {
            error_log('Failed to prepare inventory delete statement: ' . mysqli_error($conn));
            ob_clean();
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
            exit;
        }
        
        mysqli_stmt_bind_param($stmt_inventory, "i", $product_id);
        
        if (!mysqli_stmt_execute($stmt_inventory)) {
            error_log('Failed to delete inventory: ' . mysqli_stmt_error($stmt_inventory));
            ob_clean();
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error deleting inventory: ' . mysqli_stmt_error($stmt_inventory)]);
            exit;
        }
        
        mysqli_stmt_close($stmt_inventory);
        error_log('Inventory records deleted for product: ' . $product_id);
        
        // Delete image if it exists
        if (!empty($product['image_path']) && file_exists('../' . $product['image_path'])) {
            if (@unlink('../' . $product['image_path'])) {
                error_log('Image deleted: ' . $product['image_path']);
            } else {
                error_log('Failed to delete image: ' . $product['image_path']);
            }
        }
        
        // Delete product
        $delete_sql = "DELETE FROM products WHERE product_id = ?";
        $stmt = mysqli_prepare($conn, $delete_sql);
        
        if (!$stmt) {
            error_log('Failed to prepare delete statement: ' . mysqli_error($conn));
            ob_clean();
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
            exit;
        }
        
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            error_log('Failed to execute delete statement: ' . mysqli_stmt_error($stmt));
            ob_clean();
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error deleting product: ' . mysqli_stmt_error($stmt)]);
            exit;
        }
        
        mysqli_stmt_close($stmt);
        error_log('Product deleted successfully: ' . $product_id);
        
        // Log audit - wrap in try-catch in case it fails
        try {
            if (function_exists('log_audit')) {
                log_audit($_SESSION['user_id'], 'DELETE', 'products', $product_id, null, 
                         json_encode(['name' => $product['product_name']]));
                error_log('Audit log recorded for product delete');
            } else {
                error_log('log_audit function not found');
            }
        } catch (Exception $audit_error) {
            error_log('Error logging audit: ' . $audit_error->getMessage());
            // Don't fail the delete if audit logging fails
        }
        
        json_response(['success' => true, 'message' => 'Product deleted successfully!'], 200);
        
    } catch (Exception $e) {
        error_log('Exception in delete handler: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        ob_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        die();
    }
}

// Get product statistics
$stats_sql = "SELECT 
              COUNT(*) as total_products,
              SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_products,
              SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_products
              FROM products";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get total sales stats
$sales_stats_sql = "SELECT 
                    COALESCE(SUM(CASE WHEN s.status = 'completed' THEN si.quantity ELSE 0 END), 0) as total_items_sold,
                    COALESCE(SUM(CASE WHEN s.status = 'completed' THEN si.subtotal ELSE 0 END), 0) as total_revenue
                    FROM sale_items si
                    LEFT JOIN sales s ON si.sale_id = s.sale_id";
$sales_stats_result = mysqli_query($conn, $sales_stats_sql);
$sales_stats = mysqli_fetch_assoc($sales_stats_result);

// Get products with sales data - FIXED THE TYPO HERE!
$products_sql = "SELECT p.product_id,
                 p.product_name,
                 p.product_code,
                 p.size,
                 p.unit,
                 p.image_path,
                 COALESCE(p.capital_cost, 0) as capital_cost,
                 COALESCE(p.current_price, 0) as current_price,
                 p.status,
                 p.created_at,
                 COALESCE(SUM(CASE WHEN s.status = 'completed' THEN si.quantity ELSE 0 END), 0) as total_sold,
                 COALESCE(SUM(CASE WHEN s.status = 'completed' THEN si.subtotal ELSE 0 END), 0) as total_revenue,
                 (COALESCE(p.current_price, 0) - COALESCE(p.capital_cost, 0)) as profit_margin
                 FROM products p
                 LEFT JOIN sale_items si ON p.product_id = si.product_id
                 LEFT JOIN sales s ON si.sale_id = s.sale_id
                 GROUP BY p.product_id
                 ORDER BY p.created_at DESC";
$products_result = mysqli_query($conn, $products_sql);

// Get top selling products with images (top 5 products by sales)
$top_products_sql = "SELECT p.product_id,
                     p.product_name,
                     p.product_code,
                     p.image_path,
                     p.size,
                     p.unit,
                     p.capital_cost,
                     p.current_price,
                     p.status,
                     p.created_at,
                     COALESCE(SUM(CASE WHEN s.status = 'completed' THEN si.quantity ELSE 0 END), 0) as qty_sold,
                     COALESCE(SUM(CASE WHEN s.status = 'completed' THEN si.quantity ELSE 0 END), 0) as total_sold,
                     COALESCE(SUM(CASE WHEN s.status = 'completed' THEN si.subtotal ELSE 0 END), 0) as total_revenue
                     FROM products p
                     LEFT JOIN sale_items si ON p.product_id = si.product_id
                     LEFT JOIN sales s ON si.sale_id = s.sale_id
                     GROUP BY p.product_id, p.product_name, p.product_code, p.image_path, p.size, p.unit, p.capital_cost, p.current_price, p.status, p.created_at
                     HAVING qty_sold > 0
                     ORDER BY qty_sold DESC
                     LIMIT 5";
$top_products_result = mysqli_query($conn, $top_products_sql);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- Sweet Alert Library -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.all.min.js"></script>

<style>
/* Products Page - Dark Teal Dashboard Design */
:root {
    --primary-teal: #1a4d5c;
    --secondary-teal: #0f3543;
    --dark-teal: #082a33;
    --accent-cyan: #22d3ee;
    --accent-green: #4ade80;
    --accent-blue: #3b82f6;
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
    --shadow-heavy: 0 12px 40px rgba(26, 77, 92, 0.18);
}

body {
    background-color: var(--light-bg);
    color: var(--text-dark);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Welcome Header */
.welcome-header {
    background: linear-gradient(135deg, var(--primary-teal) 0%, var(--secondary-teal) 100%);
    border-radius: 12px;
    padding: 2rem 1.5rem 1.5rem 1.5rem;
    color: white;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-medium);
    border: none;
    border-left: 5px solid var(--accent-cyan);
    backdrop-filter: blur(10px);
}

.welcome-header h1 {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    color: #ffffff;
    letter-spacing: -0.5px;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
}

.welcome-header h1 i {
    color: var(--accent-cyan);
}

.welcome-header p {
    color: rgba(255, 255, 255, 0.95);
    margin-bottom: 0;
    font-size: 1.05rem;
    letter-spacing: 0.3px;
}

/* Stats Cards - Dark Teal Design */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: linear-gradient(135deg, var(--card-bg) 0%, #f1f5f9 100%);
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
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, transparent 0%, rgba(34, 211, 238, 0.05) 100%);
    border-radius: 0 0 0 100%;
}

.stat-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-heavy);
}

.stat-card.products {
    border-top-color: var(--accent-blue);
    background: linear-gradient(135deg, var(--card-bg) 0%, #eff6ff 100%);
}

.stat-card.active {
    border-top-color: var(--accent-green);
    background: linear-gradient(135deg, var(--card-bg) 0%, #f0fdf4 100%);
}

.stat-card.sold {
    border-top-color: #8b5cf6;
    background: linear-gradient(135deg, var(--card-bg) 0%, #faf5ff 100%);
}

.stat-card.revenue {
    border-top-color: var(--accent-orange);
    background: linear-gradient(135deg, var(--card-bg) 0%, #fffbf0 100%);
}

.stat-card-content {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    flex-shrink: 0;
}

.stat-icon.products {
    background: linear-gradient(135deg, var(--accent-blue), #1d4ed8);
    color: white;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.25);
}

.stat-icon.active {
    background: linear-gradient(135deg, var(--accent-green), #22c55e);
    color: white;
    box-shadow: 0 4px 15px rgba(74, 222, 128, 0.25);
}

.stat-icon.sold {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    box-shadow: 0 4px 15px rgba(139, 92, 246, 0.25);
}

.stat-icon.revenue {
    background: linear-gradient(135deg, var(--accent-orange), #d97706);
    color: white;
    box-shadow: 0 4px 15px rgba(245, 158, 11, 0.25);
}

.stat-main {
    flex-grow: 1;
}

.stat-number {
    font-size: 1.85rem;
    font-weight: 800;
    color: var(--text-dark);
    margin-bottom: 0.5rem;
    letter-spacing: -0.3px;
    background: linear-gradient(135deg, var(--primary-teal), var(--secondary-teal));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1;
}

.stat-label {
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--text-light);
    font-weight: 700;
    margin-bottom: 0.75rem;
}

.stat-details {
    font-size: 0.8rem;
    color: var(--text-light);
}

/* Main Content */
.content-section {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.content-card {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: var(--shadow-light);
    overflow: hidden;
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.card-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 1.25rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.card-header h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0;
    display: flex;
    align-items: center;
    letter-spacing: -0.3px;
}

.card-header h3 i {
    margin-right: 0.75rem;
    color: var(--primary-teal);
    font-size: 1.3rem;
}

/* Products Table */
.table-container {
    padding: 1.25rem;
    overflow-x: auto;
}

.products-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.products-table thead {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.products-table th {
    padding: 0.85rem;
    text-align: left;
    font-weight: 700;
    color: var(--primary-teal);
    border-bottom: 2px solid var(--border-color);
    white-space: nowrap;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.products-table td {
    padding: 0.85rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
}

.products-table tbody tr {
    transition: background 0.2s ease;
}

.products-table tbody tr:hover {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.products-table tbody tr:last-child td {
    border-bottom: none;
}

/* Product Image */
.product-img {
    width: 50px;
    height: 50px;
    border-radius: 6px;
    object-fit: cover;
    display: block;
    border: 1px solid var(--border-color);
}

.product-img-placeholder {
    width: 50px;
    height: 50px;
    border-radius: 6px;
    background: var(--light-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-light);
    font-size: 1.25rem;
    border: 1px solid var(--border-color);
}

/* Status Badges */
.status-badge {
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border: none;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.status-active {
    background: rgba(74, 222, 128, 0.15);
    color: #15803d;
}

.status-inactive {
    background: rgba(149, 165, 166, 0.15);
    color: #64748b;
}

/* Action Buttons */
.btn-action {
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    box-shadow: none;
}

.btn-view {
    background: rgba(52, 152, 219, 0.1);
    color: var(--primary-blue);
    border: 1px solid rgba(52, 152, 219, 0.2);
}

.btn-view:hover {
    background: var(--primary-blue);
    color: white;
}

.btn-edit {
    background: rgba(241, 196, 15, 0.1);
    color: #d68910;
    border: 1px solid rgba(241, 196, 15, 0.2);
}

.btn-edit:hover {
    background: #f39c12;
    color: white;
}

.btn-delete {
    background: rgba(231, 76, 60, 0.1);
    color: #c0392b;
    border: 1px solid rgba(231, 76, 60, 0.2);
}

.btn-delete:hover {
    background: #e74c3c;
    color: white;
}

.btn-add {
    background: linear-gradient(135deg, var(--accent-green), #16a34a);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 15px rgba(74, 222, 128, 0.25);
    letter-spacing: 0.3px;
}

.btn-add:hover {
    background: linear-gradient(135deg, #22c55e, #15803d);
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(74, 222, 128, 0.35);
}

/* Top Products Grid */
.top-products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 0.8rem;
    padding: 1rem;
}

.top-product-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 0.75rem;
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid rgba(226, 232, 240, 0.8);
    cursor: pointer;
    box-shadow: var(--shadow-light);
}

.top-product-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-medium);
    border-color: var(--accent-cyan);
}

.top-product-image {
    width: 100%;
    height: 100px;
    object-fit: cover;
    border-radius: 6px;
    margin-bottom: 0.6rem;
    background: var(--light-bg);
}

.top-product-placeholder {
    width: 100%;
    height: 100px;
    background: var(--light-bg);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.6rem;
    color: var(--text-light);
    font-size: 1.75rem;
}

.top-product-name {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.4rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.2rem;
}

.top-product-sold {
    font-size: 0.75rem;
    color: var(--text-light);
    margin-bottom: 0.15rem;
}

.top-product-qty {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--primary-blue);
}

.top-product-price {
    font-size: 0.8rem;
    color: var(--accent-green);
    font-weight: 600;
    margin-top: 0.2rem;
}

.empty-top-products {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-light);
}

/* Search */
.search-container {
    position: relative;
    flex: 0 1 250px;
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1.5px solid var(--border-color);
    border-radius: 10px;
    font-size: 0.9rem;
    color: var(--text-dark);
    background: var(--card-bg);
    transition: all 0.3s ease;
    font-weight: 500;
}

.search-input:focus {
    outline: none;
    border-color: var(--accent-cyan);
    box-shadow: 0 0 0 4px rgba(34, 211, 238, 0.15);
    background: #f0feff;
}

.search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--accent-cyan);
    font-size: 1rem;
}

/* Alert Messages */
.alert-custom {
    border-radius: 12px;
    border: none;
    box-shadow: var(--shadow-light);
    border-left: 5px solid;
}

.alert-success {
    background: rgba(74, 222, 128, 0.15);
    border-left-color: var(--accent-green);
    color: var(--text-dark);
}

.alert-danger {
    background: rgba(239, 68, 68, 0.15);
    border-left-color: var(--accent-red);
    color: var(--text-dark);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    background: rgba(26, 188, 156, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: var(--primary-teal);
}

/* Modal */
.modal-header {
    background: var(--light-bg);
    border-bottom: 1px solid var(--border-color);
    padding: 1rem;
}

.modal-title {
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
}

.modal-body {
    padding: 1rem;
}

.modal-footer {
    background: var(--light-bg);
    border-top: 1px solid var(--border-color);
    padding: 1rem;
}

/* Form Controls */
.form-control, .form-select {
    border: 1.5px solid var(--border-color);
    border-radius: 10px;
    padding: 0.75rem;
    font-size: 0.9rem;
    color: var(--text-dark);
    background: var(--card-bg);
    transition: all 0.3s ease;
    font-weight: 500;
}

.form-control:focus, .form-select:focus {
    outline: none;
    border-color: var(--accent-cyan);
    box-shadow: 0 0 0 4px rgba(34, 211, 238, 0.15);
    background: #f0feff;
}

.form-label {
    margin-bottom: 0.4rem;
}

.input-group-text {
    background: var(--light-bg);
    border: 1px solid var(--border-color);
    color: var(--text-light);
    font-weight: 600;
}

/* Image Preview */
.image-preview {
    width: 180px;
    height: 180px;
    margin: 0 auto;
    background: rgba(26, 188, 156, 0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.preview-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: none;
}

/* Responsive Design */
@media (max-width: 768px) {
    .welcome-header {
        padding: 1rem;
    }
    
    .welcome-header h1 {
        font-size: 1.35rem;
        margin-bottom: 0.2rem;
    }
    
    .welcome-header p {
        font-size: 0.85rem;
    }
    
    .d-flex.justify-content-between.align-items-center.gap-2 {
        flex-direction: column;
        align-items: flex-start !important;
    }
    
    .btn-add {
        width: 100%;
    }
    
    .content-section {
        grid-template-columns: 1fr;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .search-container {
        width: 100%;
    }
    
    .products-table {
        font-size: 0.85rem;
    }
    
    .products-table th,
    .products-table td {
        padding: 0.6rem 0.5rem;
    }
    
    .stat-number {
        font-size: 1.3rem;
    }
    
    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 1.1rem;
    }
    
    .stat-label {
        font-size: 0.75rem;
    }
    
    .top-products-grid {
        grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
        gap: 0.6rem;
    }
    
    .top-product-image,
    .top-product-placeholder {
        height: 85px;
    }
    
    .btn-action {
        padding: 0.35rem 0.6rem;
        font-size: 0.75rem;
    }
}

@media (max-width: 576px) {
    .welcome-header h1 {
        font-size: 1.2rem;
    }
    
    .welcome-header p {
        font-size: 0.85rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .btn-add {
        width: 100%;
        justify-content: center;
    }
    
    .card-header h3 {
        font-size: 1rem;
    }
    
    .d-flex.flex-column.flex-md-row {
        gap: 0.75rem;
    }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <div class="d-flex justify-content-between align-items-center gap-2">
                <div>
                    <h1>
                        <i class="bi bi-tags me-2"></i>Products Management
                    </h1>
                    <p class="mb-0">Manage your product catalog, pricing, and inventory</p>
                </div>
                <button class="btn-add" data-bs-toggle="modal" data-bs-target="#productModal">
                    <i class="bi bi-plus-circle"></i> Add New
                </button>
            </div>
        </div>
        
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: '<?php echo addslashes($_SESSION['success']); ?>',
                        confirmButtonColor: '#27ae60'
                    });
                });
            </script>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: '<?php echo addslashes($_SESSION['error']); ?>',
                        confirmButtonColor: '#e74c3c'
                    });
                });
            </script>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card products">
                <div class="stat-card-content">
                    <div class="stat-icon products">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="stat-main">
                        <div class="stat-number"><?php echo number_format($stats['total_products']); ?></div>
                        <div class="stat-label">Total Products</div>
                        <div class="stat-details">
                            <i class="bi bi-check-circle text-success me-1"></i>
                            <?php echo number_format($stats['active_products']); ?> active
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card active">
                <div class="stat-card-content">
                    <div class="stat-icon active">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-main">
                        <div class="stat-number"><?php echo number_format($stats['active_products']); ?></div>
                        <div class="stat-label">Active Products</div>
                        <div class="stat-details">
                            <i class="bi bi-box-seam me-1"></i>
                            Available for sale
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card sold">
                <div class="stat-card-content">
                    <div class="stat-icon sold">
                        <i class="bi bi-cart-check"></i>
                    </div>
                    <div class="stat-main">
                        <div class="stat-number"><?php echo number_format($sales_stats['total_items_sold']); ?></div>
                        <div class="stat-label">Total Sold</div>
                        <div class="stat-details">
                            <i class="bi bi-graph-up me-1"></i>
                            Units sold
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card revenue">
                <div class="stat-card-content">
                    <div class="stat-icon revenue">
                        <i class="bi bi-currency-dollar"></i>
                    </div>
                    <div class="stat-main">
                        <div class="stat-number"><?php echo format_currency($sales_stats['total_revenue']); ?></div>
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-details">
                            <i class="bi bi-clock-history me-1"></i>
                            All time
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Products and Charts -->
        <div class="content-section">
            <!-- Products Table -->
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="bi bi-grid"></i> Product Catalog</h3>
                    <div class="search-container">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" 
                               id="searchInput" 
                               class="search-input" 
                               placeholder="Search products...">
                    </div>
                </div>
                <div class="table-container">
                    <table class="products-table" id="productsTable">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Sold</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($products_result) > 0): ?>
                                <?php while ($product = mysqli_fetch_assoc($products_result)): ?>
                                    <tr class="product-row">
                                        <td>
                                            <?php if ($product['image_path'] && file_exists('../' . $product['image_path'])): ?>
                                                <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" 
                                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                                     class="product-img">
                                            <?php else: ?>
                                                <div class="product-img-placeholder">
                                                    <i class="bi bi-droplet"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($product['size']); ?> <?php echo htmlspecialchars($product['unit'] ?? 'kg'); ?>
                                                <?php if ($product['product_code']): ?>
                                                    • <?php echo htmlspecialchars($product['product_code']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong class="text-success"><?php echo format_currency($product['current_price']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info bg-opacity-10 text-info">
                                                <?php echo number_format($product['total_sold']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($product['status'] == 'active'): ?>
                                                <span class="status-badge status-active" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                                                    <i class="bi bi-check-circle-fill me-1"></i> Active
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge status-inactive" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                                                    <i class="bi bi-x-circle-fill me-1"></i> Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button class="btn-action btn-view" 
                                                        onclick='viewProduct(<?php echo json_encode($product); ?>)'
                                                        title="View Details"
                                                        style="padding: 0.5rem 0.75rem;">
                                                    <i class="bi bi-eye-fill"></i> View
                                                </button>
                                                <button class="btn-action btn-edit" 
                                                        onclick='editProduct(<?php echo json_encode($product); ?>)'
                                                        title="Edit Product"
                                                        style="padding: 0.5rem 0.75rem;">
                                                    <i class="bi bi-pencil-fill"></i> Edit
                                                </button>
                                                <button class="btn-action btn-delete" 
                                                        onclick='deleteProduct(<?php echo $product["product_id"]; ?>, "<?php echo htmlspecialchars($product["product_name"], ENT_QUOTES); ?>")'
                                                        title="Delete Product"
                                                        style="padding: 0.5rem 0.75rem;">
                                                    <i class="bi bi-trash-fill"></i> Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">
                                        <div class="empty-state">
                                            <div class="empty-icon">
                                                <i class="bi bi-box-seam"></i>
                                            </div>
                                            <h5 class="mt-3">No Products Found</h5>
                                            <p class="text-muted">Start by adding your first product!</p>
                                            <button class="btn-add mt-3" data-bs-toggle="modal" data-bs-target="#productModal">
                                                <i class="bi bi-plus-circle"></i> Add Product
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Top Selling Products -->
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="bi bi-star-fill"></i> Top Selling Products</h3>
                </div>
                <div class="top-products-grid">
                    <?php if ($top_products_result && mysqli_num_rows($top_products_result) > 0): ?>
                        <?php while ($top_product = mysqli_fetch_assoc($top_products_result)): ?>
                            <div class="top-product-card" onclick='viewProduct(<?php echo json_encode($top_product); ?>)'>
                                <?php if ($top_product['image_path'] && file_exists('../' . $top_product['image_path'])): ?>
                                    <img src="../<?php echo htmlspecialchars($top_product['image_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($top_product['product_name']); ?>"
                                         class="top-product-image">
                                <?php else: ?>
                                    <div class="top-product-placeholder">
                                        <i class="bi bi-droplet"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="top-product-name">
                                    <?php echo htmlspecialchars($top_product['product_name']); ?>
                                </div>
                                <div class="top-product-sold">Sold</div>
                                <div class="top-product-qty"><?php echo number_format($top_product['qty_sold']); ?></div>
                                <div class="top-product-price"><?php echo format_currency($top_product['current_price']); ?></div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-top-products" style="grid-column: 1 / -1;">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                            <p class="mb-0">No sales data available yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats Card -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="bi bi-lightning"></i> Quick Stats</h3>
            </div>
            <div class="table-container">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">Inactive Products</small>
                            <h3 class="mb-0"><?php echo number_format($stats['inactive_products']); ?></h3>
                            <small class="text-muted">Not available for sale</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">Total Revenue</small>
                            <h3 class="mb-0 text-success"><?php echo format_currency($sales_stats['total_revenue']); ?></h3>
                            <small class="text-muted">From all product sales</small>
                        </div>
                    </div>
                </div>
                
                <!-- Best Seller -->
                <?php
                $best_seller_sql = "SELECT p.product_name, 
                                   COALESCE(SUM(CASE WHEN s.status = 'completed' THEN si.quantity ELSE 0 END), 0) as qty
                                   FROM products p
                                   LEFT JOIN sale_items si ON p.product_id = si.product_id
                                   LEFT JOIN sales s ON si.sale_id = s.sale_id
                                   GROUP BY p.product_id
                                   HAVING qty > 0
                                   ORDER BY qty DESC
                                   LIMIT 1";
                $best_seller_result = mysqli_query($conn, $best_seller_sql);
                $best_seller = mysqli_fetch_assoc($best_seller_result);
                ?>
                
                <?php if ($best_seller): ?>
                    <div class="alert alert-success mt-3" style="background: rgba(39, 174, 96, 0.1); border-left: 4px solid var(--primary-green);">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-trophy-fill text-warning fs-3 me-3"></i>
                            <div>
                                <small class="d-block text-muted">Best Seller</small>
                                <strong><?php echo htmlspecialchars($best_seller['product_name']); ?></strong>
                                <div class="mt-1">
                                    <span class="badge bg-success"><?php echo number_format($best_seller['qty']); ?> sold</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Product Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i><span id="modalTitle">Add New Product</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="productForm">
                <div class="modal-body">
                    <input type="hidden" name="product_id" id="product_id">
                    <input type="hidden" name="save_product" value="1">
                    <?php echo output_token_field(); ?>
                    <input type="hidden" name="pin_verified" id="pin_verified" value="0">
                    
                    <div class="row g-2">
                        <div class="col-12">
                            <div class="text-center mb-3">
                                <div class="image-preview" id="imagePreview">
                                    <i class="bi bi-droplet-fill text-teal fs-1" id="defaultIcon"></i>
                                    <img id="previewImage" class="preview-img">
                                </div>
                                <label class="btn btn-outline-success btn-sm mt-3" for="product_image">
                                    <i class="bi bi-upload me-1"></i> Upload Product Image
                                </label>
                                <input type="file" class="d-none" name="product_image" id="product_image" 
                                       accept="image/*" onchange="previewProductImage(this)">
                                <div><small class="text-muted">JPG, PNG, or GIF (Max 5MB)</small></div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Product Name *</label>
                            <input type="text" class="form-control" name="product_name" 
                                   id="product_name" placeholder="Enter product name" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Size *</label>
                            <input type="text" class="form-control" name="size" 
                                   id="size" placeholder="e.g. 11, 22, 50" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Unit *</label>
                            <select class="form-select" name="unit" id="unit" required>
                                <option value="kg">Kilogram (kg)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Capital Cost *</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" min="0" class="form-control" 
                                       name="capital_cost" id="capital_cost" value="0" 
                                       placeholder="0.00" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Selling Price *</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" min="0" class="form-control" 
                                       name="current_price" id="current_price" value="0" 
                                       placeholder="0.00" required>
                            </div>
                        </div>
                        
                        <div class="col-12" id="statusContainer">
                            <label class="form-label fw-semibold">Status *</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="status" 
                                               id="status_active" value="active" checked>
                                        <label class="form-check-label" for="status_active">
                                            <strong>Active</strong>
                                            <small class="d-block text-muted">Available for sale</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-6" id="inactiveStatusDiv">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="status" 
                                               id="status_inactive" value="inactive" disabled>
                                        <label class="form-check-label" for="status_inactive">
                                            <strong>Inactive</strong>
                                            <small class="d-block text-muted">Not available</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-success" onclick="saveProduct()">
                        <i class="bi bi-check-circle me-2"></i>Save Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Product Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-info-circle me-2"></i>Product Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="productDetails">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- PIN Verification Modal for Products -->
<div class="modal fade" id="productPinModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-shield-lock me-2"></i>Admin PIN Verification
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-4">Enter your 6-digit PIN to add this new product:</p>
                <div class="pin-input-container">
                    <input type="text" id="productPinInput" maxlength="6" pattern="\d*" 
                           class="pin-input" autocomplete="off">
                    <div class="pin-display" id="productPinDisplay">
                        <?php for($i = 0; $i < 6; $i++): ?>
                        <div class="pin-dot"></div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" id="confirmProductPinBtn" onclick="verifyProductPin()">
                    <i class="bi bi-check-circle me-2"></i>Verify PIN
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function previewProductImage(input) {
    const preview = document.getElementById('previewImage');
    const defaultIcon = document.getElementById('defaultIcon');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            defaultIcon.style.display = 'none';
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

function editProduct(product) {
    document.getElementById('modalTitle').textContent = 'Edit Product';
    document.getElementById('product_id').value = product.product_id || '';
    document.getElementById('product_name').value = product.product_name || '';
    document.getElementById('size').value = product.size || '';
    document.getElementById('unit').value = product.unit || 'kg';
    document.getElementById('capital_cost').value = product.capital_cost || '0';
    document.getElementById('current_price').value = product.current_price || '0';
    
    // Show and enable Inactive option when editing
    document.getElementById('inactiveStatusDiv').style.display = 'block';
    document.getElementById('status_inactive').disabled = false;
    
    // Set status radio
    if (product.status === 'active') {
        document.getElementById('status_active').checked = true;
    } else {
        document.getElementById('status_inactive').checked = true;
    }
    
    // Show existing image
    const preview = document.getElementById('previewImage');
    const defaultIcon = document.getElementById('defaultIcon');
    
    if (product.image_path) {
        preview.src = '../' + product.image_path;
        preview.style.display = 'block';
        defaultIcon.style.display = 'none';
    } else {
        preview.style.display = 'none';
        defaultIcon.style.display = 'block';
    }
    
    const modal = new bootstrap.Modal(document.getElementById('productModal'));
    modal.show();
}

function viewProduct(product) {
    const capitalCost = parseFloat(product.capital_cost || 0);
    const currentPrice = parseFloat(product.current_price || 0);
    const profitMargin = currentPrice - capitalCost;
    const profitPercent = capitalCost > 0 
        ? ((profitMargin / capitalCost) * 100).toFixed(2) 
        : 0;
    
    const html = `
        <div class="row g-4">
            <div class="col-md-4">
                <div class="text-center">
                    ${product.image_path ? 
                        `<img src="../${product.image_path}" alt="${product.product_name}" 
                              style="width: 100%; max-width: 200px; height: 200px; object-fit: cover; 
                              border-radius: 12px; margin-bottom: 1rem;">` :
                        `<div style="width: 200px; height: 200px; margin: 0 auto 1rem; 
                              background: rgba(26, 188, 156, 0.1); 
                              border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-droplet-fill text-teal" style="font-size: 4rem;"></i>
                         </div>`
                    }
                    <h4 class="mb-1">${product.product_name}</h4>
                    <p class="text-muted">${product.size} ${product.unit || 'kg'}</p>
                    <span class="badge bg-${(product.status || 'active') === 'active' ? 'success' : 'secondary'}-subtle text-${(product.status || 'active') === 'active' ? 'success' : 'secondary'} px-3 py-2">
                        <i class="bi bi-${(product.status || 'active') === 'active' ? 'check' : 'x'}-circle-fill me-1"></i>
                        ${(product.status || 'active').toUpperCase()}
                    </span>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 border rounded">
                            <small class="text-muted d-block mb-1">Capital Cost</small>
                            <h4 class="mb-0 text-secondary">₱${parseFloat(product.capital_cost || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</h4>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="p-3 border rounded">
                            <small class="text-muted d-block mb-1">Selling Price</small>
                            <h4 class="mb-0 text-success">₱${parseFloat(product.current_price || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</h4>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="p-3 border rounded">
                            <small class="text-muted d-block mb-1">Total Sold</small>
                            <h4 class="mb-0 text-info">${parseFloat(product.total_sold || product.qty_sold || 0).toLocaleString()}</h4>
                            <small class="text-muted">Units</small>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="p-3 border rounded">
                            <small class="text-muted d-block mb-1">Total Revenue</small>
                            <h4 class="mb-0 text-warning">₱${parseFloat(product.total_revenue || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</h4>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="p-3 border rounded">
                            <small class="text-muted d-block mb-1">Profit Analysis</small>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <small class="text-muted d-block">Profit per Unit</small>
                                        <h5 class="mb-0 text-${profitMargin >= 0 ? 'success' : 'danger'}">
                                            ₱${profitMargin.toLocaleString('en-US', {minimumFractionDigits: 2})}
                                        </h5>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <small class="text-muted d-block">Profit Margin</small>
                                        <h5 class="mb-0 text-${profitMargin >= 0 ? 'success' : 'danger'}">
                                            ${profitPercent}%
                                        </h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="p-3 border rounded">
                            <small class="text-muted d-block mb-1">Product Information</small>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <small class="text-muted d-block">Product Code</small>
                                    <strong>${product.product_code || 'N/A'}</strong>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <small class="text-muted d-block">Created At</small>
                                    <strong>${new Date(product.created_at).toLocaleDateString('en-US', {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric'
                                    })}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('productDetails').innerHTML = html;
    new bootstrap.Modal(document.getElementById('viewModal')).show();
}

// Delete Product Function
function deleteProduct(productId, productName) {
    Swal.fire({
        title: 'Delete Product?',
        html: `<p>Are you sure you want to delete <strong>${productName}</strong>?</p><p class="text-muted small">This action cannot be undone.</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#95a5a6',
        confirmButtonText: '<i class="bi bi-trash me-2"></i>Yes, Delete',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Show PIN verification modal
            showDeletePinModal(productId, productName);
        }
    });
}

// Show PIN Modal for Delete
function showDeletePinModal(productId, productName) {
    Swal.fire({
        title: 'Verify Admin PIN',
        html: `<p class="mb-3">Enter your 6-digit admin PIN to confirm product deletion</p>
               <div style="text-align: center;">
                   <input type="password" id="deletePinInput" 
                          maxlength="6" 
                          inputmode="numeric"
                          placeholder="●●●●●●"
                          style="font-size: 2rem; 
                                 letter-spacing: 10px; 
                                 text-align: center; 
                                 width: 100%; 
                                 padding: 12px; 
                                 border: 2px solid #e0e0e0; 
                                 border-radius: 8px;
                                 font-weight: bold;">
                   <small class="d-block mt-2 text-muted">Numbers only</small>
               </div>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#95a5a6',
        confirmButtonText: '<i class="bi bi-check me-2"></i>Verify & Delete',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        willOpen: () => {
            const pinInput = document.getElementById('deletePinInput');
            pinInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
            });
            pinInput.focus();
        },
        preConfirm: () => {
            const pin = document.getElementById('deletePinInput').value;
            if (pin.length !== 6) {
                Swal.showValidationMessage('PIN must be 6 digits');
                return false;
            }
            return pin;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            performDeleteProduct(productId, productName, result.value);
        }
    });
}

// Perform the actual delete
async function performDeleteProduct(productId, productName, pin) {
    const formData = new FormData();
    formData.append('delete_product', '1');
    formData.append('product_id', productId);
    formData.append('delete_pin', pin);
    
    // Manually ensure CSRF token is included
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        const token = csrfMeta.getAttribute('content');
        if (token && !formData.get('session_token')) {
            formData.append('session_token', token);
            console.log('[DELETE] Added CSRF token to FormData');
        }
    }
    
    console.log('[DELETE] FormData keys:', Array.from(formData.keys()));
    console.log('[DELETE] Sending POST to products.php', {
        'delete_product': formData.get('delete_product'),
        'product_id': formData.get('product_id'),
        'delete_pin': '***',
        'session_token': formData.get('session_token') ? formData.get('session_token').substring(0, 10) + '...' : 'MISSING'
    });
    
    try {
        const response = await fetch('products.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        // Check response status
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Get response text first
        const responseText = await response.text();
        console.log('Response:', responseText);
        
        // Try to parse as JSON
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('Failed to parse JSON. Response was:', responseText);
            throw new Error('Invalid response from server: ' + responseText.substring(0, 100));
        }
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Deleted!',
                text: `${productName} has been deleted successfully.`,
                confirmButtonColor: '#27ae60'
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: result.message || 'Invalid PIN or product not found.',
                confirmButtonColor: '#e74c3c'
            });
        }
    } catch (error) {
        console.error('Error:', error);
        console.error('Error message:', error.message);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: error.message || 'An error occurred while deleting the product.',
            confirmButtonColor: '#e74c3c'
        });
    }
}


// Reset form when modal is closed
document.getElementById('productModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('modalTitle').textContent = 'Add New Product';
    const form = document.querySelector('#productModal form');
    form.reset();
    document.getElementById('product_id').value = '';
    document.getElementById('unit').value = 'kg';
    document.getElementById('capital_cost').value = '0';
    document.getElementById('current_price').value = '0';
    document.getElementById('status_active').checked = true;
    
    // Hide Inactive option for new products
    document.getElementById('inactiveStatusDiv').style.display = 'none';
    
    // Disable Inactive radio button for new products
    document.getElementById('status_inactive').disabled = true;
    
    // Reset image preview
    const preview = document.getElementById('previewImage');
    const defaultIcon = document.getElementById('defaultIcon');
    preview.style.display = 'none';
    defaultIcon.style.display = 'block';
});

// When "Add New" button is clicked
document.addEventListener('DOMContentLoaded', function() {
    const addBtn = document.querySelector('[data-bs-target="#productModal"]');
    if (addBtn) {
        addBtn.addEventListener('click', function() {
            // Reset form on click
            document.getElementById('productForm').reset();
        });
    }
});

// Global variable to store form data for PIN verification
let pendingProductFormData = null;

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#productsTable tbody tr.product-row');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Auto-calculate selling price if only capital cost is entered
document.getElementById('capital_cost')?.addEventListener('input', function() {
    const capitalCost = parseFloat(this.value) || 0;
    const sellingPrice = document.getElementById('current_price');
    
    if (capitalCost > 0 && (!sellingPrice.value || sellingPrice.value === '0')) {
        const suggestedPrice = capitalCost * 1.3; // 30% markup
        sellingPrice.value = suggestedPrice.toFixed(2);
    }
});

// Save product with PIN verification
async function saveProduct() {
    const productId = document.getElementById('product_id').value;
    const form = document.getElementById('productForm');
    
    if (productId === '' || productId === null) {
        // New product - require PIN verification
        const formData = new FormData(form);
        pendingProductFormData = formData;
        showProductPinModal();
    } else {
        // Editing existing product - submit directly
        form.submit();
    }
}

// PIN Modal Functions
let productPinAttempts = 0;
const MAX_PRODUCT_PIN_ATTEMPTS = 3;

function showProductPinModal() {
    productPinAttempts = 0;
    
    // Reset PIN input
    document.getElementById('productPinInput').value = '';
    updateProductPinDisplay();
    document.getElementById('confirmProductPinBtn').disabled = false;
    
    // Show modal
    const pinModal = new bootstrap.Modal(document.getElementById('productPinModal'));
    pinModal.show();
    
    // Focus PIN input
    setTimeout(() => {
        document.getElementById('productPinInput').focus();
    }, 100);
}

function updateProductPinDisplay() {
    const pin = document.getElementById('productPinInput').value;
    const dots = document.querySelectorAll('#productPinDisplay .pin-dot');
    
    dots.forEach((dot, index) => {
        if (index < pin.length) {
            dot.classList.add('filled');
        } else {
            dot.classList.remove('filled');
        }
    });
}

async function verifyProductPin() {
    const pin = document.getElementById('productPinInput').value;
    const confirmBtn = document.getElementById('confirmProductPinBtn');
    
    if (pin.length !== 6) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid PIN',
            text: 'PIN must be 6 digits',
            confirmButtonColor: '#e67e22'
        });
        return;
    }
    
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Verifying...';
    
    try {
        // Use the stored form data from the first submission
        // Reuse all fields from the original form data and add the PIN
        const formData = pendingProductFormData;
        formData.append('product_pin', pin);
        
        const response = await fetch('products.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        const result = await response.json().catch(err => {
            console.error('JSON parse error:', err);
            console.error('Response status:', response.status);
            console.error('Response headers:', response.headers);
            throw new Error('Invalid response from server');
        });
        
        if (result.success) {
            // PIN verified
            const pinModal = bootstrap.Modal.getInstance(document.getElementById('productPinModal'));
            pinModal.hide();
            
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: result.message || 'Product added successfully!',
                confirmButtonColor: '#27ae60'
            }).then(() => {
                window.location.reload();
            });
        } else {
            // PIN failed
            productPinAttempts++;
            
            if (productPinAttempts >= MAX_PRODUCT_PIN_ATTEMPTS) {
                const pinModal = bootstrap.Modal.getInstance(document.getElementById('productPinModal'));
                pinModal.hide();
                
                Swal.fire({
                    icon: 'error',
                    title: 'Maximum Attempts Exceeded',
                    text: 'You have exceeded the maximum number of PIN attempts.',
                    confirmButtonColor: '#e74c3c'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid PIN',
                    text: `${MAX_PRODUCT_PIN_ATTEMPTS - productPinAttempts} attempt(s) remaining`,
                    confirmButtonColor: '#e74c3c'
                });
                
                // Clear and reset
                document.getElementById('productPinInput').value = '';
                updateProductPinDisplay();
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Verify PIN';
                
                setTimeout(() => {
                    document.getElementById('productPinInput').focus();
                }, 300);
            }
        }
    } catch (error) {
        console.error('Error verifying PIN:', error);
        console.error('Error message:', error.message);
        console.error('Error stack:', error.stack);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Error verifying PIN. Please try again.',
            confirmButtonColor: '#e74c3c'
        });
        
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Verify PIN';
    }
}

// PIN input handling
document.getElementById('productPinInput').addEventListener('input', function(e) {
    this.value = this.value.replace(/\D/g, '');
    
    if (this.value.length > 6) {
        this.value = this.value.slice(0, 6);
    }
    
    updateProductPinDisplay();
    document.getElementById('confirmProductPinBtn').disabled = this.value.length !== 6;
});

// Enter key submits PIN
document.getElementById('productPinInput').addEventListener('keyup', function(e) {
    if (e.key === 'Enter' && this.value.length === 6) {
        verifyProductPin();
    }
});

// Auto-focus PIN input when modal opens
document.getElementById('productPinModal').addEventListener('shown.bs.modal', function() {
    document.getElementById('productPinInput').focus();
});

// PIN Modal Styles
const style = document.createElement('style');
style.textContent = `
.pin-input-container {
    position: relative;
    margin: 20px 0;
}

.pin-input {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 50px;
    cursor: default;
}

.pin-display {
    display: flex;
    justify-content: center;
    gap: 10px;
    pointer-events: none;
}

.pin-dot {
    width: 40px;
    height: 40px;
    border: 2px solid #e0e0e0;
    border-radius: 50%;
    transition: all 0.3s ease;
    background: #f8f9fa;
}

.pin-dot.filled {
    background: #3498db;
    border-color: #3498db;
    transform: scale(1.1);
}

.pin-dot.filled::after {
    content: '•';
    color: white;
    font-size: 24px;
    line-height: 40px;
    text-align: center;
    display: block;
}
`;
document.head.appendChild(style);
</script>

<?php include '../includes/footer.php'; ?>