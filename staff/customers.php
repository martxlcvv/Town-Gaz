<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_staff();
prevent_cache();

$page_title = "Customers";

// Handle Add/Edit Customer
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_name = mysqli_real_escape_string($conn, $_POST['customer_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = isset($_POST['email']) ? mysqli_real_escape_string($conn, $_POST['email']) : '';
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    
    if (isset($_POST['customer_id']) && !empty($_POST['customer_id'])) {
        // Update
        $customer_id = (int)$_POST['customer_id'];
        $sql = "UPDATE customers SET 
                customer_name = '$customer_name',
                phone = '$phone',
                email = '$email',
                address = '$address'
                WHERE customer_id = $customer_id";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Customer updated successfully";
        } else {
            $_SESSION['error'] = "Error updating customer";
        }
    } else {
        // Insert
        $sql = "INSERT INTO customers (customer_name, phone, email, address) 
                VALUES ('$customer_name', '$phone', '$email', '$address')";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Customer added successfully";
        } else {
            $_SESSION['error'] = "Error adding customer";
        }
    }
    
    header('Location: customers.php');
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $customer_id = (int)$_GET['delete'];
    $sql = "DELETE FROM customers WHERE customer_id = $customer_id";
    
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Customer deleted successfully";
    } else {
        $_SESSION['error'] = "Error deleting customer";
    }
    
    header('Location: customers.php');
    exit();
}

// Get all customers
$customers_sql = "SELECT c.*, 
                  COUNT(DISTINCT s.sale_id) as total_orders,
                  IFNULL(SUM(s.total_amount), 0) as total_spent
                  FROM customers c
                  LEFT JOIN sales s ON c.customer_id = s.customer_id AND s.status = 'completed'
                  GROUP BY c.customer_id
                  ORDER BY c.created_at DESC";
$customers_result = mysqli_query($conn, $customers_sql);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col">
                <h2><i class="bi bi-people me-2"></i>Customers</h2>
                <p class="text-muted">Manage customer information</p>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal">
                    <i class="bi bi-plus-circle"></i> Add Customer
                </button>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Customers Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Customer Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Address</th>
                                <th>Total Orders</th>
                                <th>Total Spent</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($customer = mysqli_fetch_assoc($customers_result)): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($customer['customer_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($customer['phone'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($customer['email'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($customer['address']); ?></td>
                                    <td><?php echo $customer['total_orders']; ?></td>
                                    <td>₱<?php echo number_format($customer['total_spent'], 2); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick='editCustomer(<?php echo json_encode($customer, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="?delete=<?php echo $customer['customer_id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Delete this customer?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="customer_id" id="customer_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Customer Name *</label>
                        <input type="text" class="form-control" name="customer_name" id="customer_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone *</label>
                        <input type="text" class="form-control" name="phone" id="phone" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="email">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address *</label>
                        <textarea class="form-control" name="address" id="address" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCustomer(customer) {
    document.getElementById('modalTitle').textContent = 'Edit Customer';
    document.getElementById('customer_id').value = customer.customer_id;
    document.getElementById('customer_name').value = customer.customer_name;
    document.getElementById('phone').value = customer.phone || '';
    document.getElementById('email').value = customer.email || '';
    document.getElementById('address').value = customer.address;
    
    new bootstrap.Modal(document.getElementById('customerModal')).show();
}

// Reset form when modal is closed
document.getElementById('customerModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('modalTitle').textContent = 'Add Customer';
    document.querySelector('#customerModal form').reset();
});
</script>

<?php include '../includes/footer.php'; ?>