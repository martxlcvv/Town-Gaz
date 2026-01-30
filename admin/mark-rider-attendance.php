<?php
// FILE: admin/mark-rider-attendance.php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
prevent_cache();

$page_title = "Mark Rider Attendance";
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if ($action == 'clock_in') {
        $rider_id = mysqli_real_escape_string($conn, $_POST['rider_id']);
        $login_time = mysqli_real_escape_string($conn, $_POST['login_time']);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // Check if already clocked in
        $check_sql = "SELECT * FROM rider_attendance 
                     WHERE rider_id = $rider_id 
                     AND DATE(login_time) = DATE('$login_time')
                     AND logout_time IS NULL";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error = "Rider is already clocked in!";
        } else {
            $sql = "INSERT INTO rider_attendance (rider_id, login_time, ip_address) 
                   VALUES ($rider_id, '$login_time', '$ip_address')";
            if (mysqli_query($conn, $sql)) {
                $success = "Rider clocked in successfully!";
            } else {
                $error = "Error: " . mysqli_error($conn);
            }
        }
    } 
    elseif ($action == 'clock_out') {
        $attendance_id = mysqli_real_escape_string($conn, $_POST['attendance_id']);
        $logout_time = mysqli_real_escape_string($conn, $_POST['logout_time']);
        
        $sql = "UPDATE rider_attendance 
               SET logout_time = '$logout_time' 
               WHERE attendance_id = $attendance_id";
        if (mysqli_query($conn, $sql)) {
            $success = "Rider clocked out successfully!";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
    elseif ($action == 'delete') {
        $attendance_id = mysqli_real_escape_string($conn, $_POST['attendance_id']);
        
        $sql = "DELETE FROM rider_attendance WHERE attendance_id = $attendance_id";
        if (mysqli_query($conn, $sql)) {
            $success = "Attendance record deleted successfully!";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Get all active riders
$riders_sql = "SELECT * FROM riders WHERE status = 'active' ORDER BY rider_name";
$riders_result = mysqli_query($conn, $riders_sql);

// Get today's active sessions
$today = date('Y-m-d');
$active_sql = "SELECT ra.*, r.rider_name 
              FROM rider_attendance ra
              JOIN riders r ON ra.rider_id = r.rider_id
              WHERE DATE(ra.login_time) = '$today'
              AND ra.logout_time IS NULL
              ORDER BY ra.login_time DESC";
$active_result = mysqli_query($conn, $active_sql);

// Get today's completed sessions
$completed_sql = "SELECT ra.*, r.rider_name 
                 FROM rider_attendance ra
                 JOIN riders r ON ra.rider_id = r.rider_id
                 WHERE DATE(ra.login_time) = '$today'
                 AND ra.logout_time IS NOT NULL
                 ORDER BY ra.logout_time DESC";
$completed_result = mysqli_query($conn, $completed_sql);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
/* Mark Rider Attendance Page - SAME DESIGN as Dashboard, Inventory, Products, Promotions, Customers, Sales, Users, Deliveries, Audit Logs, Settings, Riders & Attendance */
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

/* Welcome Header */
.welcome-header {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.5rem;
    color: var(--text-dark);
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-light);
    border-left: 5px solid #065275;
    border-top: 3px solid #00547a;
}

.welcome-header h1 {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.welcome-header p {
    color: var(--text-light);
    margin-bottom: 0;
}

.card {
    border: none;
    border-radius: 20px;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: var(--shadow-md);
}

.card-header {
    background: var(--light-bg);
    border-bottom: 1px solid var(--border-color);
    color: var(--text-dark);
    border-radius: 0;
    border: none;
    font-weight: 700;
    padding: 1.25rem;
}

.card-header.primary {
    border-left: 4px solid var(--primary-blue);
}

.card-header.success {
    border-left: 4px solid var(--primary-green);
}

.form-control, .form-select {
    border-radius: 12px;
    border: 2px solid #e9ecef;
    padding: 12px 16px;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #ffd43b;
    box-shadow: 0 0 0 4px rgba(255, 212, 59, 0.1);
}

.btn {
    border-radius: 12px;
    padding: 10px 20px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-warning {
    background: var(--primary-yellow);
    border: none;
    color: #d68910;
}

.btn-warning:hover {
    background: #f39c12;
    color: white;
}

.btn-success {
    background: var(--primary-green);
    border: none;
    color: white;
}

.btn-success:hover {
    background: #219653;
    color: white;
}

.btn-danger {
    background: var(--primary-red);
    border: none;
    color: white;
}

.btn-danger:hover {
    background: #c0392b;
    color: white;
}

.table thead th {
    background: var(--light-bg);
    color: var(--text-dark);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border-color);
    padding: 1rem;
}

.table tbody tr {
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    background: rgba(255, 212, 59, 0.05);
    transform: translateX(5px);
}

.badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 600;
}


.alert {
    border-radius: 15px;
    border: none;
}

.list-group-item {
    border-radius: 15px !important;
    border: none !important;
    background: #f8f9fa;
    margin-bottom: 10px;
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <div>
                <h1>
                    <i class="bi bi-bicycle me-2"></i>Mark Rider Attendance
                </h1>
                <p class="mb-0">
                    Manually clock in/out riders
                </p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Clock In Form -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Clock In Rider
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="clock_in">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Rider</label>
                                <select class="form-select" name="rider_id" required>
                                    <option value="">Choose rider...</option>
                                    <?php while ($rider = mysqli_fetch_assoc($riders_result)): ?>
                                        <option value="<?php echo $rider['rider_id']; ?>">
                                            <?php echo htmlspecialchars($rider['rider_name']); ?> - <?php echo htmlspecialchars($rider['contact']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Clock In Time</label>
                                <input type="datetime-local" class="form-control" name="login_time" 
                                       value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                            </div>
                            
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="bi bi-clock me-2"></i>Clock In
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Active Sessions -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header success">
                        <i class="bi bi-circle-fill me-2" style="animation: pulse 2s ease-in-out infinite;"></i>Active Sessions Today
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($active_result) > 0): ?>
                            <div class="list-group">
                                <?php while ($session = mysqli_fetch_assoc($active_result)): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($session['rider_name']); ?></h6>
                                                <small class="text-muted">
                                                    <i class="bi bi-clock"></i> In: <?php echo date('h:i A', strtotime($session['login_time'])); ?>
                                                </small>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-success" 
                                                    onclick="clockOut(<?php echo $session['attendance_id']; ?>, '<?php echo htmlspecialchars($session['rider_name']); ?>')">
                                                <i class="bi bi-box-arrow-right"></i> Clock Out
                                            </button>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-inbox display-4 d-block mb-3"></i>
                                <p class="mb-0">No active sessions</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Completed Sessions Today -->
        <div class="card">
            <div class="card-header primary">
                <i class="bi bi-check2-circle me-2"></i>Completed Sessions Today
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Rider</th>
                                <th>Clock In</th>
                                <th>Clock Out</th>
                                <th>Hours Worked</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($completed_result) > 0): ?>
                                <?php while ($session = mysqli_fetch_assoc($completed_result)): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($session['rider_name']); ?></strong></td>
                                        <td>
                                            <i class="bi bi-clock text-success"></i> 
                                            <?php echo date('h:i A', strtotime($session['login_time'])); ?>
                                        </td>
                                        <td>
                                            <i class="bi bi-clock text-danger"></i>
                                            <?php echo date('h:i A', strtotime($session['logout_time'])); ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $diff = strtotime($session['logout_time']) - strtotime($session['login_time']);
                                            $hours = floor($diff / 3600);
                                            $minutes = floor(($diff % 3600) / 60);
                                            ?>
                                            <strong><?php echo "{$hours}h {$minutes}m"; ?></strong>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-danger" 
                                                    onclick="deleteRecord(<?php echo $session['attendance_id']; ?>, '<?php echo htmlspecialchars($session['rider_name']); ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox display-4 d-block mb-3"></i>
                                        <p class="mb-0">No completed sessions today</p>
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

<!-- Clock Out Modal -->
<div class="modal fade" id="clockOutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px; border: none;">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-box-arrow-right me-2"></i>Clock Out Rider
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="clockOutForm">
                <input type="hidden" name="action" value="clock_out">
                <input type="hidden" name="attendance_id" id="attendance_id">
                <div class="modal-body">
                    <p>Clock out <strong id="riderName"></strong>?</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Clock Out Time</label>
                        <input type="datetime-local" class="form-control" name="logout_time" 
                               value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-2"></i>Clock Out
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px; border: none;">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-trash me-2"></i>Delete Attendance Record
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="attendance_id" id="delete_attendance_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete the attendance record for <strong id="deleteRiderName"></strong>?</p>
                    <p class="text-danger"><i class="bi bi-exclamation-triangle me-2"></i>This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-2"></i>Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function clockOut(attendanceId, riderName) {
    document.getElementById('attendance_id').value = attendanceId;
    document.getElementById('riderName').textContent = riderName;
    new bootstrap.Modal(document.getElementById('clockOutModal')).show();
}

function deleteRecord(attendanceId, riderName) {
    document.getElementById('delete_attendance_id').value = attendanceId;
    document.getElementById('deleteRiderName').textContent = riderName;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>