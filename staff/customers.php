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
    <style>
    :root{--primary-blue:#065275;--primary-green:#00547a;--light-bg:#f8f9fa;--card-bg:#fff;--text-dark:#2c3e50;--muted:#7f8c8d}
    .dashboard-header{background:linear-gradient(135deg,#1a4d5c 0%,#0f3543 100%);border-radius:12px;padding:2rem 1.5rem;margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;box-shadow:0 6px 30px rgba(26,77,92,0.25);color:#fff}
    .header-content{flex:1}
    .header-content h1{font-size:1.8rem;font-weight:700;margin:0 0 0.25rem 0;text-shadow:0 1px 2px rgba(0,0,0,0.1)}
    .header-content p{font-size:0.95rem;margin:0;opacity:0.9;color:#e0f2f7}
    .header-actions{display:flex;gap:0.75rem;align-items:center}
    .card{border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.06);border:0}
    .card-body{padding:0.75rem}
    .table thead th{font-size:0.85rem;padding:0.5rem 0.75rem;color:var(--text-dark);border-bottom:1px solid #eef2f4}
    .table tbody td{padding:0.5rem 0.75rem;vertical-align:middle}
    .btn-primary{background:var(--primary-blue);border-color:var(--primary-blue)}
    .btn-primary:hover{background:var(--primary-green);border-color:var(--primary-green)}
    .btn-primary.header-btn{background:#fff;color:#1a4d5c;border:none;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
    .btn-primary.header-btn:hover{background:#e8f4f8;transform:translateY(-1px)}
    .text-muted{color:var(--muted)!important}
    .small-muted{font-size:0.85rem;color:var(--muted)}
    </style>
    <div class="container-fluid">
        <div class="dashboard-header">
            <div class="header-content">
                <h1><i class="bi bi-people me-2"></i>Customers</h1>
                <p>Manage customer information and transactions</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary header-btn" data-bs-toggle="modal" data-bs-target="#customerModal">
                    <i class="bi bi-plus-circle me-2"></i>Add Customer
                </button>
            </div>
        </div>

        <?php if (isset($_SESSION['success']) || isset($_SESSION['error'])): ?>
            <script>
                window.serverMessage = <?php echo json_encode([
                    'type' => isset($_SESSION['success']) ? 'success' : 'error',
                    'text' => isset($_SESSION['success']) ? $_SESSION['success'] : $_SESSION['error']
                ]); ?>;
            </script>
        <?php unset($_SESSION['success'], $_SESSION['error']); endif; ?>

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
                                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo (int)$customer['customer_id']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../assets/js/sweetalert-helper.js"></script>
<script>
// Show server message via SweetAlert
if (window.serverMessage) {
    Swal.fire({
        icon: window.serverMessage.type,
        title: window.serverMessage.type === 'success' ? 'Success' : 'Error',
        text: window.serverMessage.text,
        confirmButtonColor: '#065275'
    });
}

function confirmDelete(id) {
    Swal.fire({
        title: 'Delete this customer?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '?delete=' + encodeURIComponent(id);
        }
    });
}
</script>