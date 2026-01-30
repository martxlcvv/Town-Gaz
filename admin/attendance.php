<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_admin();
prevent_cache();

$page_title = "Attendance";

// Get date filter - keeps values on refresh
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Get attendance type filter
$attendance_type = isset($_GET['type']) ? $_GET['type'] : 'all';

// Get employee attendance records
$employee_attendance_sql = "SELECT a.*, u.full_name, u.username
                   FROM attendance a
                   JOIN users u ON a.user_id = u.user_id
                   WHERE DATE(a.login_time) BETWEEN '$date_from' AND '$date_to'
                   ORDER BY a.login_time DESC";
$employee_attendance_result = mysqli_query($conn, $employee_attendance_sql);

// Get rider attendance records with profile images
$rider_attendance_sql = "SELECT ra.*, r.rider_name as full_name, r.contact as username, r.profile_image
                        FROM rider_attendance ra
                        JOIN riders r ON ra.rider_id = r.rider_id
                        WHERE DATE(ra.login_time) BETWEEN '$date_from' AND '$date_to'
                        ORDER BY ra.login_time DESC";
$rider_attendance_result = mysqli_query($conn, $rider_attendance_sql);

// Get daily attendance count for chart (combined)
$chart_sql = "SELECT DATE(login_time) as date, COUNT(DISTINCT user_id) as employee_count, 0 as rider_count
              FROM attendance
              WHERE DATE(login_time) BETWEEN '$date_from' AND '$date_to'
              GROUP BY DATE(login_time)
              UNION ALL
              SELECT DATE(login_time) as date, 0 as employee_count, COUNT(DISTINCT rider_id) as rider_count
              FROM rider_attendance
              WHERE DATE(login_time) BETWEEN '$date_from' AND '$date_to'
              GROUP BY DATE(login_time)
              ORDER BY date";
$chart_result = mysqli_query($conn, $chart_sql);
$chart_data = [];
while ($row = mysqli_fetch_assoc($chart_result)) {
    $date_key = date('M d', strtotime($row['date']));
    if (!isset($chart_data[$date_key])) {
        $chart_data[$date_key] = ['date' => $date_key, 'employee_count' => 0, 'rider_count' => 0];
    }
    $chart_data[$date_key]['employee_count'] += $row['employee_count'];
    $chart_data[$date_key]['rider_count'] += $row['rider_count'];
}
$chart_data = array_values($chart_data);

// Get employee summary
$users_sql = "SELECT u.full_name, 
              COUNT(DISTINCT DATE(a.login_time)) as days_present,
              SEC_TO_TIME(AVG(TIME_TO_SEC(TIMEDIFF(a.logout_time, a.login_time)))) as avg_hours
              FROM users u
              LEFT JOIN attendance a ON u.user_id = a.user_id 
                  AND DATE(a.login_time) BETWEEN '$date_from' AND '$date_to'
              WHERE u.status = 'active'
              GROUP BY u.user_id
              ORDER BY days_present DESC";
$users_result = mysqli_query($conn, $users_sql);

// Get rider summary
$riders_sql = "SELECT r.rider_name as full_name, 
              COUNT(DISTINCT DATE(ra.login_time)) as days_present,
              SEC_TO_TIME(AVG(TIME_TO_SEC(TIMEDIFF(ra.logout_time, ra.login_time)))) as avg_hours
              FROM riders r
              LEFT JOIN rider_attendance ra ON r.rider_id = ra.rider_id 
                  AND DATE(ra.login_time) BETWEEN '$date_from' AND '$date_to'
              WHERE r.status = 'active'
              GROUP BY r.rider_id
              ORDER BY days_present DESC";
$riders_result = mysqli_query($conn, $riders_sql);

$total_employee_records = mysqli_num_rows($employee_attendance_result);
$total_rider_records = mysqli_num_rows($rider_attendance_result);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
:root {
    --primary-blue: #3498db;
    --primary-warning: #f39c12;
    --light-bg: #f8f9fa;
    --card-bg: #ffffff;
    --text-dark: #2c3e50;
    --text-light: #7f8c8d;
    --border-color: #e9ecef;
    --shadow-light: 0 2px 10px rgba(0,0,0,0.04);
    --shadow-md: 0 4px 20px rgba(0,0,0,0.08);
}

body {
    background-color: var(--light-bg);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    font-size: 14px;
}

/* Dashboard Header Style - Same as Dashboard */
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
    gap: 1rem;
    align-items: center;
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

.btn-print, .btn-primary, .btn-warning {
    border-radius: 10px;
    padding: 8px 16px;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.3s ease;
}

.btn-print {
    background: var(--primary-blue);
    border: none;
    color: white;
}

.btn-print:hover {
    background: #2980b9;
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
    color: white;
}

.card {
    border: none;
    border-radius: 12px;
    box-shadow: var(--shadow-light);
    margin-bottom: 1.5rem;
}

.card-header {
    background: var(--light-bg);
    border: none;
    font-weight: 700;
    padding: 1rem;
    font-size: 0.95rem;
    border-bottom: 1px solid var(--border-color);
}

.card-body {
    padding: 1rem;
}

.table {
    margin-bottom: 0;
    font-size: 0.9rem;
}

.table thead th {
    background: var(--light-bg);
    color: var(--text-dark);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.6px;
    border-bottom: 2px solid var(--border-color);
    padding: 1rem 0.75rem;
    vertical-align: middle;
}

.table tbody td {
    padding: 1rem 0.75rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-dark);
}

.table tbody tr {
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(34, 211, 238, 0.05) 100%);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.attendance-user {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-avatar {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: 2px solid var(--border-color);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1rem;
    overflow: hidden;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-details {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.user-details strong {
    display: block;
    font-size: 0.9rem;
    color: var(--text-dark);
    font-weight: 600;
}

.user-details small {
    color: var(--text-light);
    font-size: 0.75rem;
}

.badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.7rem;
}

.form-control, .form-select {
    border-radius: 8px;
    border: 2px solid var(--border-color);
    padding: 8px 12px;
    font-size: 0.85rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.nav-tabs {
    border: none;
    gap: 0.5rem;
}

.nav-tabs .nav-link {
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--text-light);
    background: var(--light-bg);
    transition: all 0.3s ease;
}

.nav-tabs .nav-link:hover {
    background: #e9ecef;
    color: var(--text-dark);
}

.nav-tabs .nav-link.active {
    background: var(--primary-blue);
    color: white;
}

.stats-card {
    background: var(--primary-blue);
    color: white;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: var(--shadow-light);
    margin-bottom: 1.5rem;
}

.stats-card.warning {
    background: var(--primary-warning);
    color: white;
}

.stats-card h3 {
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.stats-card p {
    margin: 0;
    font-size: 0.9rem;
}

.table-responsive {
    border-radius: 8px;
}

@media print {
    .no-print {
        display: none !important;
    }
    
    .card {
        box-shadow: none;
        border: 1px solid var(--border-color);
        page-break-inside: avoid;
    }
    
    .table {
        font-size: 10px;
    }
}

.print-header {
    display: none;
}

@media print {
    .print-header {
        display: block;
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 3px solid var(--primary-blue);
    }
    
    .print-header h1 {
        color: var(--primary-blue);
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 10px;
    }
    
    .print-header h2 {
        font-size: 18px;
        margin-bottom: 10px;
    }
    
    .print-header p {
        color: var(--text-light);
        font-size: 12px;
        margin: 3px 0;
    }
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-actions {
        width: 100%;
        margin-top: 1rem;
    }
    
    .table {
        font-size: 0.8rem;
    }
    
    .table thead th,
    .table tbody td {
        padding: 0.5rem;
    }
    
    .user-avatar {
        width: 32px;
        height: 32px;
    }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <!-- Print Header -->
        <div class="print-header">
            <h1>TOWN GAS STORE</h1>
            <h2>Attendance Report</h2>
            <p>Period: <?php echo date('F d, Y', strtotime($date_from)); ?> - <?php echo date('F d, Y', strtotime($date_to)); ?></p>
            <p>Generated on: <?php echo date('F d, Y h:i A'); ?></p>
        </div>

        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <h1 class="animate-slide-in">
                    <i class="bi bi-clock-history me-2"></i>Attendance Management
                </h1>
                <p class="mb-0 animate-fade-in">
                    Track employee and rider attendance records
                </p>
            </div>
            <div class="header-actions">
                <a href="mark-rider-attendance.php" class="btn btn-warning me-2">
                    <i class="bi bi-bicycle me-2"></i>Mark Rider Attendance
                </a>
                <button onclick="window.print()" class="btn btn-print">
                    <i class="bi bi-printer me-2"></i>Print Report
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4 no-print">
            <div class="card-header">
                <i class="bi bi-funnel me-2"></i>Filter Attendance Records
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Date From</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Date To</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Type</label>
                        <select class="form-select" name="type">
                            <option value="all" <?php echo $attendance_type == 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="employees" <?php echo $attendance_type == 'employees' ? 'selected' : ''; ?>>Employees Only</option>
                            <option value="riders" <?php echo $attendance_type == 'riders' ? 'selected' : ''; ?>>Riders Only</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-2"></i>Apply Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <!-- Attendance Records -->
            <div class="col-lg-8 mb-4">
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-3 no-print" role="tablist">
                    <?php if ($attendance_type == 'all' || $attendance_type == 'employees'): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="employees-tab" data-bs-toggle="tab" data-bs-target="#employees" type="button" role="tab">
                            <i class="bi bi-person-badge me-2"></i>Employees
                        </button>
                    </li>
                    <?php endif; ?>
                    <?php if ($attendance_type == 'all' || $attendance_type == 'riders'): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $attendance_type == 'riders' ? 'active' : ''; ?>" id="riders-tab" data-bs-toggle="tab" data-bs-target="#riders" type="button" role="tab">
                            <i class="bi bi-bicycle me-2"></i>Riders
                        </button>
                    </li>
                    <?php endif; ?>
                </ul>

                <div class="tab-content">
                    <!-- Employee Attendance -->
                    <?php if ($attendance_type == 'all' || $attendance_type == 'employees'): ?>
                    <div class="tab-pane fade <?php echo ($attendance_type == 'all' || $attendance_type == 'employees') && $attendance_type != 'riders' ? 'show active' : ''; ?>" id="employees" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-list me-2"></i>Employee Attendance Records
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Date</th>
                                                <th>Time In</th>
                                                <th>Time Out</th>
                                                <th>Hours Worked</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($total_employee_records > 0): ?>
                                                <?php mysqli_data_seek($employee_attendance_result, 0); ?>
                                                <?php while ($att = mysqli_fetch_assoc($employee_attendance_result)): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="attendance-user">
                                                                <div class="user-avatar">
                                                                    <?php echo strtoupper(substr($att['full_name'], 0, 1)); ?>
                                                                </div>
                                                                <div class="user-details">
                                                                    <strong><?php echo htmlspecialchars($att['full_name']); ?></strong>
                                                                    <small>@<?php echo htmlspecialchars($att['username']); ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><strong><?php echo date('M d, Y', strtotime($att['login_time'])); ?></strong></td>
                                                        <td>
                                                            <i class="bi bi-clock text-success"></i> 
                                                            <?php echo date('h:i A', strtotime($att['login_time'])); ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($att['logout_time']): ?>
                                                                <i class="bi bi-clock text-danger"></i>
                                                                <?php echo date('h:i A', strtotime($att['logout_time'])); ?>
                                                            <?php else: ?>
                                                                <span class="badge bg-success">
                                                                    <i class="bi bi-record-circle"></i> Active
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            if ($att['logout_time']) {
                                                                $diff = strtotime($att['logout_time']) - strtotime($att['login_time']);
                                                                $hours = floor($diff / 3600);
                                                                $minutes = floor(($diff % 3600) / 60);
                                                                echo "<strong>{$hours}h {$minutes}m</strong>";
                                                            } else {
                                                                echo '<span class="text-muted">-</span>';
                                                            }
                                                            ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-5">
                                                        <i class="bi bi-inbox display-4 d-block mb-3"></i>
                                                        <p class="mb-0">No employee attendance records found</p>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Employee Summary -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <i class="bi bi-people me-2"></i>Employee Summary
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Days Present</th>
                                                <th>Avg Hours/Day</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                                                    <td>
                                                        <span class="badge bg-primary">
                                                            <?php echo $user['days_present']; ?> days
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?php echo $user['avg_hours'] ? substr($user['avg_hours'], 0, 5) : '0:00'; ?> hrs
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Rider Attendance -->
                    <?php if ($attendance_type == 'all' || $attendance_type == 'riders'): ?>
                    <div class="tab-pane fade <?php echo $attendance_type == 'riders' ? 'show active' : ($attendance_type == 'all' ? '' : ''); ?>" id="riders" role="tabpanel">
                        <div class="card">
                            <div class="card-header" style="background: var(--warning-gradient);">
                                <i class="bi bi-list me-2"></i>Rider Attendance Records
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Rider</th>
                                                <th>Date</th>
                                                <th>Time In</th>
                                                <th>Time Out</th>
                                                <th>Hours Worked</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($total_rider_records > 0): ?>
                                                <?php mysqli_data_seek($rider_attendance_result, 0); ?>
                                                <?php while ($att = mysqli_fetch_assoc($rider_attendance_result)): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="attendance-user">
                                                                <div class="user-avatar">
                                                                    <?php if ($att['profile_image']): ?>
                                                                        <img src="../assets/images/profiles/<?php echo htmlspecialchars($att['profile_image']); ?>" 
                                                                             alt="<?php echo htmlspecialchars($att['full_name']); ?>">
                                                                    <?php else: ?>
                                                                        <?php echo strtoupper(substr($att['full_name'], 0, 1)); ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="user-details">
                                                                    <strong><?php echo htmlspecialchars($att['full_name']); ?></strong>
                                                                    <small><i class="bi bi-phone"></i> <?php echo htmlspecialchars($att['username']); ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><strong><?php echo date('M d, Y', strtotime($att['login_time'])); ?></strong></td>
                                                        <td>
                                                            <i class="bi bi-clock text-success"></i> 
                                                            <?php echo date('h:i A', strtotime($att['login_time'])); ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($att['logout_time']): ?>
                                                                <i class="bi bi-clock text-danger"></i>
                                                                <?php echo date('h:i A', strtotime($att['logout_time'])); ?>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning text-dark">
                                                                    <i class="bi bi-record-circle"></i> Active
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            if ($att['logout_time']) {
                                                                $diff = strtotime($att['logout_time']) - strtotime($att['login_time']);
                                                                $hours = floor($diff / 3600);
                                                                $minutes = floor(($diff % 3600) / 60);
                                                                echo "<strong>{$hours}h {$minutes}m</strong>";
                                                            } else {
                                                                echo '<span class="text-muted">-</span>';
                                                            }
                                                            ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-5">
                                                        <i class="bi bi-inbox display-4 d-block mb-3"></i>
                                                        <p class="mb-0">No rider attendance records found</p>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Rider Summary -->
                        <div class="card mt-4">
                            <div class="card-header" style="background: var(--warning-gradient);">
                                <i class="bi bi-bicycle me-2"></i>Rider Summary
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Rider</th>
                                                <th>Days Present</th>
                                                <th>Avg Hours/Day</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($rider = mysqli_fetch_assoc($riders_result)): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($rider['full_name']); ?></strong></td>
                                                    <td>
                                                        <span class="badge bg-warning text-dark">
                                                            <?php echo $rider['days_present']; ?> days
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?php echo $rider['avg_hours'] ? substr($rider['avg_hours'], 0, 5) : '0:00'; ?> hrs
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chart & Stats -->
            <div class="col-lg-4 mb-4">
                <!-- Employee Stats Card -->
                <?php if ($attendance_type == 'all' || $attendance_type == 'employees'): ?>
                <div class="stats-card no-print">
                    <div class="text-center">
                        <i class="bi bi-person-badge display-4 mb-3"></i>
                        <h3><?php echo $total_employee_records; ?></h3>
                        <p>Employee Records</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Rider Stats Card -->
                <?php if ($attendance_type == 'all' || $attendance_type == 'riders'): ?>
                <div class="stats-card warning no-print">
                    <div class="text-center">
                        <i class="bi bi-bicycle display-4 mb-3"></i>
                        <h3><?php echo $total_rider_records; ?></h3>
                        <p>Rider Records</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Chart -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-bar-chart me-2"></i>Daily Attendance
                    </div>
                    <div class="card-body">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('attendanceChart');
if (ctx) {
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($chart_data, 'date')); ?>,
            datasets: [
                {
                    label: 'Employees',
                    data: <?php echo json_encode(array_column($chart_data, 'employee_count')); ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.7)',
                    borderColor: '#667eea',
                    borderWidth: 2,
                    borderRadius: 10
                },
                {
                    label: 'Riders',
                    data: <?php echo json_encode(array_column($chart_data, 'rider_count')); ?>,
                    backgroundColor: 'rgba(255, 212, 59, 0.7)',
                    borderColor: '#fab005',
                    borderWidth: 2,
                    borderRadius: 10
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                },
                x: {
                    stacked: false
                }
            }
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>