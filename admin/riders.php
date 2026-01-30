<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_admin();
prevent_cache();

$page_title = "Riders";

// Handle Add/Edit Rider
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_rider'])) {
    // Verify session token
    if (!verify_csrf_token($_POST['session_token'] ?? '')) {
        $_SESSION['error_message'] = "Invalid session. Please try again.";
        header('Location: riders.php');
        exit();
    }
    
    $rider_name = mysqli_real_escape_string($conn, $_POST['rider_name']);
    $contact = mysqli_real_escape_string($conn, $_POST['contact']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $profile_image = null;
    
    // Handle image upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['size'] > 0) {
        $file = $_FILES['profile_image'];
        $upload_dir = '../assets/images/profiles/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $new_filename = 'rider_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $profile_image = $new_filename;
            }
        }
    }
    
    if (isset($_POST['rider_id']) && !empty($_POST['rider_id'])) {
        $rider_id = (int)$_POST['rider_id'];
        
        if ($profile_image) {
            $sql = "UPDATE riders SET 
                    rider_name = '$rider_name',
                    contact = '$contact',
                    address = '$address',
                    status = '$status',
                    profile_image = '$profile_image'
                    WHERE rider_id = $rider_id";
        } else {
            $sql = "UPDATE riders SET 
                    rider_name = '$rider_name',
                    contact = '$contact',
                    address = '$address',
                    status = '$status'
                    WHERE rider_id = $rider_id";
        }
        $message = "Rider updated successfully";
    } else {
        $sql = "INSERT INTO riders (rider_name, contact, address, status, profile_image) 
                VALUES ('$rider_name', '$contact', '$address', '$status', " . ($profile_image ? "'$profile_image'" : "NULL") . ")";
        $message = "Rider added successfully";
    }
    
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = $message;
    } else {
        $_SESSION['error'] = "Error saving rider";
    }
    
    header('Location: riders.php');
    exit();
}

// Get riders with delivery stats
$riders_sql = "SELECT r.rider_id,
               r.rider_name,
               r.contact,
               r.address,
               r.status,
               r.profile_image,
               r.created_at,
               COUNT(CASE WHEN d.delivery_status IN ('assigned', 'picked_up', 'in_transit') THEN 1 END) as active_deliveries
               FROM riders r
               LEFT JOIN deliveries d ON r.rider_id = d.rider_id
               GROUP BY r.rider_id, r.rider_name, r.contact, r.address, r.status, r.profile_image, r.created_at
               ORDER BY r.rider_name";
$riders_result = mysqli_query($conn, $riders_sql);

// Count statistics
$total_riders = mysqli_num_rows($riders_result);
$active_riders = 0;
$inactive_riders = 0;

mysqli_data_seek($riders_result, 0);
while ($r = mysqli_fetch_assoc($riders_result)) {
    if ($r['status'] == 'active') $active_riders++;
    else $inactive_riders++;
}

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

.animate-slide-in {
    animation: slideLeft 0.5s ease-out;
    animation-fill-mode: both;
}

.animate-fade-in {
    animation: fadeIn 0.8s ease-out;
    animation-fill-mode: both;
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

/* Riders Page Styling */
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

.btn-add-rider {
    background: linear-gradient(135deg, #3498db, #2980b9);
    border: none;
    color: white;
    padding: 0.7rem 1.5rem;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.9rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}

.btn-add-rider:hover {
    background: linear-gradient(135deg, #2980b9, #1f618d);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.stat-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-top: 5px solid;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.stat-card.primary {
    border-top-color: var(--primary-blue);
}

.stat-card.success {
    border-top-color: var(--primary-green);
}

.stat-card.warning {
    border-top-color: var(--primary-orange);
}

.stat-card-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.stat-card.primary .stat-card-icon {
    background: linear-gradient(135deg, rgba(52, 152, 219, 0.15), rgba(52, 152, 219, 0.05));
    color: var(--primary-blue);
}

.stat-card.success .stat-card-icon {
    background: linear-gradient(135deg, rgba(39, 174, 96, 0.15), rgba(39, 174, 96, 0.05));
    color: var(--primary-green);
}

.stat-card.warning .stat-card-icon {
    background: linear-gradient(135deg, rgba(230, 126, 34, 0.15), rgba(230, 126, 34, 0.05));
    color: var(--primary-orange);
}

.stat-card-content h6 {
    color: var(--text-light);
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    margin-bottom: 0.5rem;
}

.stat-card-content h3 {
    color: var(--text-dark);
    font-weight: 800;
    font-size: 1.85rem;
    margin: 0;
    line-height: 1;
}

.content-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    overflow: hidden;
    margin-bottom: 1.5rem;
    border: 1px solid rgba(0, 0, 0, 0.04);
    transition: all 0.3s ease;
}

.content-card:hover {
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
}

.content-card-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f0f4f8 100%);
    border-bottom: 2px solid var(--border-color);
    padding: 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.content-card-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--text-dark);
    letter-spacing: 0.3px;
}

.table-wrapper {
    overflow-x: auto;
    padding: 1.25rem;
}

.riders-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 0.5rem;
    font-size: 0.9rem;
}

.riders-table thead th {
    background: var(--light-bg);
    color: var(--text-dark);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.6px;
    padding: 1rem 0.85rem;
    border-bottom: 2px solid var(--border-color);
    vertical-align: middle;
}

.riders-table tbody tr {
    background: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.riders-table tbody tr:hover {
    box-shadow: 0 6px 16px rgba(26, 77, 92, 0.12);
    transform: translateY(-2px);
}

.riders-table tbody td {
    padding: 1rem 0.85rem;
    vertical-align: middle;
    border: none;
    color: var(--text-dark);
}

.riders-table tbody tr td:first-child {
    border-radius: 10px 0 0 10px;
}

.riders-table tbody tr td:last-child {
    border-radius: 0 10px 10px 0;
}

.rider-avatar {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.25);
    object-fit: cover;
    border: 2px solid rgba(102, 126, 234, 0.2);
    flex-shrink: 0;
}

.rider-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 10px;
    object-fit: cover;
}

.badge-custom {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-block;
}

.badge-active {
    background: linear-gradient(135deg, rgba(39, 174, 96, 0.2), rgba(39, 174, 96, 0.1)) !important;
    color: #27ae60 !important;
    border: 1px solid rgba(39, 174, 96, 0.4);
}

.badge-inactive {
    background: linear-gradient(135deg, rgba(149, 165, 166, 0.2), rgba(149, 165, 166, 0.1)) !important;
    color: #7f8c8d !important;
    border: 1px solid rgba(149, 165, 166, 0.4);
}

.action-buttons {
    display: flex;
    gap: 0.35rem;
}

.btn-action {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 0.9rem;
    cursor: pointer;
    background: white;
}

.btn-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

.btn-edit {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    border: none;
}

.btn-edit:hover {
    background: linear-gradient(135deg, #2980b9, #1f618d);
    color: white;
}

.modal-content {
    border-radius: 12px;
    border: none;
    overflow: hidden;
}

.modal-header {
    background: var(--light-bg);
    border-bottom: 1px solid var(--border-color);
    padding: 1rem;
}

.modal-title {
    font-weight: 700;
    color: var(--text-dark);
}

.modal-body {
    padding: 1.5rem;
}

.form-label {
    font-weight: 600;
    color: #4a5568;
    margin-bottom: 0.4rem;
    font-size: 0.85rem;
}

.form-control {
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.6rem 0.8rem;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

.profile-image-preview {
    width: 80px;
    height: 80px;
    border-radius: 8px;
    margin-top: 0.5rem;
    object-fit: cover;
    border: 2px solid var(--border-color);
}

.btn-modal-action {
    padding: 0.6rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
}

.btn-primary {
    background: var(--primary-blue);
    border: none;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
    color: white;
}

.btn-secondary {
    padding: 0.6rem 1.5rem;
    font-size: 0.9rem;
}

.alert-custom {
    border-radius: 10px;
    border: none;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
    animation: slideDown 0.5s ease-out;
    margin-bottom: 1rem;
}

.d-flex {
    display: flex;
}

.align-items-center {
    align-items: center;
}

.gap-2 {
    gap: 0.5rem;
}

.flex-grow-1 {
    flex-grow: 1;
}

.align-items-start {
    align-items: flex-start;
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
}
</style>

<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.all.min.js"></script>
</style>

<div class="main-content">
    <div class="container-fluid">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <h1 class="animate-slide-in">
                    <i class="bi bi-person-badge me-2"></i>Riders Management
                </h1>
                <p class="mb-0 animate-fade-in">
                    Manage delivery riders and their information
                </p>
            </div>
            <div class="header-actions">
                <button class="btn btn-add-rider" data-bs-toggle="modal" data-bs-target="#riderModal">
                    <i class="bi bi-plus-circle me-2"></i>Add New Rider
                </button>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-custom alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-custom alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="d-flex align-items-start">
                    <div class="stat-card-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-card-content flex-grow-1">
                        <h6>Total Riders</h6>
                        <h3><?php echo $total_riders; ?></h3>
                    </div>
                </div>
            </div>
            <div class="stat-card success">
                <div class="d-flex align-items-start">
                    <div class="stat-card-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-card-content flex-grow-1">
                        <h6>Active Riders</h6>
                        <h3><?php echo $active_riders; ?></h3>
                    </div>
                </div>
            </div>
            <div class="stat-card warning">
                <div class="d-flex align-items-start">
                    <div class="stat-card-icon">
                        <i class="bi bi-pause-circle"></i>
                    </div>
                    <div class="stat-card-content flex-grow-1">
                        <h6>Inactive Riders</h6>
                        <h3><?php echo $inactive_riders; ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Riders Table -->
        <div class="content-card">
            <div class="content-card-header">
                <h5>
                    <i class="bi bi-list-check me-2"></i>All Riders
                </h5>
            </div>
            
            <div class="table-wrapper">
                <table class="riders-table">
                    <thead>
                        <tr>
                            <th>Rider</th>
                            <th>Contact</th>
                            <th>Address</th>
                            <th>Active</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($riders_result, 0);
                        if (mysqli_num_rows($riders_result) > 0):
                            while ($rider = mysqli_fetch_assoc($riders_result)): 
                        ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rider-avatar">
                                            <?php if ($rider['profile_image']): ?>
                                                <img src="../assets/images/profiles/<?php echo htmlspecialchars($rider['profile_image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($rider['rider_name']); ?>">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($rider['rider_name'], 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <strong><?php echo htmlspecialchars($rider['rider_name']); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($rider['contact']); ?></td>
                                <td><?php echo htmlspecialchars($rider['address']); ?></td>
                                <td><strong><?php echo $rider['active_deliveries']; ?></strong></td>
                                <td>
                                    <span class="badge badge-custom badge-<?php echo $rider['status'] == 'active' ? 'active' : 'inactive'; ?>">
                                        <?php echo ucfirst($rider['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn-action btn-edit" 
                                            onclick="editRider(<?php echo htmlspecialchars(json_encode($rider)); ?>)"
                                            title="Edit Rider">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox" style="font-size: 3rem; color: #cbd5e0;"></i>
                                    <p class="mb-0" style="margin-top: 0.75rem;">No riders found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Rider Modal -->
<div class="modal fade" id="riderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus me-2"></i><span id="modalTitle">Add New Rider</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="riderForm">
                <div class="modal-body">
                    <input type="hidden" name="rider_id" id="rider_id">
                    <input type="hidden" name="save_rider" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Rider Name *</label>
                        <input type="text" class="form-control" name="rider_name" id="rider_name" 
                               placeholder="Enter rider's full name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Contact Number *</label>
                        <input type="text" class="form-control" name="contact" id="contact" 
                               placeholder="e.g. 09171234567" pattern="[0-9]{11}" required>
                        <small class="text-muted">Format: 11 digits</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address *</label>
                        <input type="text" class="form-control" name="address" id="address" 
                               placeholder="Enter rider's address" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Profile Picture</label>
                        <input type="file" class="form-control" name="profile_image" id="profile_image" 
                               accept="image/jpeg,image/jpg,image/png,image/gif" onchange="previewImage(this)">
                        <small class="text-muted">JPG, PNG, GIF (Max: 5MB)</small>
                        <img id="imagePreview" class="profile-image-preview" style="display: none;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select class="form-control" name="status" id="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-modal-action" onclick="submitRiderForm(event)">
                        <i class="bi bi-save me-2"></i>Save Rider
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('imagePreview');
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function editRider(rider) {
    document.getElementById('modalTitle').textContent = 'Edit Rider';
    document.getElementById('rider_id').value = rider.rider_id || '';
    document.getElementById('rider_name').value = rider.rider_name || '';
    document.getElementById('contact').value = rider.contact || '';
    document.getElementById('address').value = rider.address || '';
    document.getElementById('status').value = rider.status || 'active';
    
    const preview = document.getElementById('imagePreview');
    if (rider.profile_image) {
        preview.src = '../assets/images/profiles/' + rider.profile_image;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
    
    new bootstrap.Modal(document.getElementById('riderModal')).show();
}

function submitRiderForm(event) {
    event.preventDefault();
    
    const form = document.getElementById('riderForm');
    const riderId = document.getElementById('rider_id').value;
    const isEdit = riderId ? true : false;
    
    const formData = new FormData(form);
    
    // Show loading
    Swal.fire({
        title: 'Processing...',
        text: 'Please wait while we save the rider information.',
        icon: 'info',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.ok ? Promise.resolve() : Promise.reject())
    .then(() => {
        Swal.fire({
            title: 'Success!',
            text: isEdit ? 'Rider updated successfully!' : 'Rider added successfully!',
            icon: 'success',
            confirmButtonColor: '#1a4d5c',
            confirmButtonText: 'OK',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then(() => {
            location.reload();
        });
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error!',
            text: 'An error occurred while saving the rider.',
            icon: 'error',
            confirmButtonColor: '#e74c3c'
        });
    });
}

// Reset form when modal is closed
document.getElementById('riderModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('modalTitle').textContent = 'Add New Rider';
    document.getElementById('riderForm').reset();
    document.getElementById('rider_id').value = '';
    document.getElementById('imagePreview').style.display = 'none';
});
</script>

<?php include '../includes/footer.php'; ?>