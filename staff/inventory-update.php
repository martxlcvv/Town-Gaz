<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_staff();

$page_title = "Inventory Update";

// PIN verification function
function verifyStaffPin($pin) {
    global $conn;
    
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT pin_hash FROM admin_pins WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    
    // Log for debugging
    $pin_length = strlen($pin);
    error_log("PIN Check: User=$user_id, PIN_Length=$pin_length, PIN_Found=" . ($data ? "Yes" : "No"));
    
    if ($data) {
        $is_valid = password_verify($pin, $data['pin_hash']);
        error_log("PIN Verify Result: " . ($is_valid ? "VALID" : "INVALID"));
        return $is_valid;
    }
    
    error_log("PIN ERROR: No PIN hash found for user $user_id");
    return false;
}

// Handle inventory update without PIN (Add operations)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_simple'])) {
    // Verify session token
    if (!verify_csrf_token($_POST['session_token'] ?? '')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid session. Please try again.']);
        exit();
    }
    
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $type = $_POST['type']; // 'add' or 'deduct'
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    $closing_stock = isset($_POST['closing_stock']) ? (int)$_POST['closing_stock'] : 0;
    
    // Get current inventory
    $current_sql = "SELECT current_stock FROM inventory 
                    WHERE product_id = $product_id AND date = CURDATE()";
    $current_result = mysqli_query($conn, $current_sql);
    
    if (mysqli_num_rows($current_result) > 0) {
        $current = mysqli_fetch_assoc($current_result);
        $current_stock = $current['current_stock'];
        
        // If a closing_stock (stock out value) was provided for DEDUCT, prefer that for stock-out operations.
        if ($type == 'deduct' && $closing_stock > 0) {
            $new_stock = $current_stock - $closing_stock;
            if ($new_stock < 0) {
                $_SESSION['error'] = "Cannot deduct more than current stock";
                header('Location: inventory-update.php');
                exit();
            }
        } else if ($type == 'add') {
            $new_stock = $current_stock + $quantity;
        } else {
            $new_stock = $current_stock - $quantity;
            if ($new_stock < 0) {
                $_SESSION['error'] = "Cannot deduct more than current stock";
                header('Location: inventory-update.php');
                exit();
            }
        }

        // Calculate delta and adjust stock_in/stock_out for consistency with POS/admin
        $delta = $new_stock - $current_stock;
        $stock_in_delta = 0;
        $stock_out_delta = 0;
        if ($delta > 0) {
            $stock_in_delta = $delta;
        } elseif ($delta < 0) {
            $stock_out_delta = abs($delta);
        }
        // If explicit closing_stock used, ensure stock_out_delta includes it
        if ($closing_stock > 0) {
            $stock_out_delta = max($stock_out_delta, $closing_stock);
        }

        // Update inventory (current_stock + keep stock_in/stock_out and opening/closing in sync)
        $update_sql = "UPDATE inventory SET 
                      current_stock = ?,
                      stock_in = COALESCE(stock_in,0) + ?,
                      stock_out = COALESCE(stock_out,0) + ?,
                      opening_stock = COALESCE(opening_stock,0) + ?,
                      closing_stock = COALESCE(closing_stock,0) + ?,
                      updated_at = NOW(),
                      updated_by = ?
                      WHERE product_id = ? AND date = CURDATE()";

        $update_stmt = mysqli_prepare($conn, $update_sql);
        if ($update_stmt) {
            $uid = $_SESSION['user_id'];
            mysqli_stmt_bind_param($update_stmt, 'iiiiiii', $new_stock, $stock_in_delta, $stock_out_delta, $stock_in_delta, $stock_out_delta, $uid, $product_id);
            if (mysqli_stmt_execute($update_stmt)) {
                $_SESSION['success'] = "Inventory updated successfully";
                // Audit log
                log_audit($uid, 'UPDATE', 'inventory', $product_id, ['current_stock' => $current_stock], ['current_stock' => $new_stock, 'stock_in_delta' => $stock_in_delta, 'stock_out_delta' => $stock_out_delta]);
            } else {
                $_SESSION['error'] = "Error updating inventory";
            }
            mysqli_stmt_close($update_stmt);
        } else {
            $_SESSION['error'] = "Error preparing inventory update";
        }
    } else {
        // Auto-create inventory row for today and then apply update
        $insert_sql = "INSERT INTO inventory (product_id, stock_in, stock_out, opening_stock, closing_stock, current_stock, date, updated_by, created_at, updated_at) 
                       VALUES (?, 0, 0, 0, 0, 0, CURDATE(), ?, NOW(), NOW())";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        if ($insert_stmt) {
            $uid = $_SESSION['user_id'];
            mysqli_stmt_bind_param($insert_stmt, 'ii', $product_id, $uid);
            mysqli_stmt_execute($insert_stmt);
            mysqli_stmt_close($insert_stmt);
            // Re-run the update flow by setting current_stock to 0 and computing new_stock
            $current_stock = 0;
            if ($type == 'deduct' && $closing_stock > 0) {
                $new_stock = $current_stock - $closing_stock;
                if ($new_stock < 0) {
                    $_SESSION['error'] = "Cannot deduct more than current stock";
                    header('Location: inventory-update.php');
                    exit();
                }
            } else if ($type == 'add') {
                $new_stock = $current_stock + $quantity;
            } else {
                $new_stock = $current_stock - $quantity;
                if ($new_stock < 0) {
                    $_SESSION['error'] = "Cannot deduct more than current stock";
                    header('Location: inventory-update.php');
                    exit();
                }
            }

            $delta = $new_stock - $current_stock;
            $stock_in_delta = $delta > 0 ? $delta : 0;
            $stock_out_delta = $delta < 0 ? abs($delta) : 0;
            if ($closing_stock > 0) {
                $stock_out_delta = max($stock_out_delta, $closing_stock);
            }

            $update_sql = "UPDATE inventory SET 
                      current_stock = ?,
                      stock_in = COALESCE(stock_in,0) + ?,
                      stock_out = COALESCE(stock_out,0) + ?,
                      opening_stock = COALESCE(opening_stock,0) + ?,
                      closing_stock = COALESCE(closing_stock,0) + ?,
                      updated_at = NOW(),
                      updated_by = ?
                      WHERE product_id = ? AND date = CURDATE()";

            $update_stmt = mysqli_prepare($conn, $update_sql);
            if ($update_stmt) {
                mysqli_stmt_bind_param($update_stmt, 'iiiiiii', $new_stock, $stock_in_delta, $stock_out_delta, $stock_in_delta, $stock_out_delta, $uid, $product_id);
                if (mysqli_stmt_execute($update_stmt)) {
                    $_SESSION['success'] = "Inventory created and updated successfully";
                    log_audit($uid, 'INSERT_UPDATE', 'inventory', $product_id, ['current_stock' => 0], ['current_stock' => $new_stock, 'stock_in_delta' => $stock_in_delta, 'stock_out_delta' => $stock_out_delta]);
                } else {
                    $_SESSION['error'] = "Error updating newly created inventory";
                }
                mysqli_stmt_close($update_stmt);
            } else {
                $_SESSION['error'] = "Error preparing inventory update";
            }
        } else {
            $_SESSION['error'] = "Product inventory not found for today";
        }
    }
    
    header('Location: inventory-update.php');
    exit();
}

// Handle inventory update with PIN verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_with_pin'])) {
    // Verify session token
    if (!verify_csrf_token($_POST['session_token'] ?? '')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid session. Please try again.']);
        exit();
    }
    
    $pin = $_POST['pin'] ?? '';
    
    error_log("=== PIN VERIFICATION ATTEMPT ===");
    error_log("User ID: {$_SESSION['user_id']}");
    error_log("PIN Received: [" . str_repeat("*", strlen($pin)) . "] Length: " . strlen($pin));
    error_log("POST data keys: " . implode(", ", array_keys($_POST)));
    
    // Verify PIN
    if (verifyStaffPin($pin)) {
        error_log("PIN VERIFICATION SUCCESSFUL");
        
        $product_id = (int)$_POST['product_id'];
        $quantity = (int)$_POST['quantity'];
        $type = $_POST['type']; // 'add' or 'deduct'
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        $closing_stock = isset($_POST['closing_stock']) ? (int)$_POST['closing_stock'] : 0;
        
        // Get current inventory
        $current_sql = "SELECT current_stock FROM inventory 
                        WHERE product_id = $product_id AND date = CURDATE()";
        $current_result = mysqli_query($conn, $current_sql);
        
        if (mysqli_num_rows($current_result) > 0) {
            $current = mysqli_fetch_assoc($current_result);
            $current_stock = $current['current_stock'];
            
            // If a closing_stock (stock out value) was provided for DEDUCT, prefer that for stock-out operations.
            if ($type == 'deduct' && $closing_stock > 0) {
                $new_stock = $current_stock - $closing_stock;
                if ($new_stock < 0) {
                    $_SESSION['error'] = "Cannot deduct more than current stock";
                    header('Location: inventory-update.php');
                    exit();
                }
            } else if ($type == 'add') {
                $new_stock = $current_stock + $quantity;
            } else {
                $new_stock = $current_stock - $quantity;
                if ($new_stock < 0) {
                    $_SESSION['error'] = "Cannot deduct more than current stock";
                    header('Location: inventory-update.php');
                    exit();
                }
            }

            // Calculate delta and adjust stock_in/stock_out for consistency with POS/admin
            $delta = $new_stock - $current_stock;
            $stock_in_delta = 0;
            $stock_out_delta = 0;
            if ($delta > 0) {
                $stock_in_delta = $delta;
            } elseif ($delta < 0) {
                $stock_out_delta = abs($delta);
            }
            // If explicit closing_stock used, ensure stock_out_delta includes it
            if ($closing_stock > 0) {
                $stock_out_delta = max($stock_out_delta, $closing_stock);
            }

            // Update inventory (current_stock + keep stock_in/stock_out and opening/closing in sync)
            $update_sql = "UPDATE inventory SET 
                          current_stock = ?,
                          stock_in = COALESCE(stock_in,0) + ?,
                          stock_out = COALESCE(stock_out,0) + ?,
                          opening_stock = COALESCE(opening_stock,0) + ?,
                          closing_stock = COALESCE(closing_stock,0) + ?,
                          updated_at = NOW(),
                          updated_by = ?
                          WHERE product_id = ? AND date = CURDATE()";

            $update_stmt = mysqli_prepare($conn, $update_sql);
            if ($update_stmt) {
                $uid = $_SESSION['user_id'];
                mysqli_stmt_bind_param($update_stmt, 'iiiiiii', $new_stock, $stock_in_delta, $stock_out_delta, $stock_in_delta, $stock_out_delta, $uid, $product_id);
                if (mysqli_stmt_execute($update_stmt)) {
                    $_SESSION['success'] = "Inventory updated successfully";
                    // Audit log
                    log_audit($uid, 'UPDATE', 'inventory', $product_id, ['current_stock' => $current_stock], ['current_stock' => $new_stock, 'stock_in_delta' => $stock_in_delta, 'stock_out_delta' => $stock_out_delta]);
                } else {
                    $_SESSION['error'] = "Error updating inventory";
                }
                mysqli_stmt_close($update_stmt);
            } else {
                $_SESSION['error'] = "Error preparing inventory update";
            }
        } else {
            // Auto-create inventory row for today and then apply update
            $insert_sql = "INSERT INTO inventory (product_id, stock_in, stock_out, opening_stock, closing_stock, current_stock, date, updated_by, created_at, updated_at) 
                           VALUES (?, 0, 0, 0, 0, 0, CURDATE(), ?, NOW(), NOW())";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            if ($insert_stmt) {
                $uid = $_SESSION['user_id'];
                mysqli_stmt_bind_param($insert_stmt, 'ii', $product_id, $uid);
                mysqli_stmt_execute($insert_stmt);
                mysqli_stmt_close($insert_stmt);
                // Re-run the update flow by setting current_stock to 0 and computing new_stock
                $current_stock = 0;
                if ($type == 'deduct' && $closing_stock > 0) {
                    $new_stock = $current_stock - $closing_stock;
                    if ($new_stock < 0) {
                        $_SESSION['error'] = "Cannot deduct more than current stock";
                        header('Location: inventory-update.php');
                        exit();
                    }
                } else if ($type == 'add') {
                    $new_stock = $current_stock + $quantity;
                } else {
                    $new_stock = $current_stock - $quantity;
                    if ($new_stock < 0) {
                        $_SESSION['error'] = "Cannot deduct more than current stock";
                        header('Location: inventory-update.php');
                        exit();
                    }
                }

                $delta = $new_stock - $current_stock;
                $stock_in_delta = $delta > 0 ? $delta : 0;
                $stock_out_delta = $delta < 0 ? abs($delta) : 0;
                if ($closing_stock > 0) {
                    $stock_out_delta = max($stock_out_delta, $closing_stock);
                }

                $update_sql = "UPDATE inventory SET 
                          current_stock = ?,
                          stock_in = COALESCE(stock_in,0) + ?,
                          stock_out = COALESCE(stock_out,0) + ?,
                          opening_stock = COALESCE(opening_stock,0) + ?,
                          closing_stock = COALESCE(closing_stock,0) + ?,
                          updated_at = NOW(),
                          updated_by = ?
                          WHERE product_id = ? AND date = CURDATE()";

                $update_stmt = mysqli_prepare($conn, $update_sql);
                if ($update_stmt) {
                    mysqli_stmt_bind_param($update_stmt, 'iiiiiii', $new_stock, $stock_in_delta, $stock_out_delta, $stock_in_delta, $stock_out_delta, $uid, $product_id);
                    if (mysqli_stmt_execute($update_stmt)) {
                        $_SESSION['success'] = "Inventory created and updated successfully";
                        log_audit($uid, 'INSERT_UPDATE', 'inventory', $product_id, ['current_stock' => 0], ['current_stock' => $new_stock, 'stock_in_delta' => $stock_in_delta, 'stock_out_delta' => $stock_out_delta]);
                    } else {
                        $_SESSION['error'] = "Error updating newly created inventory";
                    }
                    mysqli_stmt_close($update_stmt);
                } else {
                    $_SESSION['error'] = "Error preparing inventory update";
                }
            } else {
                $_SESSION['error'] = "Product inventory not found for today";
            }
        }
    } else {
        error_log("PIN VERIFICATION FAILED");
        $_SESSION['error'] = "Invalid PIN. Update failed.";
    }
    
    header('Location: inventory-update.php');
    exit();
}

// Get today's inventory
$inventory_sql = "SELECT i.*, p.product_name, p.unit
                  FROM inventory i
                  JOIN products p ON i.product_id = p.product_id
                  WHERE i.date = CURDATE()
                  ORDER BY p.product_name";
$inventory_result = mysqli_query($conn, $inventory_sql);

// Get products for dropdown with current stock and image
$products_sql = "SELECT 
                 p.product_id, 
                 p.product_name, 
                 p.unit,
                 p.image_path,
                 COALESCE(i.current_stock, 0) as current_stock,
                 COALESCE(i.closing_stock, 0) as closing_stock
                 FROM products p
                 LEFT JOIN inventory i ON p.product_id = i.product_id AND i.date = CURDATE()
                 WHERE p.status = 'active' 
                 ORDER BY p.product_name";
$products_result = mysqli_query($conn, $products_sql);

// Get recent updates
$recent_updates_sql = "SELECT i.*, p.product_name, u.full_name, i.updated_at
                       FROM inventory i
                       JOIN products p ON i.product_id = p.product_id
                       LEFT JOIN users u ON i.updated_by = u.user_id
                       WHERE i.date = CURDATE()
                       AND i.updated_at IS NOT NULL
                       ORDER BY i.updated_at DESC
                       LIMIT 10";
$recent_updates_result = mysqli_query($conn, $recent_updates_sql);

// Calculate today's stats
$stats_sql = "SELECT 
              SUM(opening_stock) as total_opening,
              SUM(current_stock) as total_current,
              SUM(closing_stock) as total_closing,
              COUNT(CASE WHEN current_stock < 10 THEN 1 END) as critical_count
              FROM inventory
              WHERE date = CURDATE()";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <h1><i class="bi bi-box-seam me-2"></i>Inventory Update</h1>
                <p>Manage product stock levels - PIN verification required</p>
            </div>
            <div class="header-actions">
                <button class="btn-primary header-btn" data-bs-toggle="modal" data-bs-target="#updateModal">
                    <i class="bi bi-plus-circle me-2"></i>Update Stock
                </button>
            </div>
        </div>

        <!-- Flash messages are shown using SweetAlert -->

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card opening">
                <div class="stat-content">
                    <div class="stat-icon opening">
                        <i class="bi bi-box2"></i>
                    </div>
                    <div>
                            <p class="stat-label">Stock In</p>
                        <p class="stat-number"><?php echo $stats['total_opening'] ?? 0; ?></p>
                    </div>
                </div>
            </div>

            <div class="stat-card current">
                <div class="stat-content">
                    <div class="stat-icon current">
                        <i class="bi bi-basket"></i>
                    </div>
                    <div>
                        <p class="stat-label">Current Stock</p>
                        <p class="stat-number"><?php echo $stats['total_current'] ?? 0; ?></p>
                    </div>
                </div>
            </div>

            <div class="stat-card closing">
                <div class="stat-content">
                    <div class="stat-icon closing">
                        <i class="bi bi-box2-heart"></i>
                    </div>
                    <div>
                        <p class="stat-label">Stock Out</p>
                        <p class="stat-number"><?php echo $stats['total_closing'] ?? 0; ?></p>
                    </div>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-content">
                    <div class="stat-icon warning">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div>
                        <p class="stat-label">Low Stock</p>
                        <p class="stat-number"><?php echo $stats['critical_count'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-row">
            <!-- Inventory Table -->
            <div class="content-card full-width">
                <div class="card-header">
                    <h3><i class="bi bi-table me-2"></i>Current Stock (Today)</h3>
                    <input type="text" id="searchInventory" class="search-input" placeholder="Search products...">
                </div>
                <div class="table-container">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Stock In</th>
                                <th>Current Stock</th>
                                <th>Stock Out</th>
                                <th>Unit</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (mysqli_num_rows($inventory_result) > 0):
                                while ($item = mysqli_fetch_assoc($inventory_result)): 
                            ?>
                                <tr class="inventory-row">
                                    <td><strong><?php echo htmlspecialchars($item['product_name']); ?></strong></td>
                                    <td><?php echo $item['opening_stock']; ?></td>
                                    <td><?php echo $item['current_stock']; ?></td>
                                    <td><?php echo $item['closing_stock']; ?></td>
                                    <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                    <td>
                                        <?php if ($item['current_stock'] < 10): ?>
                                            <span class="stock-badge critical">Low Stock</span>
                                        <?php elseif ($item['current_stock'] < 20): ?>
                                            <span class="stock-badge low">Low</span>
                                        <?php else: ?>
                                            <span class="stock-badge good">Good</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn-edit" type="button" onclick="editStock(<?php echo $item['product_id']; ?>)" data-bs-toggle="modal" data-bs-target="#updateModal">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No inventory records for today</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="content-row">
            <!-- Recent Updates -->
            <div class="content-card full-width">
                <div class="card-header">
                    <h3><i class="bi bi-clock-history me-2"></i>Recent Updates</h3>
                </div>
                <div class="table-container">
                    <?php if (mysqli_num_rows($recent_updates_result) > 0): ?>
                        <table class="updates-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Updated By</th>
                                    <th>Time</th>
                                    <th>Current Stock</th>
                                    <th>Stock Out</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                mysqli_data_seek($recent_updates_result, 0);
                                while ($update = mysqli_fetch_assoc($recent_updates_result)): 
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($update['product_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($update['full_name'] ?? 'System'); ?></td>
                                        <td><span class="update-time-badge"><?php echo date('M d, Y h:i A', strtotime($update['updated_at'])); ?></span></td>
                                        <td>
                                            <span class="stock-value"><?php echo $update['current_stock']; ?></span>
                                        </td>
                                        <td>
                                            <span class="stock-value"><?php echo $update['closing_stock']; ?></span>
                                        </td>
                                    </tr>
                                <?php 
                                endwhile;
                                ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <p>No recent updates</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Inventory Modal -->
<div class="modal fade" id="updateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="updateForm" method="POST">
                <?php echo output_token_field(); ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Update Inventory</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_inventory">
                    
                    <!-- Product Image Display -->
                    <div id="productImageContainer" style="margin-bottom: 1.5rem; text-align: center; display: none; min-height: 200px;">
                        <img id="productImage" src="" alt="Product Image" style="max-width: 100%; max-height: 200px; object-fit: contain; border-radius: 8px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Product *</label>
                        <select class="form-select" id="productId" name="product_id" required onchange="loadProductImage()">
                            <option value="">Select Product</option>
                            <?php 
                            mysqli_data_seek($products_result, 0);
                            while ($product = mysqli_fetch_assoc($products_result)): 
                            ?>
                                <option value="<?php echo $product['product_id']; ?>" 
                                    data-product-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                    data-image="<?php echo htmlspecialchars($product['image_path'] ?? ''); ?>"
                                    data-current-stock="<?php echo $product['current_stock']; ?>"
                                    data-closing-stock="<?php echo $product['closing_stock']; ?>"
                                    data-unit="<?php echo htmlspecialchars($product['unit']); ?>">
                                    <?php echo htmlspecialchars($product['product_name']); ?> (<?php echo $product['unit']; ?>) - Stock: <?php echo $product['current_stock']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Transaction Type *</label>
                            <select class="form-select" name="type" required>
                                <option value="add">Add Stock</option>
                                <option value="deduct">Deduct Stock</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Quantity *</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                            <small style="color: #7f8c8d; margin-top: 0.3rem; display: block;">Current Stock: <strong id="currentStockDisplay">0</strong></small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Stock Out</label>
                        <input type="number" class="form-control" id="closing_stock" name="closing_stock" min="0" step="1" placeholder="Enter stock out value">
                        <small style="color: #7f8c8d; margin-top: 0.3rem; display: block;">Current Stock Out: <strong id="closingStockDisplay">0</strong></small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Reason/Notes *</label>
                        <textarea class="form-control" name="reason" rows="3" required placeholder="Describe the reason for this update..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-primary" onclick="proceedWithUpdate()">
                        <i class="bi bi-check-circle me-1"></i>Update Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- PIN Verification Modal -->
<div class="modal fade" id="pinModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-shield-lock me-2"></i>PIN Verification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="pin-modal-container">
                    <div class="pin-icon-header">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <p class="text-center text-muted mb-4" style="font-size: 0.95rem; margin-bottom: 1.5rem;">Enter your 6-digit PIN to confirm</p>
                    
                    <form id="pinForm" method="POST">
                        <?php echo output_token_field(); ?>
                        <input type="hidden" name="update_with_pin" value="1">
                        <input type="hidden" name="product_id" id="pinProductId">
                        <input type="hidden" name="quantity" id="pinQuantity">
                        <input type="hidden" name="type" id="pinType">
                        <input type="hidden" name="reason" id="pinReason">
                        <input type="hidden" name="closing_stock" id="pinClosingStock">
                        <input type="hidden" name="pin" id="pinHidden" value="">
                        
                        <div class="pin-input-wrapper">
                            <input type="password" class="pin-input-field" id="pinInput" inputmode="numeric" maxlength="6" placeholder="000000" autocomplete="off">
                        </div>
                        
                        <div class="pin-display-container">
                            <div class="pin-display">
                                <div class="pin-dot"><span class="dot-inner">●</span></div>
                                <div class="pin-dot"><span class="dot-inner">●</span></div>
                                <div class="pin-dot"><span class="dot-inner">●</span></div>
                                <div class="pin-dot"><span class="dot-inner">●</span></div>
                                <div class="pin-dot"><span class="dot-inner">●</span></div>
                                <div class="pin-dot"><span class="dot-inner">●</span></div>
                            </div>
                        </div>
                        
                        <div id="pinError" class="alert alert-danger mt-3" style="display: none;">
                            <i class="bi bi-exclamation-circle me-2"></i>
                            <span id="pinErrorMessage"></span>
                        </div>
                        
                        <p class="text-center text-muted small" style="margin-top: 1.5rem; margin-bottom: 0;">Type the PIN shown on your screen</p>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-primary" id="confirmPinBtn" onclick="submitPin()" disabled>
                    <i class="bi bi-check-circle me-1"></i>Verify PIN
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Root Variables */
:root {
    --primary-blue: #065275; /* admin teal */
    --primary-green: #00547a; /* darker teal */
    --primary-orange: #e67e22;
    --primary-red: #e74c3c;
    --primary-purple: #9b59b6;
    --light-bg: #f8f9fa;
    --card-bg: #ffffff;
    --text-dark: #2c3e50;
    --text-light: #7f8c8d;
    --border-color: #e9ecef;
    --shadow-light: 0 1px 6px rgba(0,0,0,0.04);
    --shadow-medium: 0 2px 10px rgba(0,0,0,0.06);
}

body {
    background-color: var(--light-bg);
    color: var(--text-dark);
}

/* Dashboard Header */
.dashboard-header {
    background: linear-gradient(135deg, #1a4d5c 0%, #0f3543 100%);
    border-radius: 12px;
    padding: 2rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 6px 30px rgba(26, 77, 92, 0.25);
    color: white;
}

.header-content {
    flex: 1;
}

.header-content h1 {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0 0 0.25rem 0;
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.header-content p {
    font-size: 0.95rem;
    margin: 0;
    opacity: 0.9;
    color: #e0f2f7;
}

.header-actions {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.btn-primary.header-btn {
    background: #fff;
    color: #1a4d5c;
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.btn-primary.header-btn:hover {
    background: #e8f4f8;
    transform: translateY(-1px);
}

/* Page Header */
.page-header {
    background: var(--card-bg);
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    box-shadow: var(--shadow-light);
    border-left: 4px solid var(--primary-blue);
    border-top: 2px solid var(--primary-green);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.header-content h2 {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--text-dark);
}

.header-content p {
    color: var(--text-light);
    margin-bottom: 0;
}

/* Button */
.btn-primary-action {
    background: var(--primary-blue);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: none;
}

.btn-primary-action:hover {
    background: #043a52;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(4,58,82,0.12);
}

/* Alert Messages */
.alert-box {
    padding: 1rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    border-left: 4px solid;
    animation: slideIn 0.3s ease;
}

.alert-success {
    background: rgba(39, 174, 96, 0.1);
    border-left-color: var(--primary-green);
    color: var(--text-dark);
}

.alert-danger {
    background: rgba(231, 76, 60, 0.1);
    border-left-color: var(--primary-red);
    color: var(--text-dark);
}

.alert-box i {
    font-size: 1.25rem;
    flex-shrink: 0;
    margin-top: 0.25rem;
}

.alert-box strong {
    display: block;
    margin-bottom: 0.25rem;
}

.alert-box p {
    margin: 0;
    font-size: 0.9rem;
}

.close-alert {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: inherit;
    opacity: 0.7;
    margin-left: auto;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: var(--card-bg);
    border-radius: 8px;
    padding: 0.75rem;
    box-shadow: var(--shadow-light);
    transition: all 0.2s ease;
    border-top: 4px solid;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-medium);
}

.stat-card.opening {
    border-top-color: var(--primary-blue);
}

.stat-card.current {
    border-top-color: var(--primary-green);
}

.stat-card.closing {
    border-top-color: var(--primary-purple);
}

.stat-card.warning {
    border-top-color: var(--primary-red);
}

.stat-content {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.stat-icon.opening {
    background: rgba(6, 82, 117, 0.08);
    color: var(--primary-blue);
}

.stat-icon.current {
    background: rgba(0, 84, 122, 0.06);
    color: var(--primary-green);
}

.stat-icon.closing {
    background: rgba(155, 89, 182, 0.1);
    color: var(--primary-purple);
}

.stat-icon.warning {
    background: rgba(231, 76, 60, 0.1);
    color: var(--primary-red);
}

.stat-label {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: var(--text-light);
    font-weight: 700;
    margin: 0;
}

.stat-number {
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--text-dark);
    margin: 0;
}

/* Content Layout */
.content-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.content-card {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: var(--shadow-light);
    overflow: hidden;
}

.content-card.full-width {
    grid-column: 1 / -1;
}

.card-header {
    background: var(--light-bg);
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.card-header h3 {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0;
    display: flex;
    align-items: center;
}

.card-header h3 i {
    margin-right: 0.5rem;
    color: var(--primary-orange);
}

.search-input {
    padding: 0.4rem 0.8rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-size: 0.85rem;
    min-width: 220px;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(6,82,117,0.06);
}

/* Table */
.table-container {
    overflow-x: auto;
    padding: 0;
}

.inventory-table {
    width: 100%;
    border-collapse: collapse;
}

.inventory-table thead {
    background: var(--light-bg);
}

.inventory-table th {
    padding: 0.6rem 0.75rem;
    text-align: left;
    font-weight: 600;
    color: var(--text-dark);
    border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
    font-size: 0.85rem;
}

.inventory-table td {
    padding: 0.6rem 0.75rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
}

.inventory-table tbody tr:hover {
    background: var(--light-bg);
}

.inventory-table tbody tr:last-child td {
    border-bottom: none;
}

/* Stock Badges */
.stock-badge {
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
}

.stock-badge.critical {
    background: rgba(231, 76, 60, 0.1);
    color: var(--primary-red);
}

.stock-badge.low {
    background: rgba(230, 126, 34, 0.1);
    color: var(--primary-orange);
}

.stock-badge.good {
    background: rgba(39, 174, 96, 0.1);
    color: var(--primary-green);
}

/* Edit Button */
.btn-edit {
    background: var(--primary-blue);
    color: white;
    border: none;
    padding: 0.4rem 0.8rem;
    border-radius: 5px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.btn-edit:hover {
    background: #043a52;
    transform: translateY(-1px);
}

/* Updates Table Styles */
.updates-table {
    width: 100%;
    border-collapse: collapse;
}

.updates-table thead {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.updates-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 700;
    color: var(--text-dark);
    border-bottom: 2px solid var(--border-color);
    white-space: nowrap;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.updates-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
    font-size: 0.9rem;
    color: var(--text-dark);
}

.updates-table tbody tr {
    transition: all 0.2s ease;
}

.updates-table tbody tr:hover {
    background: #f8f9fa;
    box-shadow: inset 0 0 0 1px var(--border-color);
}

.updates-table tbody tr:last-child td {
    border-bottom: none;
}

.update-time-badge {
    background: rgba(52, 152, 219, 0.1);
    color: var(--primary-blue);
    padding: 0.4rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
    display: inline-block;
}

.stock-value {
    background: rgba(39, 174, 96, 0.1);
    color: var(--primary-green);
    padding: 0.4rem 0.75rem;
    border-radius: 6px;
    font-weight: 700;
    display: inline-block;
    min-width: 50px;
    text-align: center;
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: var(--text-light);
}

.empty-state i {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}

/* Form Styles */
.form-group {
    margin-bottom: 1.25rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.form-label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--text-dark);
    font-size: 0.9rem;
}

.form-control, .form-select {
    width: 100%;
    padding: 0.6rem 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-size: 0.9rem;
    color: var(--text-dark);
    background: white;
    transition: all 0.2s ease;
}

.form-control:focus, .form-select:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(6,82,117,0.06);
}

.form-control::placeholder {
    color: var(--text-light);
}

/* Modal Styles */
.modal-content {
    border: none;
    border-radius: 10px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
}

.modal-header {
    background: var(--light-bg);
    border: none;
    border-bottom: 1px solid var(--border-color);
    padding: 1rem;
}

.modal-title {
    font-weight: 700;
    color: var(--text-dark);
    font-size: 1.1rem;
}

.modal-body {
    padding: 1rem;
}

.modal-footer {
    background: var(--light-bg);
    border-top: 1px solid var(--border-color);
    padding: 0.75rem;
}

.btn-secondary {
    background: #95a5a6;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    background: #7f8c8d;
}

.btn-primary {
    background: var(--primary-blue);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary:hover:not(:disabled) {
    background: #2980b9;
    transform: translateY(-2px);
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* PIN Modal Styles */
.pin-modal-container {
    padding: 0.5rem 0;
}

.pin-icon-header {
    text-align: center;
    margin-bottom: 1.5rem;
}

.pin-icon-header i {
    font-size: 2.5rem;
    color: var(--primary-blue);
    opacity: 0.8;
}

.pin-input-wrapper {
    margin: 1.5rem 0 1rem 0;
}

.pin-input-field {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    font-size: 1.4rem;
    text-align: center;
    letter-spacing: 12px;
    font-weight: bold;
    cursor: text;
    background: white;
    color: var(--primary-blue);
    transition: all 0.3s ease;
    font-family: 'Courier New', monospace;
}

.pin-input-field::placeholder {
    color: #bdc3c7;
    opacity: 1;
}

.pin-input-field:focus {
    outline: none;
    border-color: var(--primary-blue);
    background: rgba(52, 152, 219, 0.02);
    box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.15);
}

.pin-input-field:active {
    border-color: var(--primary-blue);
}

.pin-display-container {
    margin: 1.5rem 0;
    padding: 1rem 0;
}

.pin-display {
    display: flex;
    justify-content: center;
    gap: 12px;
    pointer-events: none;
}

.pin-dot {
    width: 48px;
    height: 48px;
    border: 2px solid var(--border-color);
    border-radius: 50%;
    background: white;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: transparent;
    position: relative;
}

.pin-dot .dot-inner {
    color: transparent;
    transition: all 0.2s ease;
    display: block;
}

.pin-dot.filled {
    background: var(--primary-blue);
    border-color: var(--primary-blue);
    color: white;
    font-weight: bold;
}

.alert {
    padding: 1rem;
    border-radius: 6px;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.alert-danger {
    background: rgba(231, 76, 60, 0.1);
    color: var(--primary-red);
    border-left: 4px solid var(--primary-red);
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .search-input {
        min-width: 100%;
    }

    .inventory-table {
        font-size: 0.85rem;
    }

    .inventory-table th, .inventory-table td {
        padding: 0.75rem 0.5rem;
    }

    .form-row {
        grid-template-columns: 1fr;
    }

    .btn-edit {
        padding: 0.3rem 0.6rem;
        font-size: 0.75rem;
    }
}
</style>

<script src="../assets/js/sweetalert-helper.js"></script>
<script>
// Pass server flash messages to JS for SweetAlert display
<?php if (isset($_SESSION['success'])): ?>
    <?php $msg = $_SESSION['success']; unset($_SESSION['success']); ?>
    window.flash = <?php echo json_encode(['type' => 'success', 'message' => $msg]); ?>;
<?php elseif (isset($_SESSION['error'])): ?>
    <?php $msg = $_SESSION['error']; unset($_SESSION['error']); ?>
    window.flash = <?php echo json_encode(['type' => 'error', 'message' => $msg]); ?>;
<?php else: ?>
    window.flash = null;
<?php endif; ?>
// Search functionality
document.getElementById('searchInventory').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('.inventory-row');
    
    rows.forEach(row => {
        const productName = row.querySelector('td:first-child').textContent.toLowerCase();
        row.style.display = productName.includes(searchTerm) ? '' : 'none';
    });
});

// Load product image and auto-populate stock
function loadProductImage() {
    const productId = document.getElementById('productId').value;
    if (!productId) {
        document.getElementById('productImageContainer').style.display = 'none';
        document.getElementById('quantity').value = '';
        document.getElementById('closing_stock').value = '';
        document.getElementById('currentStockDisplay').textContent = '0';
        document.getElementById('closingStockDisplay').textContent = '0';
        return;
    }
    
    const selectedOption = document.querySelector(`#productId option[value="${productId}"]`);
    if (!selectedOption) return;
    
    // Get data from option
    const productName = selectedOption.getAttribute('data-product-name');
    const imagePath = selectedOption.getAttribute('data-image');
    const currentStock = selectedOption.getAttribute('data-current-stock');
    const closingStock = selectedOption.getAttribute('data-closing-stock');
    
    // Display current and closing stock info
    document.getElementById('currentStockDisplay').textContent = currentStock || '0';
    document.getElementById('closingStockDisplay').textContent = closingStock || '0';
    
    // Clear quantity input when loading new product
    document.getElementById('quantity').value = '';
    
    // Show the container first
    document.getElementById('productImageContainer').style.display = 'block';
    const img = document.getElementById('productImage');
    
    // If no image path, show placeholder
    if (!imagePath || imagePath.trim() === '') {
        createPlaceholderImage();
        return;
    }
    
    // Adjust path if it's a relative path from root
    let fullImagePath = imagePath;
    if (!imagePath.startsWith('../') && !imagePath.startsWith('http')) {
        // It's a relative path from root, need to add ../
        fullImagePath = '../' + imagePath;
    }
    
    // Try loading the image with adjusted path
    const tempImg = new Image();
    tempImg.onload = function() {
        img.src = fullImagePath;
    };
    tempImg.onerror = function() {
        // Try without the ../ prefix in case paths are already correct
        const altPaths = [
            imagePath, // Original path
            '../' + imagePath, // With ../
            imagePath.replace('uploads/products/', '../uploads/products/'), // Explicit
        ];
        
        tryAlternatePath(0, altPaths, img, productName, productId);
    };
    tempImg.src = fullImagePath;
}

function tryAlternatePath(index, paths, imgElement, productName, productId) {
    if (index >= paths.length) {
        // All paths failed, create colored placeholder
        createPlaceholderImage();
        return;
    }
    
    const testImg = new Image();
    testImg.onload = function() {
        imgElement.src = paths[index];
    };
    testImg.onerror = function() {
        tryAlternatePath(index + 1, paths, imgElement, productName, productId);
    };
    testImg.src = paths[index];
}

function createPlaceholderImage() {
    const img = document.getElementById('productImage');
    const productId = document.getElementById('productId').value;
    const selectedOption = document.querySelector(`#productId option[value="${productId}"]`);
    const productName = selectedOption ? selectedOption.getAttribute('data-product-name') : 'Product';
    
    // Create canvas with colored placeholder
    const canvas = document.createElement('canvas');
    canvas.width = 300;
    canvas.height = 200;
    
    const ctx = canvas.getContext('2d');
    const colors = ['#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6'];
    const colorIndex = productId.charCodeAt(0) % colors.length;
    const bgColor = colors[colorIndex];
    
    ctx.fillStyle = bgColor;
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 16px Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(productName.substring(0, 20), canvas.width / 2, canvas.height / 2);
    
    img.src = canvas.toDataURL();
}

// Edit stock function - simplified
function editStock(productId) {
    document.getElementById('productId').value = productId;
    document.getElementById('quantity').value = '';
    document.getElementById('closing_stock').value = '';
    // Reset form
    document.querySelector('select[name="type"]').value = 'add';
    document.querySelector('textarea[name="reason"]').value = '';
    // Load the product image and info
    loadProductImage();
}

// Proceed with update - now with smart PIN logic
function proceedWithUpdate() {
    const productId = document.getElementById('productId').value;
    const quantity = document.getElementById('quantity').value;
    const type = document.querySelector('select[name="type"]').value;
    const reason = document.querySelector('textarea[name="reason"]').value;
    const closingStock = document.querySelector('input[name="closing_stock"]').value;
    
    if (!productId || !quantity || !reason) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Fields',
            text: 'Please fill in all required fields',
            confirmButtonColor: '#3498db'
        });
        return;
    }
    
    const productText = document.querySelector(`#productId option[value="${productId}"]`).textContent;
    
    // ALL operations require PIN verification
    // Store values in hidden fields
    document.getElementById('pinProductId').value = productId;
    document.getElementById('pinQuantity').value = quantity;
    document.getElementById('pinType').value = type;
    document.getElementById('pinReason').value = reason;
    document.getElementById('pinClosingStock').value = closingStock;
    
    // Close update modal and show PIN modal
    bootstrap.Modal.getInstance(document.getElementById('updateModal')).hide();
    
    setTimeout(() => {
        const pinModal = new bootstrap.Modal(document.getElementById('pinModal'));
        pinModal.show();
        document.getElementById('pinInput').focus();
    }, 300);
}

// PIN input handling
if (document.getElementById('pinInput')) {
    document.getElementById('pinInput').addEventListener('input', function(e) {
        // Allow only numbers
        this.value = this.value.replace(/\D/g, '');
        
        // Limit to 6 digits
        if (this.value.length > 6) {
            this.value = this.value.slice(0, 6);
        }
        
        // Update PIN display dots
        updatePinDisplay();
        
        // Set the hidden field value
        const pinHidden = document.getElementById('pinHidden');
        if (pinHidden) {
            pinHidden.value = this.value;
        }
        
        // Hide error
        const errorDiv = document.getElementById('pinError');
        if (errorDiv) errorDiv.style.display = 'none';
        
        // Enable button when 6 digits
        const confirmBtn = document.getElementById('confirmPinBtn');
        if (confirmBtn) confirmBtn.disabled = this.value.length !== 6;
    });
    
    // Enter key on PIN
    document.getElementById('pinInput').addEventListener('keyup', function(e) {
        if (e.key === 'Enter' && this.value.length === 6) {
            submitPin();
        }
    });
}

function updatePinDisplay() {
    const pinInput = document.getElementById('pinInput');
    if (!pinInput) return;
    
    const pin = pinInput.value;
    const dots = document.querySelectorAll('.pin-dot');
    
    dots.forEach((dot, index) => {
        if (index < pin.length) {
            dot.classList.add('filled');
            const innerSpan = dot.querySelector('.dot-inner');
            if (innerSpan) {
                innerSpan.style.color = 'white';
            }
        } else {
            dot.classList.remove('filled');
            const innerSpan = dot.querySelector('.dot-inner');
            if (innerSpan) {
                innerSpan.style.color = 'transparent';
            }
        }
    });
}

// Submit PIN
function submitPin() {
    const pinInput = document.getElementById('pinInput');
    if (!pinInput) return;
    
    const pin = pinInput.value;
    
    // Validate PIN length
    if (pin.length !== 6) {
        const errorDiv = document.getElementById('pinError');
        const errorMsg = document.getElementById('pinErrorMessage');
        if (errorDiv && errorMsg) {
            errorMsg.textContent = 'PIN must be 6 digits';
            errorDiv.style.display = 'flex';
        }
        return;
    }
    
    // Set the hidden field value
    const pinHidden = document.getElementById('pinHidden');
    if (pinHidden) {
        pinHidden.value = pin;
    }
    
    // Show loading state
    const confirmBtn = document.getElementById('confirmPinBtn');
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Verifying...';
    }
    
    // Submit after brief delay
    setTimeout(() => {
        const pinForm = document.getElementById('pinForm');
        if (pinForm) {
            pinForm.submit();
        }
    }, 300);
}

// Close alerts
document.querySelectorAll('.close-alert').forEach(btn => {
    btn.addEventListener('click', function() {
        this.closest('.alert-box').remove();
    });
});

// Show SweetAlert for server flash messages (if any)
if (window.flash) {
    const f = window.flash;
    Swal.fire({
        icon: f.type === 'success' ? 'success' : 'error',
        title: f.type === 'success' ? 'Success' : 'Error',
        text: f.message,
        confirmButtonColor: '#3498db',
        timer: 3500
    });
}
</script>

<?php include '../includes/footer.php'; ?>
