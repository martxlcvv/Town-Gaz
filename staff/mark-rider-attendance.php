<?php
// FILE: staff/mark-rider-attendance.php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';

// Check if user is staff
if ($_SESSION['role_name'] !== 'Staff') {
    header('Location: ../auth/login.php');
    exit();
}

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
:root {
    --primary-gradient: linear-gradient(135deg, #00A8E8 0%, #007EA7 100%);
    --success-gradient: linear-gradient(135deg, #51cf66 0%, #37b24d 100%);
    --warning-gradient: linear-gradient(135deg, #ffd43b 0%, #fab005 100%);
    --danger-gradient: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    --shadow-sm: 0 2px 8px rgba(0,0,0,0.08);
    --shadow-md: 0 4px 16px rgba(0,0,0,0.12);
}

.main-content {
    margin-left: 260px;
    padding: 30px;
    background: #f8f9fa;
    min-height: 100vh;
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
    background: var(--warning-gradient);
    color: white;
    border-radius: 20px 20px 0 0 !important;
    border: none;
    font-weight: 700;
    padding: 20px 24px;
}

.card-header.primary {
    background: var(--primary-gradient);
}

.card-header.success {
    background: var(--success-gradient);
}

.form-control, .form-select {
    border-radius: 12px;
    border: 2px solid #e9ecef;
    padding: 12px 16px;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #00A8E8;
    box-shadow: 0 0 0 4px rgba(0, 168, 232, 0.1);
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
    background: var(--warning-gradient);
    border: none;
    color: white;
}

.btn-success {
    background: var(--success-gradient);
    border: none;
}

.btn-danger {
    background: var(--danger-gradient);
    border: none;
}

.table thead th {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    color: #495057;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    border: none;
    padding: 15px;
}

.table tbody tr {
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    background: rgba(0, 168, 232, 0.05);
    transform: translateX(5px);
}

.badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 600;
}

.page-header {
    background: linear-gradient(135deg, rgba(6, 82, 117, 0.1) 0%, rgba(0, 84, 122, 0.1) 100%);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    border-left: 5px solid #065275;
    border-top: 3px solid #00547a;
}

.page-header h2 {
    font-weight: 700;
    color: #065275;
    margin-bottom: 10px;
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

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 15px;
    }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h2><i class="bi bi-bicycle me-2"></i>Mark Rider Attendance</h2>
            <p class="text-muted mb-0">Manually clock in/out riders</p>
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
            <div class="modal-header" style="background: var(--success-gradient); color: white; border-radius: 20px 20px 0 0;">
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
            <div class="modal-header" style="background: var(--danger-gradient); color: white; border-radius: 20px 20px 0 0;">
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