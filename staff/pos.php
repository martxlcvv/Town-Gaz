<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
prevent_cache();

$page_title = "POS";

// Function to verify admin PIN (simple verification)
function verifyAdminPin($pin) {
    global $conn;
    
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT pin_hash FROM admin_pins WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    
    if ($data && password_verify($pin, $data['pin_hash'])) {
        return true;
    }
    return false;
}

// Get all active products with current stock
$products_sql = "SELECT p.*, 
                 COALESCE(i.current_stock, 0) as current_stock
                 FROM products p
                 LEFT JOIN inventory i ON p.product_id = i.product_id AND i.date = CURDATE()
                 WHERE p.status = 'active' 
                 ORDER BY p.product_name";
$products_result = mysqli_query($conn, $products_sql);

// Get all customers for dropdown
$customers_sql = "SELECT * FROM customers WHERE status = 'active' ORDER BY customer_name";
$customers_result = mysqli_query($conn, $customers_sql);

// Get active promotions
$promos_sql = "SELECT * FROM promotions 
               WHERE status = 'active' 
               AND start_date <= NOW() 
               AND end_date >= NOW()
               ORDER BY promo_name";
$promos_result = mysqli_query($conn, $promos_sql);

// Handle sale submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complete_sale'])) {
    // Verify session token
    if (!verify_csrf_token($_POST['session_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid session. Please try again.']);
        exit();
    }
    
    mysqli_begin_transaction($conn);
    
    try {
        $customer_id = $_POST['customer_id'] == '' ? NULL : intval($_POST['customer_id']);
        $payment_method = clean_input($_POST['payment_method']);
        $cart_items = json_decode($_POST['cart_data'], true);
        $delivery_option = isset($_POST['delivery_option']) ? $_POST['delivery_option'] : 'pickup';
        
        // Promo code
        $promo_code = isset($_POST['promo_code']) ? clean_input($_POST['promo_code']) : '';
        $promo_discount = 0;
        $promo_id = NULL;
        
        // Payment details
        $amount_paid = floatval($_POST['amount_paid']);
        
        if (!empty($cart_items) && is_array($cart_items)) {
            $total_amount = 0;
            $total_capital = 0;
            $total_profit = 0;
            
            foreach ($cart_items as $item) {
                if (!isset($item['product_id'], $item['quantity'], $item['price'], $item['capital'])) {
                    throw new Exception('Invalid cart data');
                }
                
                $product_id = intval($item['product_id']);
                $quantity = intval($item['quantity']);
                
                if ($quantity <= 0) {
                    throw new Exception('Invalid quantity');
                }
                
                // Check stock availability
                $stock_check = "SELECT current_stock FROM inventory WHERE product_id = ? AND date = CURDATE()";
                $stock_stmt = mysqli_prepare($conn, $stock_check);
                mysqli_stmt_bind_param($stock_stmt, "i", $product_id);
                mysqli_stmt_execute($stock_stmt);
                $stock_result = mysqli_stmt_get_result($stock_stmt);
                $stock = mysqli_fetch_assoc($stock_result);
                
                if (!$stock || $stock['current_stock'] < $quantity) {
                    throw new Exception('Insufficient stock for product');
                }
                
                $verify_sql = "SELECT current_price, capital_cost FROM products WHERE product_id = ? AND status = 'active'";
                $stmt = mysqli_prepare($conn, $verify_sql);
                mysqli_stmt_bind_param($stmt, "i", $product_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $product = mysqli_fetch_assoc($result);
                
                if (!$product) {
                    throw new Exception('Product not found or inactive');
                }
                
                $price = floatval($product['current_price']);
                $capital = floatval($product['capital_cost']);
                
                $subtotal = $price * $quantity;
                $subtotal_capital = $capital * $quantity;
                $subtotal_profit = $subtotal - $subtotal_capital;
                
                $total_amount += $subtotal;
                $total_capital += $subtotal_capital;
                $total_profit += $subtotal_profit;
                
                $item['price'] = $price;
                $item['capital'] = $capital;
                $item['subtotal'] = $subtotal;
                $item['subtotal_capital'] = $subtotal_capital;
                $item['subtotal_profit'] = $subtotal_profit;
                $cart_items[array_search($item, $cart_items)] = $item;
            }

            // Apply promo code if provided
            if (!empty($promo_code)) {
                $promo_sql = "SELECT * FROM promotions 
                             WHERE promo_code = ? 
                             AND status = 'active' 
                             AND DATE(start_date) <= CURDATE() 
                             AND DATE(end_date) >= CURDATE()";
                $promo_stmt = mysqli_prepare($conn, $promo_sql);
                mysqli_stmt_bind_param($promo_stmt, "s", $promo_code);
                mysqli_stmt_execute($promo_stmt);
                $promo_result = mysqli_stmt_get_result($promo_stmt);
                $promo = mysqli_fetch_assoc($promo_result);
                
                if ($promo) {
                    $promo_id = $promo['promo_id'];
                    
                    if ($total_amount >= $promo['min_purchase_amount']) {
                        if ($promo['discount_type'] == 'percentage') {
                            $promo_discount = ($total_amount * $promo['discount_value']) / 100;
                            if ($promo['max_discount_amount'] > 0) {
                                $promo_discount = min($promo_discount, $promo['max_discount_amount']);
                            }
                        } else {
                            $promo_discount = $promo['discount_value'];
                        }
                        
                        $promo_discount = min($promo_discount, $total_amount);
                    }
                }
            }
            
            // Subtract discount from total
            $final_amount = $total_amount - $promo_discount;
            $total_profit = $total_profit - $promo_discount;
            
            // Validate payment
            if ($amount_paid < $final_amount) {
                throw new Exception('Insufficient payment amount');
            }
            
            $invoice_number = generate_invoice_number();
            
            $insert_sale_sql = "INSERT INTO sales (invoice_number, customer_id, user_id, total_amount, 
                                                  total_capital, total_profit, payment_method, status) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')";
            $stmt = mysqli_prepare($conn, $insert_sale_sql);
            mysqli_stmt_bind_param($stmt, "siiddds", $invoice_number, $customer_id, $_SESSION['user_id'], 
                                   $final_amount, $total_capital, $total_profit, $payment_method);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to create sale');
            }
            
            $sale_id = mysqli_insert_id($conn);
            
            // Save promo usage
            if ($promo_id && $promo_discount > 0) {
                $promo_usage_sql = "INSERT INTO sale_promotions (sale_id, promo_id, discount_amount) 
                                   VALUES (?, ?, ?)";
                $promo_stmt = mysqli_prepare($conn, $promo_usage_sql);
                mysqli_stmt_bind_param($promo_stmt, "iid", $sale_id, $promo_id, $promo_discount);
                mysqli_stmt_execute($promo_stmt);
            }
            
            $insert_item_sql = "INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, 
                                                       unit_capital, subtotal, subtotal_capital, subtotal_profit) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $item_stmt = mysqli_prepare($conn, $insert_item_sql);
            
            $update_inventory_sql = "UPDATE inventory 
                                    SET current_stock = current_stock - ? 
                                    WHERE product_id = ? AND date = CURDATE()";
            $inv_stmt = mysqli_prepare($conn, $update_inventory_sql);
            
            foreach ($cart_items as $item) {
                $product_id = intval($item['product_id']);
                $quantity = intval($item['quantity']);
                $unit_price = floatval($item['price']);
                $unit_capital = floatval($item['capital']);
                $subtotal = floatval($item['subtotal']);
                $subtotal_capital = floatval($item['subtotal_capital']);
                $subtotal_profit = floatval($item['subtotal_profit']);
                
                mysqli_stmt_bind_param($item_stmt, "iididddd", $sale_id, $product_id, $quantity, 
                                       $unit_price, $unit_capital, $subtotal, $subtotal_capital, $subtotal_profit);
                
                if (!mysqli_stmt_execute($item_stmt)) {
                    throw new Exception('Failed to add item to sale');
                }
                
                mysqli_stmt_bind_param($inv_stmt, "ii", $quantity, $product_id);
                
                if (!mysqli_stmt_execute($inv_stmt)) {
                    throw new Exception('Failed to update inventory');
                }
            }
            
            $insert_payment_sql = "INSERT INTO payments (sale_id, payment_method, amount_paid, reference_number) 
                                  VALUES (?, ?, ?, NULL)";
            $payment_stmt = mysqli_prepare($conn, $insert_payment_sql);
            mysqli_stmt_bind_param($payment_stmt, "isd", $sale_id, $payment_method, $amount_paid);
            
            if (!mysqli_stmt_execute($payment_stmt)) {
                throw new Exception('Failed to record payment');
            }
            
            // Create delivery record for all sales
            // Walk-in orders: delivery_option == 'pickup', customer_id = NULL, status = 'completed' (auto-complete)
            // Delivery orders: delivery_option == 'delivery', customer_id = NOT NULL, status = 'pending'
            $delivery_status = ($delivery_option == 'delivery') ? 'pending' : 'completed';
            $delivery_sql = "INSERT INTO deliveries (sale_id, customer_id, delivery_status, created_at) 
                            VALUES (?, ?, ?, NOW())";
            $delivery_stmt = mysqli_prepare($conn, $delivery_sql);
            mysqli_stmt_bind_param($delivery_stmt, "iis", $sale_id, $customer_id, $delivery_status);
            
            if (!mysqli_stmt_execute($delivery_stmt)) {
                throw new Exception('Failed to create delivery record');
            }
            
            mysqli_commit($conn);
            
            $_SESSION['success_message'] = "Sale completed successfully! Invoice: $invoice_number";
            $_SESSION['print_invoice'] = $sale_id;
            $_SESSION['change_amount'] = $amount_paid - $final_amount;
            $_SESSION['promo_discount'] = $promo_discount;
            header('Location: pos.php?print=' . $sale_id);
            exit();
            
        } else {
            throw new Exception('Cart is empty');
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header('Location: pos.php');
        exit();
    }
}

// Get receipt data for printing
$receipt_data = null;
$change_amount = 0;
$promo_discount = 0;
if (isset($_GET['print'])) {
    $sale_id = intval($_GET['print']);
    $receipt_sql = "SELECT s.*, c.customer_name, c.address, c.contact, u.full_name as cashier,
                    p.amount_paid, p.reference_number
                    FROM sales s
                    LEFT JOIN customers c ON s.customer_id = c.customer_id
                    JOIN users u ON s.user_id = u.user_id
                    LEFT JOIN payments p ON s.sale_id = p.sale_id
                    WHERE s.sale_id = ?";
    $stmt = mysqli_prepare($conn, $receipt_sql);
    mysqli_stmt_bind_param($stmt, "i", $sale_id);
    mysqli_stmt_execute($stmt);
    $receipt_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if ($receipt_data) {
        $items_sql = "SELECT si.*, p.product_name, p.size, p.unit
                      FROM sale_items si
                      JOIN products p ON si.product_id = p.product_id
                      WHERE si.sale_id = ?";
        $stmt = mysqli_prepare($conn, $items_sql);
        mysqli_stmt_bind_param($stmt, "i", $sale_id);
        mysqli_stmt_execute($stmt);
        $receipt_data['items'] = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        
        $promo_sql = "SELECT sp.discount_amount, pr.promo_name, pr.promo_code
                     FROM sale_promotions sp
                     JOIN promotions pr ON sp.promo_id = pr.promo_id
                     WHERE sp.sale_id = ?";
        $stmt = mysqli_prepare($conn, $promo_sql);
        mysqli_stmt_bind_param($stmt, "i", $sale_id);
        mysqli_stmt_execute($stmt);
        $promo_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $receipt_data['promo'] = $promo_data;
        
        if (isset($_SESSION['change_amount'])) {
            $change_amount = $_SESSION['change_amount'];
            unset($_SESSION['change_amount']);
        } else {
            $change_amount = $receipt_data['amount_paid'] - $receipt_data['total_amount'];
        }
        
        if (isset($_SESSION['promo_discount'])) {
            $promo_discount = $_SESSION['promo_discount'];
            unset($_SESSION['promo_discount']);
        } else if ($promo_data) {
            $promo_discount = $promo_data['discount_amount'];
        }
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- Sweet Alert Library -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.all.min.js"></script>

<style>
:root {
    --primary: #2d5016;
    --primary-light: #4a7c2c;
    --success: #51cf66;
    --danger: #ff6b6b;
    --warning: #ffd43b;
    --info: #4dabf7;
    --light: #f8f9fa;
    --dark: #212529;
    --border-radius: 10px;
    --shadow: 0 1px 3px rgba(0,0,0,0.08);
    --shadow-hover: 0 2px 8px rgba(0,0,0,0.1);
}

body {
    background: var(--light);
    font-size: 0.95rem;
}

/* ============================================
   PRODUCT CARDS
   ============================================ */
.product-card {
    transition: all 0.2s ease;
    border: 1px solid #e9ecef;
    cursor: pointer;
    border-radius: var(--border-radius);
    background: white;
    box-shadow: var(--shadow);
    height: 100%;
}

.product-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-hover);
    border-color: var(--primary);
}

.product-card.out-of-stock {
    opacity: 0.5;
    cursor: not-allowed;
    filter: grayscale(1);
}

.product-card .card-body {
    padding: 10px;
}

/* ============================================
   CART SIDEBAR
   ============================================ */
.cart-sidebar {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    height: fit-content;
    position: sticky;
    top: 20px;
}

.cart-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
    padding: 12px 14px;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
    font-weight: 600;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* ============================================
   COMPACT FORM CONTROLS
   ============================================ */
.compact-form {
    padding: 12px;
    background: #f8f9fa;
    border-radius: 0;
    margin: 0;
}

.compact-form .form-label {
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 4px;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 4px;
}

.compact-form .form-control,
.compact-form .form-select {
    font-size: 0.85rem;
    padding: 7px 10px;
    border-radius: 6px;
    border: 1px solid #dee2e6;
}

.compact-form .form-control:focus,
.compact-form .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(45, 80, 22, 0.08);
}

.compact-form small {
    font-size: 0.7rem;
    display: block;
    margin-top: 2px;
    color: #6c757d;
}

/* ============================================
   CUSTOMER INFO DISPLAY
   ============================================ */
.customer-info-box {
    background: rgba(45, 80, 22, 0.08);
    border-left: 3px solid var(--primary);
    padding: 8px 10px;
    border-radius: 6px;
    margin: 0 12px 12px 12px;
    font-size: 0.8rem;
}

.customer-info-box .info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
    color: var(--dark);
}

.customer-info-box .info-row:last-child {
    margin-bottom: 0;
}

.customer-info-box .info-label {
    font-weight: 600;
    color: var(--primary);
}

.customer-info-box .info-value {
    text-align: right;
    color: #6c757d;
    word-break: break-word;
}

/* ============================================
   PROMO INPUT GROUP
   ============================================ */
.promo-input-group {
    display: flex;
    gap: 6px;
    padding: 0 12px;
}

.promo-input-group input {
    flex: 1;
    font-size: 0.85rem;
    padding: 7px 10px;
}

.promo-input-group button {
    padding: 7px 12px;
    font-size: 0.8rem;
    border-radius: 6px;
    white-space: nowrap;
}

/* ============================================
   CART ITEMS
   ============================================ */
.cart-items-container {
    max-height: 300px;
    overflow-y: auto;
    padding: 10px;
    background: white;
}

.cart-item-compact {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 8px;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
}

.cart-item-img,
.cart-item-img-placeholder {
    width: 45px;
    height: 45px;
    border-radius: 6px;
    flex-shrink: 0;
}

.cart-item-img {
    object-fit: cover;
}

.cart-item-img-placeholder {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
}

.cart-item-info {
    flex: 1;
    min-width: 0;
}

.cart-item-name {
    font-weight: 600;
    font-size: 0.85rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 2px;
}

.cart-item-details {
    font-size: 0.75rem;
    color: #6c757d;
    margin-bottom: 4px;
}

.cart-item-price {
    font-weight: 600;
    color: var(--success);
    font-size: 0.9rem;
}

/* ============================================
   QUANTITY CONTROLS
   ============================================ */
.qty-controls {
    display: flex;
    align-items: center;
    gap: 4px;
}

.qty-btn {
    width: 26px;
    height: 26px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
    font-size: 0.8rem;
    font-weight: 700;
    border: 1px solid;
}

.qty-value {
    font-weight: 600;
    font-size: 0.85rem;
    min-width: 28px;
    text-align: center;
}

.remove-btn {
    width: 26px;
    height: 26px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
    flex-shrink: 0;
}

/* ============================================
   CART TOTALS
   ============================================ */
.cart-totals {
    padding: 12px;
    background: #f8f9fa;
    border-radius: 0;
    font-size: 0.85rem;
}

.total-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 6px;
    align-items: center;
}

.total-row.main {
    font-size: 1rem;
    font-weight: 700;
    padding-top: 8px;
    border-top: 2px solid #dee2e6;
    color: var(--success);
    margin-top: 6px;
}

/* ============================================
   BUTTONS
   ============================================ */
.btn-compact {
    padding: 10px 16px;
    font-size: 0.9rem;
    font-weight: 600;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-checkout-compact {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    transition: all 0.2s ease;
}

.btn-checkout-compact:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(45, 80, 22, 0.2);
    color: white;
}

.btn-checkout-compact:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-clear-compact {
    background: white;
    color: var(--danger);
    border: 1px solid var(--danger);
}

.btn-clear-compact:hover {
    background: var(--danger);
    color: white;
}

/* ============================================
   EMPTY CART
   ============================================ */
.empty-cart {
    text-align: center;
    padding: 40px 20px;
    color: #adb5bd;
}

.empty-cart i {
    font-size: 3rem;
    margin-bottom: 10px;
    opacity: 0.5;
}

.empty-cart p {
    font-size: 0.9rem;
    margin: 0;
}

/* ============================================
   STOCK BADGES
   ============================================ */
.stock-badge-compact {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 600;
}

.stock-badge-compact.low {
    background: #ffe0e0;
    color: var(--danger);
}

.stock-badge-compact.in {
    background: #d3f9d8;
    color: #2b8a3e;
}

/* ============================================
   FLOATING CART BUTTON
   ============================================ */
.floating-cart-btn {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1060;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(45, 80, 22, 0.4);
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    transition: all 0.3s ease;
}

.floating-cart-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 16px rgba(45, 80, 22, 0.5);
}

.cart-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: var(--danger);
    color: white;
    border-radius: 50%;
    width: 26px;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: bold;
    border: 2px solid white;
}

/* ============================================
   CART BACKDROP
   ============================================ */
.cart-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1054;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.cart-backdrop.show {
    display: block;
    opacity: 1;
}

/* ============================================
   CART CLOSE BUTTON
   ============================================ */
.cart-close-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    display: none;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 18px;
}

.cart-close-btn:hover {
    background: rgba(255,255,255,0.3);
    transform: scale(1.1);
}

/* ============================================
   ALERT STYLES
   ============================================ */
.alert-compact {
    padding: 10px 14px;
    font-size: 0.9rem;
    border-radius: 8px;
    margin-bottom: 16px;
}

/* ============================================
   SCROLLBAR STYLING
   ============================================ */
.cart-items-container::-webkit-scrollbar,
.card-body.overflow-auto::-webkit-scrollbar {
    width: 8px;
}

.cart-items-container::-webkit-scrollbar-track,
.card-body.overflow-auto::-webkit-scrollbar-track {
    background: #f1f3f5;
    border-radius: 10px;
}

.cart-items-container::-webkit-scrollbar-thumb,
.card-body.overflow-auto::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 10px;
}

.cart-items-container::-webkit-scrollbar-thumb:hover,
.card-body.overflow-auto::-webkit-scrollbar-thumb:hover {
    background: var(--primary-light);
}

/* ============================================
   PRINT STYLES
   ============================================ */
@media print {
    body * {
        visibility: hidden;
    }
    #receiptPrint, #receiptPrint * {
        visibility: visible;
    }
    #receiptPrint {
        position: absolute;
        left: 0;
        top: 0;
        width: 80mm;
    }
    .no-print {
        display: none !important;
    }
}

.receipt-container {
    width: 80mm;
    margin: 0 auto;
    padding: 20px;
    font-family: 'Courier New', monospace;
    background: white;
}

/* ============================================
   TABLET RESPONSIVE (768px - 991px)
   ============================================ */
@media (max-width: 991px) {
    .main-content {
        padding-bottom: 100px !important;
    }
    
    .floating-cart-btn {
        display: flex;
    }
    
    .cart-close-btn {
        display: flex;
    }
    
    .cart-sidebar {
        position: fixed;
        bottom: -100%;
        left: 0;
        right: 0;
        z-index: 1055;
        border-radius: 20px 20px 0 0;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
        margin: 0;
        transition: bottom 0.3s ease-in-out;
        top: auto;
    }
    
    .cart-sidebar.show {
        bottom: 0;
    }
    
    .cart-items-container {
        max-height: 40vh !important;
    }
    
    .col-md-6.col-xl-4 {
        flex: 0 0 50%;
        max-width: 50%;
    }
}

/* ============================================
   MOBILE RESPONSIVE (< 768px)
   ============================================ */
@media (max-width: 768px) {
    .col-md-6.col-xl-4 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    .product-card .card-body {
        padding: 12px;
    }
    
    .product-card h6 {
        font-size: 0.9rem !important;
    }
    
    .product-card img,
    .product-card > div > div:first-child > div {
        width: 55px !important;
        height: 55px !important;
    }
    
    .stock-badge-compact {
        font-size: 0.7rem;
        padding: 2px 8px;
    }
    
    .cart-sidebar {
        max-height: 85vh;
    }
    
    .cart-header {
        padding: 14px 16px;
        font-size: 0.95rem;
    }
    
    .compact-form {
        padding: 12px;
    }
    
    .compact-form .form-label {
        font-size: 0.8rem;
    }
    
    .compact-form .form-control,
    .compact-form .form-select {
        font-size: 0.85rem;
        padding: 7px 10px;
    }
    
    .cart-items-container {
        max-height: 35vh !important;
        padding: 10px;
    }
    
    .cart-item-compact {
        padding: 8px;
        gap: 8px;
    }
    
    .cart-item-img,
    .cart-item-img-placeholder {
        width: 45px !important;
        height: 45px !important;
    }
    
    .cart-totals {
        padding: 12px;
        font-size: 0.85rem;
    }
    
    .total-row.main {
        font-size: 1rem;
    }
    
    .btn-compact {
        padding: 10px 16px;
        font-size: 0.9rem;
    }
    
    .floating-cart-btn {
        width: 58px;
        height: 58px;
        bottom: 18px;
        right: 18px;
    }
}

/* ============================================
   SMALL MOBILE (< 576px)
   ============================================ */
@media (max-width: 576px) {
    .main-content {
        padding: 12px !important;
        padding-bottom: 100px !important;
    }
    
    .card-header h6 {
        font-size: 0.95rem;
    }
    
    .card-body[style*="max-height"] {
        max-height: calc(100vh - 250px) !important;
        min-height: 400px;
    }
    
    .product-card .card-body {
        padding: 10px;
    }
    
    .product-card h6 {
        font-size: 0.85rem !important;
    }
    
    .product-card p {
        font-size: 0.75rem !important;
    }
    
    .product-card img,
    .product-card > div > div:first-child > div {
        width: 50px !important;
        height: 50px !important;
    }
    
    .cart-sidebar {
        max-height: 90vh;
    }
    
    .cart-header {
        padding: 12px 14px;
        font-size: 0.9rem;
    }
    
    .cart-close-btn {
        width: 32px;
        height: 32px;
        font-size: 16px;
    }
    
    .compact-form {
        padding: 10px;
    }
    
    .compact-form .form-label {
        font-size: 0.75rem;
        margin-bottom: 4px;
    }
    
    .compact-form .form-control,
    .compact-form .form-select {
        font-size: 0.8rem;
        padding: 6px 8px;
    }
    
    .compact-form small {
        font-size: 0.7rem;
    }
    
    .promo-input-group button {
        padding: 6px 12px;
        font-size: 0.75rem;
    }
    
    .cart-items-container {
        max-height: 30vh !important;
        padding: 8px;
    }
    
    .cart-item-compact {
        padding: 8px;
        gap: 8px;
        font-size: 0.85rem;
    }
    
    .cart-item-img,
    .cart-item-img-placeholder {
        width: 40px !important;
        height: 40px !important;
    }
    
    .cart-item-name {
        font-size: 0.85rem;
    }
    
    .cart-item-details {
        font-size: 0.75rem;
    }
    
    .cart-item-price {
        font-size: 0.85rem;
    }
    
    .qty-btn {
        width: 26px;
        height: 26px;
        font-size: 0.8rem;
    }
    
    .qty-value {
        font-size: 0.85rem;
        min-width: 26px;
    }
    
    .remove-btn {
        width: 26px;
        height: 26px;
    }
    
    .cart-totals {
        padding: 10px;
        font-size: 0.8rem;
    }
    
    .total-row {
        margin-bottom: 6px;
    }
    
    .total-row.main {
        font-size: 0.95rem;
        padding-top: 8px;
    }
    
    .btn-compact {
        padding: 10px 14px;
        font-size: 0.85rem;
        gap: 6px;
    }
    
    .alert-compact {
        font-size: 0.8rem;
        padding: 8px 12px;
        margin-bottom: 12px;
    }
    
    .alert-success {
        background-color: #d3f9d8 !important;
        color: #2b8a3e !important;
        border: 1px solid #51cf66 !important;
    }
    
    .alert-success .alert-link {
        color: #2b8a3e !important;
        font-weight: 600;
    }
    
    .alert-warning {
        background-color: #fff3bf !important;
        color: #856404 !important;
        border: 1px solid #ffc107 !important;
    }
    
    .alert-danger {
        background-color: #ffe0e0 !important;
        color: #c41e3a !important;
        border: 1px solid #ff6b6b !important;
    }
    
    .alert-info {
        background-color: #d0e8f2 !important;
        color: #0c5460 !important;
        border: 1px solid #0c7aa5 !important;
    }
    
    .alert-light {
        background-color: #f8f9fa !important;
        color: #383d41 !important;
        border: 1px solid #dee2e6 !important;
    }
    
    .empty-cart {
        padding: 40px 15px;
    }
    
    .empty-cart i {
        font-size: 3rem;
    }
    
    .empty-cart p {
        font-size: 0.9rem;
    }
    
    .modal-dialog {
        margin: 12px;
    }
    
    .modal-header {
        padding: 12px 16px;
    }
    
    .modal-header h6 {
        font-size: 0.95rem;
    }
    
    .modal-body {
        padding: 14px;
    }
    
    .payment-option {
        padding: 14px;
    }
    
    .payment-option i {
        font-size: 2.5rem !important;
    }
    
    .payment-option h6 {
        font-size: 0.9rem;
    }
    
    .payment-option small {
        font-size: 0.75rem;
    }
    
    .floating-cart-btn {
        width: 56px;
        height: 56px;
        bottom: 16px;
        right: 16px;
        font-size: 22px;
    }
    
    .cart-badge {
        width: 24px;
        height: 24px;
        font-size: 12px;
    }
}

/* ============================================
   EXTRA SMALL SCREENS (< 400px)
   ============================================ */
@media (max-width: 399px) {
    .main-content {
        padding: 10px !important;
        padding-bottom: 90px !important;
    }
    
    .product-card .card-body {
        padding: 8px;
    }
    
    .product-card img,
    .product-card > div > div:first-child > div {
        width: 45px !important;
        height: 45px !important;
    }
    
    .product-card h6 {
        font-size: 0.8rem !important;
    }
    
    .product-card p {
        font-size: 0.7rem !important;
    }
    
    .product-card h6.text-success {
        font-size: 0.8rem !important;
    }
    
    .cart-item-compact {
        padding: 6px;
        gap: 6px;
    }
    
    .cart-item-img,
    .cart-item-img-placeholder {
        width: 38px !important;
        height: 38px !important;
    }
    
    .cart-item-info > div:last-child {
        flex-wrap: wrap;
        gap: 8px !important;
    }
    
    .floating-cart-btn {
        width: 52px;
        height: 52px;
        bottom: 14px;
        right: 14px;
        font-size: 20px;
    }
}

/* ============================================
   LANDSCAPE MODE ADJUSTMENTS
   ============================================ */
@media (max-width: 767px) and (orientation: landscape) {
    .cart-sidebar {
        max-height: 95vh;
    }
    
    .cart-items-container {
        max-height: 25vh !important;
    }
    
    .cart-header {
        padding: 10px 14px;
    }
    
    .compact-form {
        padding: 8px 12px;
    }
    
    .cart-totals {
        padding: 8px 12px;
    }
}

/* ============================================
   SMOOTH SCROLLING
   ============================================ */
.cart-sidebar,
.card-body.overflow-auto {
    -webkit-overflow-scrolling: touch;
    scroll-behavior: smooth;
}

/* ============================================
   FIX BOOTSTRAP COLUMN RESPONSIVENESS
   ============================================ */
@media (min-width: 576px) and (max-width: 767px) {
    .col-md-6.col-xl-4 {
        flex: 0 0 50%;
        max-width: 50%;
    }
}

@media (min-width: 768px) and (max-width: 991px) {
    .col-md-6.col-xl-4 {
        flex: 0 0 50%;
        max-width: 50%;
    }
}

@media (min-width: 992px) and (max-width: 1199px) {
    .col-md-6.col-xl-4 {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
    }
}

@media (min-width: 1200px) {
    .col-md-6.col-xl-4 {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
    }
}
</style>
<!-- FULL POS STRUCTURE - Replace from <div class="main-content"> to end -->

<div class="main-content">
    <div class="container-fluid">
        
        <!-- ========================================== -->
        <!-- RECEIPT SECTION (PRINT VIEW ONLY) -->
        <!-- ========================================== -->
        <?php if (isset($_GET['print'])): ?>
        
        <div id="receiptPrint" class="receipt-container">
            <div style="text-align: center; border-bottom: 2px dashed #000; padding-bottom: 10px; margin-bottom: 10px;">
                <div style="font-size: 24px; font-weight: bold;">TOWN GAS STORE</div>
                <div style="margin-top: 10px;"><strong>OFFICIAL RECEIPT</strong></div>
            </div>
            
            <div style="margin: 15px 0; font-size: 12px;">
                <div style="display: flex; justify-content: space-between;">
                    <span>Invoice #:</span>
                    <strong><?php echo htmlspecialchars($receipt_data['invoice_number']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Date:</span>
                    <span><?php echo date('M d, Y h:i A', strtotime($receipt_data['created_at'])); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Cashier:</span>
                    <span><?php echo htmlspecialchars($receipt_data['cashier']); ?></span>
                </div>
                <?php if ($receipt_data['customer_name']): ?>
                <div style="display: flex; justify-content: space-between;">
                    <span>Customer:</span>
                    <span><?php echo htmlspecialchars($receipt_data['customer_name']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div style="border-bottom: 2px dashed #000; padding: 10px 0;">
                <table style="width: 100%; font-size: 12px;">
                    <thead>
                        <tr style="border-bottom: 1px solid #000;">
                            <th style="text-align: left;">Item</th>
                            <th style="text-align: center;">Qty</th>
                            <th style="text-align: right;">Price</th>
                            <th style="text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($receipt_data['items'] as $item): ?>
                        <tr>
                            <td style="padding: 5px 0;">
                                <?php echo htmlspecialchars($item['product_name']); ?><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($item['size']); ?> <?php echo htmlspecialchars($item['unit']); ?></small>
                            </td>
                            <td style="text-align: center;"><?php echo $item['quantity']; ?></td>
                            <td style="text-align: right;">₱<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td style="text-align: right;">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="border-top: 2px solid #000; padding-top: 10px; margin-top: 10px; font-weight: bold; font-size: 14px;">
                <div style="display: flex; justify-content: space-between; font-size: 18px; margin-bottom: 5px;">
                    <span>TOTAL:</span>
                    <span>₱<?php echo number_format($receipt_data['total_amount'], 2); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Payment:</span>
                    <span><?php echo strtoupper($receipt_data['payment_method']); ?></span>
                </div>
                <?php if ($receipt_data['payment_method'] == 'cash'): ?>
                <div style="display: flex; justify-content: space-between;">
                    <span>Amount Paid:</span>
                    <span>₱<?php echo number_format($receipt_data['amount_paid'], 2); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 16px; margin-top: 5px;">
                    <span>CHANGE:</span>
                    <span>₱<?php echo number_format($change_amount, 2); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center; margin-top: 20px; padding-top: 10px; border-top: 2px dashed #000;">
                <p style="margin: 5px 0;">Thank you for your purchase!</p>
                <p style="margin: 5px 0; font-size: 10px;">This serves as your official receipt</p>
            </div>
        </div>
        
        <div class="text-center mt-4 no-print">
            <button onclick="window.print()" class="btn btn-primary btn-lg me-2">
                <i class="bi bi-printer"></i> Print Receipt
            </button>
            <a href="pos.php" class="btn btn-secondary btn-lg">
                <i class="bi bi-arrow-left"></i> New Transaction
            </a>
        </div>
        
        <!-- ========================================== -->
        <!-- POS INTERFACE (DEFAULT VIEW) -->
        <!-- ========================================== -->
        <?php else: ?>
        
        <div class="row mb-3">
            <div class="col">
                <h4 class="mb-0"><i class="bi bi-cart-check me-2"></i>Point of Sale</h4>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-compact alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-compact alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <div class="row">
            <!-- ========================================== -->
            <!-- LEFT COLUMN: PRODUCTS SECTION -->
            <!-- ========================================== -->
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-grid me-2"></i>Products</h6>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <div class="row g-3">
                            <?php 
                            mysqli_data_seek($products_result, 0);
                            while ($product = mysqli_fetch_assoc($products_result)): 
                            ?>
                                <div class="col-md-6 col-xl-4">
                                    <div class="card product-card h-100 <?php echo $product['current_stock'] < 1 ? 'out-of-stock' : ''; ?>" 
                                         data-product-id="<?php echo $product['product_id']; ?>"
                                         data-current-stock="<?php echo $product['current_stock']; ?>"
                                         data-product='<?php echo json_encode($product, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                         <?php echo $product['current_stock'] < 1 ? '' : 'onclick="handleProductClick(this)"'; ?>>
                                        <div class="card-body p-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <div>
                                                    <?php if ($product['image_path'] && file_exists('../' . $product['image_path'])): ?>
                                                        <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" 
                                                             alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                                                    <?php else: ?>
                                                        <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #2d5016, #4a7c2c); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                            <i class="bi bi-droplet-fill text-white" style="font-size: 24px;"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow-1 min-w-0">
                                                    <h6 class="mb-1 text-truncate" style="font-size: 0.9rem;"><?php echo htmlspecialchars($product['product_name']); ?></h6>
                                                    <p class="mb-1 text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($product['size']); ?> <?php echo htmlspecialchars($product['unit'] ?? 'kg'); ?></p>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <h6 class="mb-0 text-success fw-bold" style="font-size: 0.95rem;">₱<?php echo number_format($product['current_price'], 2); ?></h6>
                                                        <span class="stock-badge-compact <?php echo $product['current_stock'] < 20 ? 'low' : 'in'; ?>">
                                                            <?php echo $product['current_stock']; ?> pcs
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ========================================== -->
            <!-- RIGHT COLUMN: CART SIDEBAR -->
            <!-- ========================================== -->
            <div class="col-lg-4">
                <div class="cart-sidebar">
                    <div class="cart-header">
                        <i class="bi bi-cart3 me-2"></i>Shopping Cart
                        <!-- Close button will be added by JavaScript on mobile -->
                    </div>
                    
                    <form method="POST" id="posForm">
                        <!-- Customer, Promo, Delivery Options -->
                        <div class="compact-form">
                            <div class="mb-2">
                                <label class="form-label">
                                    <i class="bi bi-person-fill"></i> Customer
                                </label>
                                <select name="customer_id" id="customer_id" class="form-select">
                                    <option value="">Walk-in</option>
                                    <?php 
                                    mysqli_data_seek($customers_result, 0);
                                    while ($customer = mysqli_fetch_assoc($customers_result)): 
                                    ?>
                                        <option value="<?php echo $customer['customer_id']; ?>">
                                            <?php echo htmlspecialchars($customer['customer_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <!-- Customer Info Box -->
                            <div id="customerInfoBox" class="customer-info-box" style="display: none; margin-bottom: 12px;">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi bi-info-circle text-primary" style="margin-top: 2px;"></i>
                                    <div style="flex: 1;">
                                        <strong id="customerName" style="display: block; font-size: 0.85rem; margin-bottom: 2px;"></strong>
                                        <small id="customerContact" class="text-muted" style="display: block; line-height: 1.3;"></small>
                                        <small id="customerAddress" class="text-muted" style="display: block; line-height: 1.3;"></small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label">
                                    <i class="bi bi-tag-fill"></i> Promo Code
                                </label>
                                <div class="promo-input-group">
                                    <input type="text" class="form-control" id="promoCode" placeholder="Enter code">
                                    <button class="btn btn-primary" type="button" onclick="applyPromo()">
                                        <i class="bi bi-check"></i>
                                    </button>
                                </div>
                                <div id="promoMessage"></div>
                            </div>
                            
                            <div class="mb-0">
                                <label class="form-label">
                                    <i class="bi bi-truck"></i> Delivery
                                </label>
                                <select name="delivery_option" id="delivery_option" class="form-select">
                                    <option value="pickup">Pick-up</option>
                                    <option value="delivery">Delivery</option>
                                </select>
                                <small class="text-muted">*Delivery for registered customers</small>
                            </div>
                        </div>
                        
                        <!-- Cart Items -->
                        <div class="cart-items-container" id="cartItems">
                            <div class="empty-cart" id="emptyCart">
                                <i class="bi bi-cart-x"></i>
                                <p class="mb-0" style="font-size: 0.85rem;">Cart is empty</p>
                            </div>
                        </div>
                        
                        <!-- Totals -->
                        <div class="cart-totals">
                            <div id="displayTotal">
                                <div class="total-row main">
                                    <span>TOTAL:</span>
                                    <span>₱0.00</span>
                                </div>
                            </div>
                        </div>
                        
                        <input type="hidden" name="cart_data" id="cartData">
                        
                        <!-- Action Buttons -->
                        <div class="p-2">
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-checkout-compact btn-compact" id="proceedBtn" 
                                        onclick="showPaymentModal()" disabled>
                                    <i class="bi bi-credit-card"></i>
                                    <span>Proceed to Payment</span>
                                </button>
                                <button type="button" class="btn btn-clear-compact btn-compact" onclick="clearCart()">
                                    <i class="bi bi-trash"></i>
                                    <span>Clear Cart</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
        </div> <!-- End of row -->
        
        <!-- ========================================== -->
        <!-- ✅ FLOATING CART BUTTON (MOBILE ONLY) -->
        <!-- ========================================== -->
        <button class="floating-cart-btn" id="floatingCartBtn" onclick="toggleCart()">
            <i class="bi bi-cart3"></i>
            <span class="cart-badge" id="cartBadge" style="display: none;">0</span>
        </button>
        
        <!-- ========================================== -->
        <!-- ✅ CART BACKDROP (MOBILE ONLY) -->
        <!-- ========================================== -->
        <div class="cart-backdrop" id="cartBackdrop" onclick="toggleCart()"></div>
        
        <?php endif; ?>
        <!-- End of POS Interface -->
        
    </div> <!-- End of container-fluid -->
</div> <!-- End of main-content -->

<!-- ========================================== -->
<!-- PIN VERIFICATION MODAL -->
<!-- ========================================== -->
<div class="modal fade" id="pinModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h6 class="modal-title mb-0"><i class="bi bi-shield-lock me-2"></i>PIN Verification Required</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p class="text-muted mb-3">Enter your PIN to continue</p>
                <div class="pin-input-container mb-3">
                    <input type="password" id="pinInput" maxlength="6" pattern="\d*" 
                           class="pin-input form-control form-control-lg text-center" 
                           placeholder="●●●●●●" autocomplete="off" inputmode="numeric" style="letter-spacing: 8px; font-size: 1.5rem; font-weight: bold; text-align: center;">
                    <div class="pin-display mt-3" id="pinDisplay">
                        <div class="pin-dot"></div>
                        <div class="pin-dot"></div>
                        <div class="pin-dot"></div>
                        <div class="pin-dot"></div>
                        <div class="pin-dot"></div>
                        <div class="pin-dot"></div>
                    </div>
                </div>
                <small class="text-danger" id="pinError"></small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirmPinBtn" onclick="verifyPin()">Verify</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- PAYMENT MODAL (OUTSIDE MAIN CONTENT) -->
<!-- ========================================== -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title mb-0"><i class="bi bi-wallet2 me-2"></i>Payment Method</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info alert-compact mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong>Total Amount:</strong>
                        <h5 class="mb-0 fw-bold" id="modalTotal">₱0.00</h5>
                    </div>
                </div>
                
                <!-- Payment Options -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="payment-option" onclick="selectPayment('cash')">
                            <div class="text-center">
                                <i class="bi bi-cash-coin display-4 text-success mb-2"></i>
                                <h6 class="fw-bold mb-1">Cash</h6>
                                <small class="text-muted">Pay with cash</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="payment-option" onclick="selectPayment('gcash')">
                            <div class="text-center">
                                <i class="bi bi-phone display-4 text-primary mb-2"></i>
                                <h6 class="fw-bold mb-1">GCash</h6>
                                <small class="text-muted">Scan QR code</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Cash Payment -->
                <div id="cashPaymentForm" style="display: none;">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body" style="padding: 16px; min-height: 200px;">
                            <h6 class="fw-bold mb-3">
                                <i class="bi bi-cash-stack text-success me-2"></i>Cash Payment
                            </h6>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Amount Received</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" class="form-control" id="cashAmount" 
                                           step="0.01" min="0" placeholder="0.00" oninput="calculateChange()">
                                </div>
                            </div>
                            <div class="alert alert-success d-flex justify-content-between align-items-center" 
                                 id="changeDisplay" style="display: none !important; margin-top: 12px; margin-bottom: 0; visibility: visible; padding: 12px 14px;">
                                <strong>Change:</strong>
                                <h5 class="mb-0 fw-bold" id="changeAmount">₱0.00</h5>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- GCash Payment -->
                <div id="gcashPaymentForm" style="display: none;">
                    <div class="card border-0 shadow-sm bg-primary text-white">
                        <div class="card-body text-center">
                            <h6 class="fw-bold mb-3">
                                <i class="bi bi-qr-code me-2"></i>Scan to Pay
                            </h6>
                            <div class="bg-white p-3 rounded mb-3 d-inline-block">
                                <img src="../assets/images/gcash temp.jpg" 
                                     alt="GCash QR" 
                                     style="max-width: 200px; height: auto; border: 2px solid #007bff; border-radius: 8px;">
                            </div>
                            <div class="bg-white bg-opacity-25 rounded p-2 mb-2">
                                <i class="bi bi-phone-fill me-2"></i>
                                <strong>0930 894 0224</strong>
                            </div>
                            <small>Scan with GCash app to complete payment</small>
                        </div>
                    </div>
                    <div class="alert alert-light alert-compact mt-3 mb-0 text-center">
                        <i class="bi bi-check-circle text-success me-2"></i>
                        <strong>After payment, click "Confirm Payment"</strong>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-success" id="confirmPaymentBtn" onclick="confirmPayment()" disabled>
                    <i class="bi bi-check-circle me-2"></i>Confirm Payment
                </button>
            </div>
        </div>
    </div>
</div>


 <?php include '../includes/footer.php'; ?>


<script>
let cart = [];
let paymentModal;
let pinModal;
let selectedPaymentMethod = null;
let appliedPromo = null;
let totalAmount = 0;
let subtotalAmount = 0;
let pendingAction = null;
let pinAttempts = 0;
let pendingQuantityIndex = null;
let pinLockoutTimer = null;
let pinLocked = false;
const MAX_PIN_ATTEMPTS = 3;
const PIN_LOCKOUT_SECONDS = 30;

// Handle product card clicks
function handleProductClick(element) {
    const productData = element.getAttribute('data-product');
    if (productData) {
        try {
            const product = JSON.parse(productData);
            addToCart(product);
        } catch (e) {
            console.error('Error parsing product data:', e);
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
    pinModal = new bootstrap.Modal(document.getElementById('pinModal'));
    
    const pinInput = document.getElementById('pinInput');
    if (pinInput) {
        pinInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
            if (this.value.length > 6) this.value = this.value.slice(0, 6);
            updatePinDisplay();
        });
        
        pinInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter' && this.value.length === 6) verifyPin();
        });
    }
    
    const deliveryOption = document.getElementById('delivery_option');
    if (deliveryOption) {
        deliveryOption.addEventListener('change', function() {
            const customerId = document.getElementById('customer_id').value;
            if (this.value === 'delivery' && !customerId) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Select Customer',
                    text: 'Please select a customer for delivery',
                    confirmButtonColor: '#ffd43b',
                    confirmButtonText: 'OK'
                });
                this.value = 'pickup';
            }
        });
    }
    
    const customerId = document.getElementById('customer_id');
    if (customerId) {
        customerId.addEventListener('change', function() {
            displayCustomerInfo();
        });
    }
    
    // Real-time change calculation on cash input
    const cashAmountInput = document.getElementById('cashAmount');
    if (cashAmountInput) {
        cashAmountInput.addEventListener('input', function() {
            calculateChange();
        });
    }
    
    initMobileCart();
});

function initMobileCart() {
    updateCartBadge();
    addCloseButton();
    window.addEventListener('resize', addCloseButton);
}

function updatePinDisplay() {
    const pin = document.getElementById('pinInput').value;
    const dots = document.querySelectorAll('#pinDisplay .pin-dot');
    
    dots.forEach((dot, index) => {
        if (index < pin.length) {
            dot.classList.add('filled');
        } else {
            dot.classList.remove('filled');
        }
    });
}

function requestPin(action) {
    pendingAction = action;
    pinAttempts = 0;
    
    const pinInput = document.getElementById('pinInput');
    const pinError = document.getElementById('pinError');
    const confirmBtn = document.getElementById('confirmPinBtn');
    
    if (pinInput) pinInput.value = '';
    if (pinError) pinError.textContent = '';
    if (confirmBtn) confirmBtn.disabled = false;
    
    updatePinDisplay();
    
    if (pinModal) {
        pinModal.show();
        setTimeout(() => pinInput?.focus(), 200);
    }
}

async function verifyPin() {
    const pin = document.getElementById('pinInput').value;
    const pinError = document.getElementById('pinError');
    const confirmBtn = document.getElementById('confirmPinBtn');
    
    if (pin.length !== 6) {
        if (pinError) pinError.textContent = 'PIN must be 6 digits';
        return;
    }
    
    if (pinLocked) {
        if (pinError) pinError.textContent = 'Too many attempts. Please wait...';
        return;
    }
    
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Verifying...';
    
    try {
        const response = await fetch('../api/verify-pin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'pin=' + encodeURIComponent(pin)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Reset attempts on success
            pinAttempts = 0;
            pinLocked = false;
            
            if (pinModal) pinModal.hide();
            
            Swal.fire({
                icon: 'success',
                title: 'PIN Verified!',
                text: 'Proceeding with ' + pendingAction,
                timer: 1500,
                showConfirmButton: false
            });
            
            executePendingAction();
        } else {
            pinAttempts++;
            
            if (pinAttempts >= MAX_PIN_ATTEMPTS) {
                pinLocked = true;
                
                Swal.fire({
                    icon: 'error',
                    title: 'Maximum Attempts Exceeded',
                    text: 'Too many incorrect attempts. Please wait 30 seconds before trying again.',
                    confirmButtonColor: '#ff6b6b'
                });
                
                startPinLockout();
                
                const pinInput = document.getElementById('pinInput');
                const pinDisplay = document.getElementById('pinDisplay');
                if (pinInput) pinInput.disabled = true;
                if (pinDisplay) pinDisplay.style.opacity = '0.5';
                
            } else {
                if (pinError) {
                    pinError.textContent = `Incorrect PIN. ${MAX_PIN_ATTEMPTS - pinAttempts} attempt(s) remaining`;
                }
                
                const pinInput = document.getElementById('pinInput');
                if (pinInput) {
                    pinInput.value = '';
                    updatePinDisplay();
                    pinInput.focus();
                }
            }
        }
    } catch (error) {
        console.error('Error:', error);
        if (pinError) pinError.textContent = 'Connection error. Please try again.';
    } finally {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Verify';
    }
}

function startPinLockout() {
    let secondsRemaining = PIN_LOCKOUT_SECONDS;
    const pinError = document.getElementById('pinError');
    const confirmBtn = document.getElementById('confirmPinBtn');
    const pinInput = document.getElementById('pinInput');
    
    if (pinLockoutTimer) clearInterval(pinLockoutTimer);
    
    const updateCountdown = () => {
        if (pinError) {
            pinError.innerHTML = `<i class="bi bi-exclamation-triangle me-2"></i>Too many attempts. Try again in <strong>${secondsRemaining}</strong>s`;
        }
        
        if (confirmBtn) confirmBtn.disabled = true;
    };
    
    updateCountdown();
    
    pinLockoutTimer = setInterval(() => {
        secondsRemaining--;
        
        if (secondsRemaining > 0) {
            updateCountdown();
        } else {
            // Lockout period over - reset
            clearInterval(pinLockoutTimer);
            pinLocked = false;
            pinAttempts = 0;
            
            if (pinError) {
                pinError.textContent = 'Lockout period ended. You can try again.';
                pinError.style.color = '#51cf66';
            }
            
            if (confirmBtn) confirmBtn.disabled = false;
            if (pinInput) {
                pinInput.disabled = false;
                pinInput.value = '';
                updatePinDisplay();
                pinInput.focus();
            }
            
            const pinDisplay = document.getElementById('pinDisplay');
            if (pinDisplay) pinDisplay.style.opacity = '1';
        }
    }, 1000);
}

function executePendingAction() {
    if (pendingAction === 'Clear Cart') {
        clearCartConfirmed();
    } else if (pendingAction === 'Cancel Order') {
        cancelOrderConfirmed();
    } else if (pendingAction === 'Remove Promo') {
        removePromoConfirmed();
    } else if (pendingAction === 'Decrease Quantity') {
        if (pendingQuantityIndex !== null) {
            decreaseQuantityConfirmed(pendingQuantityIndex);
            pendingQuantityIndex = null;
        }
    } else if (pendingAction === 'Remove Item') {
        if (pendingQuantityIndex !== null) {
            removeFromCartConfirmed(pendingQuantityIndex);
            pendingQuantityIndex = null;
        }
    }
    pendingAction = null;
}

function displayCustomerInfo() {
    const customerId = document.getElementById('customer_id').value;
    const customerInfoBox = document.getElementById('customerInfoBox');
    const customerNameEl = document.getElementById('customerName');
    const customerContactEl = document.getElementById('customerContact');
    const customerAddressEl = document.getElementById('customerAddress');
    
    if (!customerId) {
        if (customerInfoBox) customerInfoBox.style.display = 'none';
        return;
    }
    
    const customerSelect = document.getElementById('customer_id');
    const selectedOption = customerSelect.options[customerSelect.selectedIndex];
    const customerName = selectedOption.text;
    
    fetch(`../api/get-customer-info.php?id=${encodeURIComponent(customerId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.customer) {
                const customer = data.customer;
                
                if (customerNameEl) customerNameEl.textContent = customer.customer_name || customerName;
                if (customerContactEl) {
                    if (customer.contact) {
                        customerContactEl.innerHTML = `<i class="bi bi-telephone me-1"></i>${escapeHtml(customer.contact)}`;
                    } else {
                        customerContactEl.innerHTML = '';
                    }
                }
                if (customerAddressEl) {
                    if (customer.address) {
                        customerAddressEl.innerHTML = `<i class="bi bi-geo-alt me-1"></i>${escapeHtml(customer.address)}`;
                    } else {
                        customerAddressEl.innerHTML = '';
                    }
                }
                
                if (customerInfoBox) {
                    customerInfoBox.style.display = 'block';
                }
            } else {
                if (customerNameEl) customerNameEl.textContent = customerName;
                if (customerContactEl) customerContactEl.innerHTML = '';
                if (customerAddressEl) customerAddressEl.innerHTML = '';
                if (customerInfoBox) customerInfoBox.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error fetching customer info:', error);
            if (customerNameEl) customerNameEl.textContent = customerName;
            if (customerContactEl) customerContactEl.innerHTML = '';
            if (customerAddressEl) customerAddressEl.innerHTML = '';
            if (customerInfoBox) customerInfoBox.style.display = 'block';
        });
}

function updateCartBadge() {
    const badge = document.getElementById('cartBadge');
    if (badge) {
        if (cart.length > 0) {
            badge.style.display = 'flex';
            badge.textContent = cart.length;
        } else {
            badge.style.display = 'none';
        }
    }
}

function toggleCart() {
    const cartSidebar = document.querySelector('.cart-sidebar');
    const backdrop = document.getElementById('cartBackdrop');
    
    if (cartSidebar && backdrop) {
        cartSidebar.classList.toggle('show');
        backdrop.classList.toggle('show');
        
        if (cartSidebar.classList.contains('show')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }
}

function addCloseButton() {
    const cartHeader = document.querySelector('.cart-header');
    const existingCloseBtn = document.querySelector('.cart-close-btn');
    
    if (window.innerWidth < 992 && cartHeader && !existingCloseBtn) {
        const closeBtn = document.createElement('button');
        closeBtn.className = 'cart-close-btn';
        closeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        closeBtn.onclick = toggleCart;
        closeBtn.type = 'button';
        cartHeader.appendChild(closeBtn);
    } else if (window.innerWidth >= 992 && existingCloseBtn) {
        existingCloseBtn.remove();
    }
}

function applyPromo() {
    const promoInput = document.getElementById('promoCode');
    const promoMessageDiv = document.getElementById('promoMessage');
    
    if (!promoInput || !promoMessageDiv) {
        console.error('Promo elements not found!');
        return;
    }
    
    const promoCode = promoInput.value.trim().toUpperCase();
    
    if (!promoCode) {
        showPromoMessage('Please enter a promo code', 'danger');
        return;
    }
    
    if (cart.length === 0) {
        showPromoMessage('Please add items first', 'warning');
        return;
    }
    
    let cartSubtotal = 0;
    cart.forEach(item => {
        cartSubtotal += item.price * item.quantity;
    });
    
    showPromoMessage('<i class="spinner-border spinner-border-sm me-2"></i>Validating...', 'info');
    
    const url = '../admin/ajax/validate_promo.php?code=' + 
                encodeURIComponent(promoCode) + 
                '&total=' + cartSubtotal.toFixed(2);
    
    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(data => {
            if (data.success) {
                appliedPromo = data.promo;
                appliedPromo.discount_amount = data.discount;
                
                let successMsg = `<i class="bi bi-check-circle-fill me-2"></i><strong>${data.promo.promo_name}</strong> applied! Save <strong>₱${data.discount.toFixed(2)}</strong>`;
                
                if (data.promo.remaining_uses) {
                    successMsg += `<br><small>${data.promo.remaining_uses} uses left</small>`;
                }
                
                showPromoMessage(successMsg, 'success');
                
                promoInput.readOnly = true;
                promoInput.style.backgroundColor = '#e9ecef';
                
                updateCart();
                
            } else {
                appliedPromo = null;
                showPromoMessage('<i class="bi bi-x-circle me-2"></i>' + data.message, 'danger');
                updateCart();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showPromoMessage('<i class="bi bi-exclamation-triangle me-2"></i>Connection error', 'danger');
            appliedPromo = null;
            updateCart();
        });
}

function removePromo() {
    requestPin('Remove Promo');
}

function showPromoMessage(message, type) {
    const promoMessageDiv = document.getElementById('promoMessage');
    if (!promoMessageDiv) return;
    
    const colorMap = {
        'success': 'success',
        'danger': 'danger',
        'warning': 'warning',
        'info': 'info'
    };
    
    promoMessageDiv.innerHTML = 
        `<div class="alert alert-${colorMap[type]} alert-compact mt-2 mb-0" style="font-size: 0.75rem; padding: 6px 10px; border-radius: 4px;">
            ${message}
        </div>`;
}

function addToCart(product) {
    const currentStock = parseInt(product.current_stock);
    const productId = parseInt(product.product_id);
    const existingItem = cart.find(item => parseInt(item.product_id) === productId);
    
    if (existingItem) {
        if (existingItem.quantity >= currentStock) {
            showNotification('Only ' + currentStock + ' units available', 'warning');
            return;
        }
        existingItem.quantity++;
        showNotification('Updated quantity!', 'success');
        // Decrease inventory when increasing quantity
        updateInventoryInDatabase(productId, 1, 'add_to_cart');
    } else {
        if (currentStock < 1) {
            showNotification('Out of stock!', 'danger');
            return;
        }
        cart.push({
            product_id: productId,
            name: product.product_name,
            price: parseFloat(product.current_price),
            capital: parseFloat(product.capital_cost),
            quantity: 1,
            max_stock: currentStock,
            image_path: product.image_path,
            size: product.size,
            unit: product.unit
        });
        showNotification('Added to cart!', 'success');
        // Decrease inventory when adding to cart
        updateInventoryInDatabase(productId, 1, 'add_to_cart');
    }
    
    updateCart();
}

function updateCart() {
    const cartItemsDiv = document.getElementById('cartItems');
    const emptyCart = document.getElementById('emptyCart');
    const displayTotal = document.getElementById('displayTotal');
    const modalTotal = document.getElementById('modalTotal');
    const proceedBtn = document.getElementById('proceedBtn');
    const cartData = document.getElementById('cartData');
    
    if (!cartItemsDiv || !displayTotal) return;
    
    updateCartBadge();
    
    if (cart.length === 0) {
        if (emptyCart) emptyCart.style.display = 'block';
        cartItemsDiv.innerHTML = '';
        if (emptyCart) cartItemsDiv.appendChild(emptyCart);
        if (proceedBtn) proceedBtn.disabled = true;
        
        displayTotal.innerHTML = `
            <div class="total-row main">
                <span>TOTAL:</span>
                <span>₱0.00</span>
            </div>
        `;
        if (modalTotal) modalTotal.textContent = '₱0.00';
        return;
    }
    
    if (emptyCart) emptyCart.style.display = 'none';
    
    let html = '';
    subtotalAmount = 0;
    
    cart.forEach((item, index) => {
        const subtotal = item.price * item.quantity;
        subtotalAmount += subtotal;
        
        const imageHtml = item.image_path 
            ? `<img src="../${escapeHtml(item.image_path)}" class="cart-item-img">`
            : `<div class="cart-item-img-placeholder">
                 <i class="bi bi-droplet-fill text-white"></i>
               </div>`;
        
        html += `
            <div class="cart-item-compact">
                ${imageHtml}
                <div class="cart-item-info">
                    <div class="cart-item-name">${escapeHtml(item.name)}</div>
                    <div class="cart-item-details">${escapeHtml(item.size)} ${escapeHtml(item.unit)} • ₱${item.price.toFixed(2)}</div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <div class="qty-controls">
                            <button type="button" class="btn btn-sm qty-btn" 
                                    onclick="decreaseQuantity(${index})"
                                    style="${item.quantity <= 1 ? 'background: #ff6b6b; color: white; border-color: #ff6b6b; cursor: not-allowed; opacity: 0.6;' : 'background: white; color: #ff6b6b; border: 1px solid #ff6b6b;'}"
                                    ${item.quantity <= 1 ? 'disabled' : ''}>
                                <i class="bi bi-dash"></i>
                            </button>
                            <span class="qty-value">${item.quantity}</span>
                            <button type="button" class="btn btn-outline-success btn-sm qty-btn" 
                                    onclick="increaseQuantity(${index})" 
                                    ${item.quantity >= item.max_stock ? 'disabled' : ''}>
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                        <div class="cart-item-price">₱${subtotal.toFixed(2)}</div>
                    </div>
                </div>
                <button type="button" class="btn btn-danger btn-sm remove-btn" 
                        onclick="removeFromCart(${index})">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        `;
    });
    
    cartItemsDiv.innerHTML = html;
    
    let discountAmount = 0;
    if (appliedPromo && appliedPromo.discount_amount) {
        discountAmount = appliedPromo.discount_amount;
        
        if (appliedPromo.discount_type === 'percentage') {
            discountAmount = (subtotalAmount * appliedPromo.discount_value) / 100;
            if (appliedPromo.max_discount_amount > 0) {
                discountAmount = Math.min(discountAmount, appliedPromo.max_discount_amount);
            }
        } else {
            discountAmount = appliedPromo.discount_value;
        }
        discountAmount = Math.min(discountAmount, subtotalAmount);
        appliedPromo.discount_amount = discountAmount;
    }
    
    totalAmount = subtotalAmount - discountAmount;
    
    let totalHtml = `
        <div class="total-row">
            <span>Subtotal:</span>
            <span>₱${subtotalAmount.toFixed(2)}</span>
        </div>
    `;
    
    if (discountAmount > 0) {
        totalHtml += `
            <div class="total-row text-success">
                <span><i class="bi bi-tag-fill me-1"></i>${escapeHtml(appliedPromo.promo_code)}:</span>
                <span>-₱${discountAmount.toFixed(2)}</span>
            </div>
            <div class="mb-2">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removePromo()" style="font-size: 0.75rem; padding: 4px 8px;">
                    <i class="bi bi-x me-1"></i>Remove
                </button>
            </div>
        `;
    }
    
    totalHtml += `
        <div class="total-row main">
            <span>TOTAL:</span>
            <span>₱${totalAmount.toFixed(2)}</span>
        </div>
    `;
    
    displayTotal.innerHTML = totalHtml;
    
    if (modalTotal) modalTotal.textContent = '₱' + totalAmount.toFixed(2);
    if (cartData) cartData.value = JSON.stringify(cart);
    if (proceedBtn) proceedBtn.disabled = false;
}

function increaseQuantity(index) {
    if (cart[index].quantity >= cart[index].max_stock) {
        showNotification('Only ' + cart[index].max_stock + ' units available', 'warning');
        return;
    }
    cart[index].quantity++;
    updateCart();
}

function decreaseQuantity(index) {
    if (cart[index].quantity > 1) {
        requestPin('Decrease Quantity');
        pendingQuantityIndex = index;
    }
}

function decreaseQuantityConfirmed(index) {
    if (cart[index] && cart[index].quantity > 1) {
        const productId = cart[index].product_id;
        cart[index].quantity--;
        
        // Restore inventory: add back 1 unit
        updateInventoryInDatabase(productId, -1, 'decrease_quantity');
        
        updateCart();
        
        Swal.fire({
            icon: 'success',
            title: 'Quantity Updated',
            text: 'Quantity decreased by 1',
            timer: 1200,
            showConfirmButton: false
        });
    }
}

function removeFromCart(index) {
    requestPin('Remove Item');
    pendingQuantityIndex = index;
}

function removeFromCartConfirmed(index) {
    if (cart[index]) {
        const itemName = cart[index].name;
        const productId = cart[index].product_id;
        const quantity = cart[index].quantity;
        
        // Restore inventory: use negative value to add back
        updateInventoryInDatabase(productId, -quantity, 'remove_from_cart');
        
        cart.splice(index, 1);
        updateCart();
        
        Swal.fire({
            icon: 'success',
            title: 'Item Removed',
            text: itemName + ' has been removed from cart',
            timer: 1200,
            showConfirmButton: false
        });
    }
}

function clearCart() {
    requestPin('Clear Cart');
}

function clearCartConfirmed() {
    // Restore inventory for ALL items in cart BEFORE clearing
    cart.forEach(item => {
        const productId = item.product_id;
        const quantity = item.quantity;
        // Restore inventory: use negative value to add back all items
        updateInventoryInDatabase(productId, -quantity, 'clear_cart');
    });
    
    cart = [];
    appliedPromo = null;
    
    const promoCode = document.getElementById('promoCode');
    const promoMessage = document.getElementById('promoMessage');
    
    if (promoCode) {
        promoCode.value = '';
        promoCode.readOnly = false;
        promoCode.style.backgroundColor = '';
    }
    
    if (promoMessage) promoMessage.innerHTML = '';
    
    updateCart();
    
    Swal.fire({
        icon: 'success',
        title: 'Cart Cleared',
        text: 'All items have been removed from cart',
        timer: 1500,
        showConfirmButton: false
    });
}

function cancelOrderConfirmed() {
    cart = [];
    appliedPromo = null;
    
    const promoCode = document.getElementById('promoCode');
    const promoMessage = document.getElementById('promoMessage');
    
    if (promoCode) {
        promoCode.value = '';
        promoCode.readOnly = false;
        promoCode.style.backgroundColor = '';
    }
    
    if (promoMessage) promoMessage.innerHTML = '';
    
    updateCart();
    
    Swal.fire({
        icon: 'info',
        title: 'Order Cancelled',
        text: 'The order has been successfully cancelled',
        timer: 1500,
        showConfirmButton: false
    });
}

function removePromoConfirmed() {
    appliedPromo = null;
    
    const promoCode = document.getElementById('promoCode');
    const promoMessage = document.getElementById('promoMessage');
    
    if (promoCode) {
        promoCode.value = '';
        promoCode.readOnly = false;
        promoCode.style.backgroundColor = '';
    }
    
    if (promoMessage) promoMessage.innerHTML = '';
    
    updateCart();
    
    Swal.fire({
        icon: 'success',
        title: 'Promo Removed',
        text: 'Promotional discount has been removed',
        timer: 1500,
        showConfirmButton: false
    });
}

function showPaymentModal() {
    if (cart.length === 0) {
        showNotification('Cart is empty!', 'warning');
        return;
    }
    
    console.log('🔵 Payment Modal Opening - Total Amount:', totalAmount, 'Cart:', cart);
    
    const deliveryOption = document.getElementById('delivery_option');
    const customerId = document.getElementById('customer_id');
    
    if (deliveryOption && customerId) {
        if (deliveryOption.value === 'delivery' && !customerId.value) {
            showNotification('Select a customer for delivery', 'warning');
            return;
        }
    }
    
    if (window.innerWidth < 992) {
        const cartSidebar = document.querySelector('.cart-sidebar');
        const backdrop = document.getElementById('cartBackdrop');
        if (cartSidebar) cartSidebar.classList.remove('show');
        if (backdrop) backdrop.classList.remove('show');
        document.body.style.overflow = '';
    }
    
    selectedPaymentMethod = null;
    
    const cashForm = document.getElementById('cashPaymentForm');
    const gcashForm = document.getElementById('gcashPaymentForm');
    const confirmBtn = document.getElementById('confirmPaymentBtn');
    
    if (cashForm) cashForm.style.display = 'none';
    if (gcashForm) gcashForm.style.display = 'none';
    if (confirmBtn) confirmBtn.disabled = true;
    
    document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('selected'));
    
    if (paymentModal) paymentModal.show();
}

function selectPayment(method) {
    selectedPaymentMethod = method;
    console.log('🟡 Payment method selected:', method, 'Total:', totalAmount);
    
    document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    
    const cashForm = document.getElementById('cashPaymentForm');
    const gcashForm = document.getElementById('gcashPaymentForm');
    const cashAmount = document.getElementById('cashAmount');
    const changeDisplay = document.getElementById('changeDisplay');
    const confirmBtn = document.getElementById('confirmPaymentBtn');
    
    if (method === 'cash') {
        if (cashForm) cashForm.style.display = 'block';
        if (gcashForm) gcashForm.style.display = 'none';
        if (cashAmount) {
            cashAmount.value = '';
            cashAmount.focus();
            console.log('🟡 Cash form showing, focus on input');
        }
        // Don't hide changeDisplay here - let calculateChange() control it
        if (confirmBtn) confirmBtn.disabled = true;
    } else {
        if (gcashForm) gcashForm.style.display = 'block';
        if (cashForm) cashForm.style.display = 'none';
        if (changeDisplay) changeDisplay.style.display = 'none';
        if (confirmBtn) confirmBtn.disabled = false;
    }
}

function calculateChange() {
    const cashAmount = document.getElementById('cashAmount');
    const changeAmount = document.getElementById('changeAmount');
    const changeDisplay = document.getElementById('changeDisplay');
    const confirmBtn = document.getElementById('confirmPaymentBtn');
    
    if (!cashAmount || !changeDisplay) {
        console.error('❌ Elements not found');
        return;
    }
    
    const cash = parseFloat(cashAmount.value) || 0;
    console.log('🔍 calculateChange fired - Cash:', cash, 'Total:', totalAmount);
    
    if (totalAmount <= 0) {
        console.warn('⚠️ Invalid totalAmount');
        changeDisplay.classList.remove('d-flex');
        changeDisplay.style.display = 'none';
        return;
    }
    
    if (cash > 0) {
        if (cash >= totalAmount) {
            const change = cash - totalAmount;
            changeAmount.textContent = '₱' + change.toFixed(2);
            console.log('✅ Change: ₱' + change.toFixed(2));
            
            // Remove any hidden classes and force display
            changeDisplay.classList.add('d-flex');
            changeDisplay.classList.remove('d-none');
            changeDisplay.style.removeProperty('display');
            changeDisplay.setAttribute('style', 'background-color: #d3f9d8 !important; border: 1px solid #51cf66 !important; margin-top: 12px; margin-bottom: 0; padding: 12px 14px !important;');
            
            console.log('✅ Display visible - attempting scroll');
            setTimeout(() => {
                const rect = changeDisplay.getBoundingClientRect();
                console.log('Element position - top:', rect.top, 'bottom:', rect.bottom);
                changeDisplay.scrollIntoView({ behavior: 'auto', block: 'nearest' });
            }, 50);
            
            if (confirmBtn) confirmBtn.disabled = false;
        } else {
            changeAmount.textContent = '₱0.00 - Insufficient';
            changeDisplay.classList.add('d-flex');
            changeDisplay.setAttribute('style', 'background-color: #ffe0e0 !important; border: 1px solid #ff6b6b !important; margin-top: 12px; margin-bottom: 0; padding: 12px 14px !important;');
            console.log('⚠️ Insufficient cash');
            
            if (confirmBtn) confirmBtn.disabled = true;
        }
    } else {
        console.log('ℹ️ Clearing display');
        changeDisplay.classList.remove('d-flex');
        changeDisplay.style.display = 'none';
        if (confirmBtn) confirmBtn.disabled = true;
    }
}

function confirmPayment() {
    const form = document.getElementById('posForm');
    const btn = document.getElementById('confirmPaymentBtn');
    
    if (!form || !btn) return;
    
    btn.disabled = true;
    
    if (selectedPaymentMethod === 'cash') {
        const cashAmountInput = document.getElementById('cashAmount');
        if (!cashAmountInput) return;
        
        const cashAmount = parseFloat(cashAmountInput.value);
        if (cashAmount < totalAmount) {
            showNotification('Insufficient payment!', 'danger');
            btn.disabled = false;
            return;
        }
        
        const methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = 'payment_method';
        methodInput.value = 'cash';
        form.appendChild(methodInput);
        
        const amountInput = document.createElement('input');
        amountInput.type = 'hidden';
        amountInput.name = 'amount_paid';
        amountInput.value = cashAmount;
        form.appendChild(amountInput);
        
    } else if (selectedPaymentMethod === 'gcash') {
        const methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = 'payment_method';
        methodInput.value = 'gcash';
        form.appendChild(methodInput);
        
        const amountInput = document.createElement('input');
        amountInput.type = 'hidden';
        amountInput.name = 'amount_paid';
        amountInput.value = totalAmount;
        form.appendChild(amountInput);
    }
    
    // Ensure cart_data is updated with latest cart items
    const cartDataInput = document.getElementById('cartData');
    if (cartDataInput) {
        cartDataInput.value = JSON.stringify(cart);
    }
    
    if (appliedPromo) {
        const promoInput = document.createElement('input');
        promoInput.type = 'hidden';
        promoInput.name = 'promo_code';
        promoInput.value = appliedPromo.promo_code;
        form.appendChild(promoInput);
    }
    
    const completeInput = document.createElement('input');
    completeInput.type = 'hidden';
    completeInput.name = 'complete_sale';
    completeInput.value = '1';
    form.appendChild(completeInput);
    
    if (paymentModal) paymentModal.hide();
    form.submit();
}

function showNotification(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
    alertDiv.style.zIndex = '9999';
    alertDiv.style.minWidth = '300px';
    alertDiv.innerHTML = `
        <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'x-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 3000);
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

// Update inventory in database in real-time
function updateInventoryInDatabase(productId, quantityChange, action) {
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity_change', quantityChange);
    formData.append('action', action);
    
    console.log('Calling API to update inventory: product=' + productId + ', change=' + quantityChange + ', action=' + action);
    
    fetch('../api/update-pos-inventory.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        console.log('API Response status:', response.status);
        if (!response.ok) {
            throw new Error('HTTP ' + response.status + ': ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        console.log('API Response data:', data);
        if (data.success) {
            console.log('✓ Inventory updated: ' + data.product_name + ' - Old: ' + data.old_stock + ' → New: ' + data.new_stock);
            // Refresh product display to show updated stock
            refreshProductStock(productId, data.new_stock);
        } else {
            console.error('✗ Inventory update failed: ' + data.message);
            showNotification('Stock update failed: ' + data.message, 'warning');
        }
    })
    .catch(error => {
        console.error('✗ Network error updating inventory:', error);
        showNotification('Connection error: ' + error.message, 'warning');
    });
}

// Refresh product stock display in real-time
function refreshProductStock(productId, newStock) {
    const productCards = document.querySelectorAll('[data-product-id="' + productId + '"]');
    
    productCards.forEach(card => {
        // Update stock badge
        const stockBadge = card.querySelector('.stock-badge-compact');
        if (stockBadge) {
            stockBadge.textContent = newStock + ' pcs';
            
            // Update badge color based on stock
            if (newStock < 1) {
                stockBadge.className = 'stock-badge-compact';
                card.classList.add('out-of-stock');
                card.style.pointerEvents = 'none';
                card.style.opacity = '0.5';
            } else if (newStock < 5) {
                stockBadge.className = 'stock-badge-compact low';
                card.classList.remove('out-of-stock');
                card.style.pointerEvents = 'auto';
                card.style.opacity = '1';
            } else {
                stockBadge.className = 'stock-badge-compact in';
                card.classList.remove('out-of-stock');
                card.style.pointerEvents = 'auto';
                card.style.opacity = '1';
            }
        }
        
        // Update product's current_stock attribute for cart validation
        card.setAttribute('data-current-stock', newStock);
    });
}
</script>

<?php include '../includes/footer.php'; ?>
 