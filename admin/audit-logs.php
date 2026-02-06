<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_admin();
prevent_cache();

$page_title = "Audit Logs";

// Pagination
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Filters
$filter_user = isset($_GET['user']) ? $_GET['user'] : '';
$filter_action = isset($_GET['action']) ? $_GET['action'] : '';
$filter_table = isset($_GET['table']) ? $_GET['table'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($filter_user)) {
    $where_conditions[] = "al.user_id = ?";
    $params[] = $filter_user;
}

if (!empty($filter_action)) {
    $where_conditions[] = "al.action LIKE ?";
    $params[] = "%$filter_action%";
}

if (!empty($filter_table)) {
    $where_conditions[] = "al.table_name = ?";
    $params[] = $filter_table;
}

if (!empty($filter_date_from)) {
    $where_conditions[] = "DATE(al.created_at) >= ?";
    $params[] = $filter_date_from;
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "DATE(al.created_at) <= ?";
    $params[] = $filter_date_to;
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total records for pagination
$count_sql = "SELECT COUNT(*) as total FROM audit_logs al $where_sql";
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $count_sql);
    $types = str_repeat('s', count($params));
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $count_result = mysqli_stmt_get_result($stmt);
} else {
    $count_result = mysqli_query($conn, $count_sql);
}
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get audit logs
$logs_sql = "SELECT al.*, u.full_name, u.username 
             FROM audit_logs al
             JOIN users u ON al.user_id = u.user_id
             $where_sql
             ORDER BY al.created_at DESC
             LIMIT ? OFFSET ?";

$params[] = $records_per_page;
$params[] = $offset;

$stmt = mysqli_prepare($conn, $logs_sql);
$types = str_repeat('s', count($params) - 2) . 'ii';
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$logs_result = mysqli_stmt_get_result($stmt);

// Get filter options
$users_sql = "SELECT DISTINCT user_id, full_name FROM users ORDER BY full_name";
$users_result = mysqli_query($conn, $users_sql);

$tables_sql = "SELECT DISTINCT table_name FROM audit_logs WHERE table_name IS NOT NULL ORDER BY table_name";
$tables_result = mysqli_query($conn, $tables_sql);

$actions_sql = "SELECT DISTINCT action FROM audit_logs ORDER BY action";
$actions_result = mysqli_query($conn, $actions_sql);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header with Gradient Background -->
        <div class="dashboard-header mb-4">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="header-title">
                        <i class="bi bi-shield-lock me-3"></i>Audit Logs
                    </h1>
                    <p class="header-subtitle">Track and monitor all system activities</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-light btn-lg shadow-sm" onclick="printAuditLogs()" title="Print Report">
                        <i class="bi bi-printer-fill me-2"></i>Print
                    </button>
                    <button class="btn btn-light btn-lg shadow-sm" onclick="exportToCSV()" title="Export to CSV">
                        <i class="bi bi-file-earmark-excel-fill me-2"></i>Export CSV
                    </button>
                    <button class="btn btn-light btn-lg shadow-sm" onclick="exportToPDF()" title="Export to PDF">
                        <i class="bi bi-file-earmark-pdf-fill me-2"></i>Export PDF
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <p class="text-muted mb-1 small text-uppercase fw-semibold">Total Records</p>
                                <h3 class="mb-0 fw-bold"><?php echo number_format($total_records); ?></h3>
                                <small class="text-success">
                                    <i class="bi bi-arrow-up-circle-fill me-1"></i>All time
                                </small>
                            </div>
                            <div class="stat-icon-wrapper bg-primary bg-opacity-10">
                                <i class="bi bi-list-ul text-primary"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0">
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 100%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <p class="text-muted mb-1 small text-uppercase fw-semibold">Today's Activity</p>
                                <h3 class="mb-0 fw-bold">
                                    <?php
                                    $today_sql = "SELECT COUNT(*) as today FROM audit_logs WHERE DATE(created_at) = CURDATE()";
                                    $today_result = mysqli_query($conn, $today_sql);
                                    echo number_format(mysqli_fetch_assoc($today_result)['today']);
                                    ?>
                                </h3>
                                <small class="text-success">
                                    <i class="bi bi-calendar-check-fill me-1"></i><?php echo date('M d, Y'); ?>
                                </small>
                            </div>
                            <div class="stat-icon-wrapper bg-success bg-opacity-10">
                                <i class="bi bi-calendar-check text-success"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0">
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 75%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <p class="text-muted mb-1 small text-uppercase fw-semibold">Active Users</p>
                                <h3 class="mb-0 fw-bold">
                                    <?php
                                    $active_users_sql = "SELECT COUNT(DISTINCT user_id) as active FROM audit_logs WHERE DATE(created_at) = CURDATE()";
                                    $active_users_result = mysqli_query($conn, $active_users_sql);
                                    echo number_format(mysqli_fetch_assoc($active_users_result)['active']);
                                    ?>
                                </h3>
                                <small class="text-info">
                                    <i class="bi bi-people-fill me-1"></i>Today
                                </small>
                            </div>
                            <div class="stat-icon-wrapper bg-info bg-opacity-10">
                                <i class="bi bi-people text-info"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0">
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-info" role="progressbar" style="width: 60%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white border-bottom py-2 px-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 d-flex align-items-center" style="font-size: 0.95rem;">
                        <i class="bi bi-funnel-fill me-2 text-primary"></i>Filters
                    </h6>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" title="Toggle Filters">
                        <i class="bi bi-chevron-down"></i>
                    </button>
                </div>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body p-2">
                    <form method="GET" action="" class="row g-2">
                        <div class="col-sm-6 col-md-2">
                            <label class="form-label mb-1" style="font-size: 0.85rem; font-weight: 600;">User</label>
                            <select name="user" class="form-select form-select-sm shadow-sm border-1">
                                <option value="">All Users</option>
                                <?php mysqli_data_seek($users_result, 0); ?>
                                <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                                    <option value="<?php echo $user['user_id']; ?>" 
                                        <?php echo $filter_user == $user['user_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-sm-6 col-md-2">
                            <label class="form-label mb-1" style="font-size: 0.85rem; font-weight: 600;">Action</label>
                            <select name="action" class="form-select form-select-sm shadow-sm border-1">
                                <option value="">All Actions</option>
                                <?php while ($action = mysqli_fetch_assoc($actions_result)): ?>
                                    <option value="<?php echo htmlspecialchars($action['action']); ?>" 
                                        <?php echo $filter_action == $action['action'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($action['action']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-sm-6 col-md-2">
                            <label class="form-label mb-1" style="font-size: 0.85rem; font-weight: 600;">Table</label>
                            <select name="table" class="form-select form-select-sm shadow-sm border-1">
                                <option value="">All Tables</option>
                                <?php mysqli_data_seek($tables_result, 0); ?>
                                <?php while ($table = mysqli_fetch_assoc($tables_result)): ?>
                                    <option value="<?php echo $table['table_name']; ?>" 
                                        <?php echo $filter_table == $table['table_name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($table['table_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-sm-6 col-md-2">
                            <label class="form-label mb-1" style="font-size: 0.85rem; font-weight: 600;">Rows</label>
                            <select class="form-select form-select-sm shadow-sm border-1" onchange="changePageSize(this.value)">
                                <option value="25" <?php echo $records_per_page == 25 ? 'selected' : ''; ?>>25 entries</option>
                                <option value="50" <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50 entries</option>
                                <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100 entries</option>
                                <option value="200" <?php echo $records_per_page == 200 ? 'selected' : ''; ?>>200 entries</option>
                            </select>
                        </div>
                        
                        <div class="col-sm-6 col-md-2">
                            <label class="form-label mb-1" style="font-size: 0.85rem; font-weight: 600;">From</label>
                            <input type="date" name="date_from" class="form-control form-control-sm shadow-sm border-1" 
                                   value="<?php echo htmlspecialchars($filter_date_from); ?>">
                        </div>
                        
                        <div class="col-sm-6 col-md-2">
                            <label class="form-label mb-1" style="font-size: 0.85rem; font-weight: 600;">To</label>
                            <input type="date" name="date_to" class="form-control form-control-sm shadow-sm border-1" 
                                   value="<?php echo htmlspecialchars($filter_date_to); ?>">
                        </div>
                        
                        <div class="col-12">
                            <div class="d-flex gap-2 flex-wrap align-items-end">
                                <button type="submit" class="btn btn-primary btn-sm shadow-sm" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                                <a href="audit-logs.php" class="btn btn-outline-secondary btn-sm shadow-sm" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Audit Logs Table -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2 px-3">
                <div class="d-flex justify-content-between align-items-center" style="font-size: 0.9rem;">
                    <h6 class="mb-0 d-flex align-items-center">
                        <i class="bi bi-list-ul text-primary me-2"></i>Logs
                        <span class="badge bg-primary ms-2 rounded-pill" style="font-size: 0.75rem;"><?php echo number_format($total_records); ?></span>
                    </h6>
                    <small class="text-muted"><?php echo min($offset + 1, $total_records); ?>-<?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo number_format($total_records); ?></small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="auditLogsTable" style="font-size: 0.85rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 60px; padding: 0.6rem 0.5rem; font-size: 0.8rem;">ID</th>
                                <th style="width: 140px; padding: 0.6rem 0.5rem; font-size: 0.8rem;">User</th>
                                <th style="width: 120px; padding: 0.6rem 0.5rem; font-size: 0.8rem;">Action</th>
                                <th style="width: 100px; padding: 0.6rem 0.5rem; font-size: 0.8rem;">Table</th>
                                <th style="width: 150px; padding: 0.6rem 0.5rem; font-size: 0.8rem;">Date & Time</th>
                                <th class="text-center" style="width: 60px; padding: 0.6rem 0.5rem; font-size: 0.8rem;">View</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($logs_result) > 0): ?>
                                <?php while ($log = mysqli_fetch_assoc($logs_result)): 
                                    $badge_color = 'secondary';
                                    $icon = 'bi-circle-fill';
                                    $action_lower = strtolower($log['action']);
                                    
                                    if (strpos($action_lower, 'created') !== false || strpos($action_lower, 'added') !== false) {
                                        $badge_color = 'success';
                                        $icon = 'bi-plus-circle-fill';
                                    } elseif (strpos($action_lower, 'updated') !== false || strpos($action_lower, 'modified') !== false) {
                                        $badge_color = 'primary';
                                        $icon = 'bi-pencil-square';
                                    } elseif (strpos($action_lower, 'deleted') !== false || strpos($action_lower, 'removed') !== false) {
                                        $badge_color = 'danger';
                                        $icon = 'bi-trash-fill';
                                    } elseif (strpos($action_lower, 'login') !== false) {
                                        $badge_color = 'info';
                                        $icon = 'bi-box-arrow-in-right';
                                    } elseif (strpos($action_lower, 'logout') !== false) {
                                        $badge_color = 'dark';
                                        $icon = 'bi-box-arrow-right';
                                    }
                                ?>
                                    <tr class="audit-row">
                                        <td class="text-center" style="padding: 0.6rem 0.5rem;">
                                            <span class="badge bg-light text-dark border" style="font-size: 0.7rem;">#<?php echo $log['log_id']; ?></span>
                                        </td>
                                        <td style="padding: 0.6rem 0.5rem;">
                                            <div class="d-flex align-items-center" style="font-size: 0.8rem;">
                                                <div class="user-avatar-lg me-2" style="background: var(--primary-blue); color: white;">
                                                    <?php echo strtoupper(substr($log['full_name'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($log['full_name']); ?></div>
                                                    <small class="text-muted">
                                                        <i class="bi bi-at"></i><?php echo htmlspecialchars($log['username']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 0.6rem 0.5rem;">
                                            <span class="badge bg-<?php echo $badge_color; ?> d-inline-flex align-items-center gap-1" style="padding: 0.35rem 0.6rem; font-size: 0.75rem;">
                                                <i class="bi <?php echo $icon; ?>"></i>
                                                <?php echo htmlspecialchars($log['action']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($log['table_name']): ?>
                                                <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1">
                                                    <i class="bi bi-table"></i>
                                                    <?php echo htmlspecialchars($log['table_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <span class="text-dark">
                                                    <i class="bi bi-calendar3 text-primary me-1"></i>
                                                    <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                                                </span>
                                                <small class="text-muted">
                                                    <i class="bi bi-clock text-muted me-1"></i>
                                                    <?php echo date('h:i:s A', strtotime($log['created_at'])); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td class="text-center" style="padding: 0.6rem 0.5rem;">
                                            <button type="button" class="btn btn-xs btn-outline-primary" style="padding: 0.25rem 0.5rem; font-size: 0.7rem;" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#detailsModal<?php echo $log['log_id']; ?>"
                                                    title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            
                                            <!-- Details Modal -->
                                            <div class="modal fade" id="detailsModal<?php echo $log['log_id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
                                                        <div class="modal-content border-0 shadow-lg">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title d-flex align-items-center">
                                                                    <i class="bi bi-info-circle-fill me-2"></i>
                                                                    Audit Log Details
                                                                    <span class="badge bg-white text-primary ms-2">#<?php echo $log['log_id']; ?></span>
                                                                </h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body p-4">
                                                                <div class="row g-4 mb-4">
                                                                    <div class="col-md-6">
                                                                        <div class="info-box-modal">
                                                                            <label class="text-muted small mb-2 text-uppercase fw-semibold">
                                                                                <i class="bi bi-person-fill me-1"></i>User
                                                                            </label>
                                                                            <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($log['full_name']); ?></div>
                                                                            <small class="text-muted">@<?php echo htmlspecialchars($log['username']); ?></small>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="info-box-modal">
                                                                            <label class="text-muted small mb-2 text-uppercase fw-semibold">
                                                                                <i class="bi bi-lightning-fill me-1"></i>Action
                                                                            </label>
                                                                            <div>
                                                                                <span class="badge bg-<?php echo $badge_color; ?> px-3 py-2">
                                                                                    <i class="bi <?php echo $icon; ?> me-1"></i>
                                                                                    <?php echo htmlspecialchars($log['action']); ?>
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="info-box-modal">
                                                                            <label class="text-muted small mb-2 text-uppercase fw-semibold">
                                                                                <i class="bi bi-table me-1"></i>Table Name
                                                                            </label>
                                                                            <div>
                                                                                <code class="bg-light px-3 py-2 rounded"><?php echo htmlspecialchars($log['table_name'] ?? '—'); ?></code>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="info-box-modal">
                                                                            <label class="text-muted small mb-2 text-uppercase fw-semibold">
                                                                                <i class="bi bi-key-fill me-1"></i>Record ID
                                                                            </label>
                                                                            <div class="fw-bold text-dark"><?php echo $log['record_id'] ?? '—'; ?></div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="info-box-modal">
                                                                            <label class="text-muted small mb-2 text-uppercase fw-semibold">
                                                                                <i class="bi bi-geo-alt-fill me-1"></i>IP Address
                                                                            </label>
                                                                            <div><code class="bg-light px-3 py-2 rounded"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></code></div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="info-box-modal">
                                                                            <label class="text-muted small mb-2 text-uppercase fw-semibold">
                                                                                <i class="bi bi-calendar-check-fill me-1"></i>Date & Time
                                                                            </label>
                                                                            <div class="fw-bold text-dark"><?php echo date('M d, Y h:i:s A', strtotime($log['created_at'])); ?></div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                
                                                                <?php if ($log['old_value']): ?>
                                                                    <div class="data-section-modal mb-4">
                                                                        <div class="d-flex align-items-center mb-3">
                                                                            <div class="section-icon bg-danger bg-opacity-10 text-danger me-2">
                                                                                <i class="bi bi-dash-circle-fill"></i>
                                                                            </div>
                                                                            <h6 class="mb-0 fw-bold">Previous Value</h6>
                                                                        </div>
                                                                        <pre class="code-block-modal"><?php echo htmlspecialchars($log['old_value']); ?></pre>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($log['new_value']): ?>
                                                                    <div class="data-section-modal mb-4">
                                                                        <div class="d-flex align-items-center mb-3">
                                                                            <div class="section-icon bg-success bg-opacity-10 text-success me-2">
                                                                                <i class="bi bi-check-circle-fill"></i>
                                                                            </div>
                                                                            <h6 class="mb-0 fw-bold">New Value</h6>
                                                                        </div>
                                                                        <pre class="code-block-modal"><?php echo htmlspecialchars($log['new_value']); ?></pre>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($log['new_data']): ?>
                                                                    <div class="data-section-modal">
                                                                        <div class="d-flex align-items-center mb-3">
                                                                            <div class="section-icon bg-info bg-opacity-10 text-info me-2">
                                                                                <i class="bi bi-info-circle-fill"></i>
                                                                            </div>
                                                                            <h6 class="mb-0 fw-bold">Additional Data</h6>
                                                                        </div>
                                                                        <pre class="code-block-modal"><?php echo htmlspecialchars($log['new_data']); ?></pre>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="modal-footer border-0 bg-light">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                                    <i class="bi bi-x-circle me-1"></i>Close
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <div class="empty-state">
                                            <div class="empty-icon">
                                                <i class="bi bi-inbox"></i>
                                            </div>
                                            <h5 class="mt-3">No Audit Logs Found</h5>
                                            <p class="text-muted">No records match your current filters. Try adjusting your search criteria.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer bg-white border-top py-2 px-3">
                        <nav>
                            <ul class="pagination pagination-sm mb-0 justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=1&per_page=<?php echo $records_per_page; ?>&user=<?php echo $filter_user; ?>&action=<?php echo $filter_action; ?>&table=<?php echo $filter_table; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>">
                                            <i class="bi bi-chevron-bar-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $records_per_page; ?>&user=<?php echo $filter_user; ?>&action=<?php echo $filter_action; ?>&table=<?php echo $filter_table; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&per_page=<?php echo $records_per_page; ?>&user=<?php echo $filter_user; ?>&action=<?php echo $filter_action; ?>&table=<?php echo $filter_table; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $records_per_page; ?>&user=<?php echo $filter_user; ?>&action=<?php echo $filter_action; ?>&table=<?php echo $filter_table; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $total_pages; ?>&per_page=<?php echo $records_per_page; ?>&user=<?php echo $filter_user; ?>&action=<?php echo $filter_action; ?>&table=<?php echo $filter_table; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>">
                                            <i class="bi bi-chevron-bar-right"></i>
                                        </a>
                                    </li>
                                </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Audit Logs Page - ENHANCED WITH DASHBOARD STYLING */
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

/* Dashboard Header with Gradient - compact */
.dashboard-header {
    background: linear-gradient(135deg, #1a4d5c 0%, #0f3543 100%);
    border-radius: 10px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1rem;
    color: white;
    box-shadow: 0 4px 15px rgba(26, 77, 92, 0.2);
    position: relative;
    overflow: hidden;
    animation: slideInDown 0.45s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 1;
    flex-wrap: wrap;
    gap: 2rem;
}

.header-left {
    flex: 1;
    min-width: 0;
}

.header-title {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    letter-spacing: -1px;
    line-height: 1.2;
}

.header-subtitle {
    font-size: 1.125rem;
    opacity: 0.95;
    margin-bottom: 0;
    font-weight: 300;
    letter-spacing: 0.5px;
}

.header-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

/* Icons */
.icon-box {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    background: rgba(149, 165, 166, 0.1);
    color: var(--primary-gray);
}

/* Statistics Cards */
.stat-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid var(--border-color);
}

.stat-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12) !important;
}

.stat-icon-wrapper {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    transition: transform 0.3s;
}

.stat-card:hover .stat-icon-wrapper {
    transform: scale(1.1) rotate(5deg);
}

.stat-card .card-body {
    padding: 1rem;
    font-size: 0.9rem;
}

.stat-card h3 {
    font-size: 1.875rem;
    line-height: 1.2;
}

/* Filter Section */
.bg-gradient-primary {
    background: var(--primary-blue) !important;
    color: white !important;
}

/* User Avatar */
.user-avatar-lg {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Table Enhancements */
.table {
    font-size: 0.9375rem;
    margin-bottom: 0;
}

.table thead th {
    background: linear-gradient(135deg, #f5f7fa 0%, #ecf0f3 100%);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 1px;
    border-bottom: 2px solid #dde4eb;
    padding: 1.25rem 1rem;
    white-space: nowrap;
    color: var(--text-dark);
    position: sticky;
    top: 0;
}

.audit-row {
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    border-bottom: 1px solid var(--border-color);
}

.audit-row:hover {
    background: linear-gradient(90deg, rgba(52, 152, 219, 0.05), rgba(52, 152, 219, 0.02));
    box-shadow: inset 0 2px 8px rgba(52, 152, 219, 0.1);
    transform: translateX(4px);
}

.table tbody td {
    padding: 1.25rem 1rem;
    vertical-align: middle;
    color: var(--text-dark);
    line-height: 1.6;
}

/* Badges */
.badge {
    font-weight: 600;
    font-size: 0.8125rem;
    padding: 0.6rem 1rem;
    border-radius: 50px;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

/* Modal Enhancements */
.info-box-modal {
    padding: 1.25rem;
    background: var(--light-bg);
    border-radius: 12px;
    border-left: 4px solid var(--primary-blue);
    transition: transform 0.2s;
}

.info-box-modal:hover {
    transform: translateX(4px);
}

.data-section-modal {
    background: var(--card-bg);
    padding: 1.5rem;
    border-radius: 12px;
    border: 2px solid var(--border-color);
}

.section-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.code-block-modal {
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 1.25rem;
    max-height: 350px;
    overflow-y: auto;
    font-size: 0.875rem;
    margin: 0;
    line-height: 1.6;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
}

/* Empty State */
.empty-state {
    padding: 3rem 1rem;
}

.empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    background: rgba(149, 165, 166, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: var(--primary-gray);
}

/* Pagination */
.pagination-lg .page-link {
    padding: 0.4rem 0.6rem;
    font-size: 0.85rem;
    border-radius: 6px;
    margin: 0 2px;
    transition: all 0.2s;
}

.pagination-lg .page-item.active .page-link {
    background: var(--primary-blue);
    border-color: transparent;
    color: white;
    box-shadow: 0 2px 6px rgba(52, 152, 219, 0.2);
}

.pagination-lg .page-link:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Buttons */
.btn {
    border-radius: 10px;
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

.btn-lg {
    padding: 0.75rem 1.5rem;
    font-size: 0.95rem;
}

.btn-primary {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #2980b9 0%, #1f618d 100%);
}

.btn-light {
    background-color: white;
    color: #2c3e50;
    border: 1px solid var(--border-color);
}

.btn-light:hover {
    background-color: var(--light-bg);
    color: var(--text-dark);
}

/* Form Controls */
.form-select, .form-control {
    border-radius: 8px;
    border: 1px solid var(--border-color);
    transition: all 0.2s;
    font-size: 0.9rem;
}

.form-select.border-2, .form-control.border-2 {
    border: 2px solid var(--border-color);
}

.form-select:focus, .form-control:focus {
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
}

.form-select-lg, .form-control-lg {
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
}

/* Cards */
.card {
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid var(--border-color);
    transition: all 0.3s;
}

.card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1) !important;
}

.card-header {
    padding: 0.875rem 1rem;
    background-color: white;
    font-size: 0.9rem;
}

.card-body {
    padding: 0.875rem 1rem;
    background-color: white;
}

/* Scrollbar */
.table-responsive::-webkit-scrollbar,
.code-block-modal::-webkit-scrollbar {
    height: 8px;
    width: 8px;
}

.table-responsive::-webkit-scrollbar-track,
.code-block-modal::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.table-responsive::-webkit-scrollbar-thumb,
.code-block-modal::-webkit-scrollbar-thumb {
    background: #667eea;
    border-radius: 10px;
}

.table-responsive::-webkit-scrollbar-thumb:hover,
.code-block-modal::-webkit-scrollbar-thumb:hover {
    background: #764ba2;
}

/* Print Styles */
@media print {
    body * {
        visibility: hidden;
    }
    
    #printArea, #printArea * {
        visibility: visible;
    }
    
    #printArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    
    .no-print, .btn, .sidebar, .pagination, 
    .card-header button, [data-bs-toggle],
    .modal, .empty-icon {
        display: none !important;
    }
    
    .card {
        border: 2px solid #000 !important;
        box-shadow: none !important;
        page-break-inside: avoid;
        margin-bottom: 1rem;
    }
    
    .card-header {
        background: #f8f9fa !important;
        border-bottom: 2px solid #000 !important;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
    
    .table {
        font-size: 9pt;
        border: 1px solid #000;
    }
    
    .table thead {
        background: #e9ecef !important;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
    
    .table th, .table td {
        border: 1px solid #000 !important;
        padding: 8px !important;
    }
    
    .badge, .stat-icon-wrapper {
        border: 1px solid #000;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
    
    @page {
        margin: 1.5cm;
        size: landscape;
    }
    
    .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 3px solid #000;
    }
    
    .print-logo {
        max-width: 150px;
        margin-bottom: 10px;
    }
}

.print-header {
    display: none;
}

/* Responsive */
@media (max-width: 768px) {
    .dashboard-header {
        padding: 2rem 1.5rem;
        margin-bottom: 1.5rem;
    }

    .header-content {
        flex-direction: column;
        gap: 1.5rem;
    }

    .header-left {
        width: 100%;
    }

    .header-title {
        font-size: 2rem;
    }

    .header-subtitle {
        font-size: 1rem;
    }

    .header-actions {
        width: 100%;
        justify-content: flex-start;
    }

    .stat-card {
        margin-bottom: 1rem;
    }
    
    .table {
        font-size: 0.8125rem;
    }
    
    .table thead th {
        padding: 1rem 0.75rem;
        font-size: 0.75rem;
    }
    
    .table tbody td {
        padding: 1rem 0.75rem;
    }
    
    .btn-lg {
        padding: 0.625rem 1.25rem;
        font-size: 0.9375rem;
    }

    .btn {
        font-size: 0.875rem;
    }

    .form-select, .form-control {
        font-size: 0.9rem;
    }

    .card-header, .card-body {
        padding: 1.25rem;
    }

    .table-responsive {
        font-size: 0.875rem;
    }

    .audit-row:hover {
        transform: translateX(2px);
    }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

<script>
    // ===============================================
// AUDIT LOGS - COMPLETE ENHANCED JAVASCRIPT
// ===============================================

/**
 * Print Audit Logs with Beautiful Design
 */
function printAuditLogs() {
    const printWindow = window.open('', '_blank', 'height=900,width=1400');
    
    if (!printWindow) {
        alert('Please allow popups for this website to print the report.');
        return;
    }
    
    // Get active filters
    const filters = [];
    const filterUser = document.querySelector('select[name="user"]');
    const filterAction = document.querySelector('select[name="action"]');
    const filterTable = document.querySelector('select[name="table"]');
    const filterDateFrom = document.querySelector('input[name="date_from"]');
    const filterDateTo = document.querySelector('input[name="date_to"]');
    
    if (filterUser?.value) {
        filters.push(`<span class="filter-badge">👤 ${filterUser.options[filterUser.selectedIndex].text}</span>`);
    }
    if (filterAction?.value) {
        filters.push(`<span class="filter-badge">⚡ ${filterAction.value}</span>`);
    }
    if (filterTable?.value) {
        filters.push(`<span class="filter-badge">📊 ${filterTable.value}</span>`);
    }
    if (filterDateFrom?.value) {
        filters.push(`<span class="filter-badge">📅 From: ${filterDateFrom.value}</span>`);
    }
    if (filterDateTo?.value) {
        filters.push(`<span class="filter-badge">📅 To: ${filterDateTo.value}</span>`);
    }
    
    const filterHtml = filters.length > 0 
        ? `<div class="filter-info">
              <strong style="color: #667eea; font-size: 16px;">🔍 Active Filters</strong>
              <div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                  ${filters.join('')}
              </div>
           </div>` 
        : '';
    
    // Get table rows
    const table = document.getElementById('auditLogsTable');
    const rows = table.querySelectorAll('tbody tr');
    let tableHtml = '';
    
    rows.forEach((row, index) => {
        if (row.cells.length > 1) {
            const cells = row.cells;
            const rowClass = index % 2 === 0 ? 'even-row' : 'odd-row';
            
            // Clean text content
            const id = cells[0].textContent.trim();
            const user = cells[1].textContent.trim().replace(/\s+/g, ' ');
            const action = cells[2].textContent.trim();
            const tableName = cells[3].textContent.trim();
            const dateTime = cells[4].textContent.trim().replace(/\s+/g, ' ');
            
            tableHtml += `
                <tr class="${rowClass}">
                    <td class="text-center">${id}</td>
                    <td>${user}</td>
                    <td>${action}</td>
                    <td>${tableName}</td>
                    <td>${dateTime}</td>
                </tr>
            `;
        }
    });
    
    // Get statistics
    const statCards = document.querySelectorAll('.stat-card h3');
    const stats = {
        total: statCards[0]?.textContent.trim() || '0',
        today: statCards[1]?.textContent.trim() || '0',
        users: statCards[2]?.textContent.trim() || '0',
        tables: statCards[3]?.textContent.trim() || '0'
    };
    
    // Current date/time
    const now = new Date();
    const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
    const formattedDate = now.toLocaleDateString('en-US', dateOptions);
    const formattedTime = now.toLocaleTimeString('en-US', timeOptions);
    
    // Write document
    printWindow.document.write(`
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Audit Logs Report - ${now.toLocaleDateString()}</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
            <style>
                @page {
                    margin: 1.5cm;
                    size: landscape;
                }
                
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    padding: 30px;
                    background: #3498db;
                    min-height: 100vh;
                }
                
                .print-container {
                    background: white;
                    padding: 50px;
                    border-radius: 20px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    max-width: 100%;
                }
                
                .print-header {
                    text-align: center;
                    margin-bottom: 40px;
                    padding-bottom: 30px;
                    border-bottom: 5px solid #3498db;
                    position: relative;
                }
                
                .print-header::after {
                    content: '';
                    position: absolute;
                    bottom: -8px;
                    left: 50%;
                    transform: translateX(-50%);
                    width: 200px;
                    height: 3px;
                    background: #3498db;
                }
                
                .print-logo {
                    width: 90px;
                    height: 90px;
                    margin: 0 auto 20px;
                    background: #3498db;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 45px;
                    color: white;
                    box-shadow: 0 10px 30px rgba(52, 152, 219, 0.3);
                    animation: pulse 2s infinite;
                }
                
                @keyframes pulse {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                }
                
                .print-header h1 {
                    color: #3498db;
                    font-weight: 900;
                    font-size: 48px;
                    margin-bottom: 15px;
                    letter-spacing: 3px;
                    text-transform: uppercase;
                }
                
                .report-meta {
                    display: flex;
                    justify-content: center;
                    gap: 30px;
                    flex-wrap: wrap;
                    font-size: 14px;
                    color: #666;
                    margin-top: 20px;
                }
                
                .report-meta span {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 10px 20px;
                    background: #f8f9fa;
                    border-radius: 25px;
                    font-weight: 600;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                }
                
                .report-meta i {
                    color: #3498db;
                    font-size: 18px;
                }
                
                .filter-info {
                    background: #fff5e6;
                    border-left: 6px solid #3498db;
                    padding: 25px;
                    margin-bottom: 35px;
                    border-radius: 12px;
                    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
                }
                
                .filter-badge {
                    display: inline-block;
                    padding: 8px 16px;
                    background: white;
                    border: 2px solid #667eea;
                    border-radius: 20px;
                    font-size: 13px;
                    font-weight: 600;
                    color: #667eea;
                    box-shadow: 0 2px 6px rgba(102, 126, 234, 0.2);
                }
                
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 25px;
                    margin-bottom: 40px;
                }
                
                .stat-box {
                    background: #ffffff;
                    padding: 30px;
                    border-radius: 16px;
                    text-align: center;
                    border: 2px solid #e9ecef;
                    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
                }
                
                .stat-icon {
                    width: 70px;
                    height: 70px;
                    margin: 0 auto 18px;
                    background: #3498db;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 32px;
                    color: white;
                    box-shadow: 0 6px 18px rgba(52, 152, 219, 0.3);
                }
                
                .stat-label {
                    font-size: 13px;
                    color: #666;
                    text-transform: uppercase;
                    font-weight: 700;
                    letter-spacing: 1.2px;
                    margin-bottom: 10px;
                }
                
                .stat-value {
                    font-size: 36px;
                    font-weight: 900;
                    color: #3498db;
                    line-height: 1;
                }
                
                table {
                    width: 100%;
                    border-collapse: separate;
                    border-spacing: 0;
                    margin-top: 30px;
                    border-radius: 15px;
                    overflow: hidden;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
                }
                
                thead {
                    background: #3498db;
                    color: white;
                }
                
                th {
                    padding: 20px 14px;
                    text-align: left;
                    font-weight: 700;
                    font-size: 12px;
                    text-transform: uppercase;
                    letter-spacing: 1.5px;
                    border: none;
                }
                
                td {
                    padding: 18px 14px;
                    border-bottom: 1px solid #e9ecef;
                    font-size: 13px;
                    color: #333;
                    line-height: 1.6;
                }
                
                tr.even-row {
                    background-color: #f8f9fa;
                }
                
                tr.odd-row {
                    background-color: white;
                }
                
                tbody tr:last-child td {
                    border-bottom: none;
                }
                
                .text-center {
                    text-align: center !important;
                }
                
                .print-footer {
                    margin-top: 50px;
                    padding-top: 30px;
                    border-top: 3px double #dee2e6;
                    text-align: center;
                    color: #666;
                    font-size: 13px;
                }
                
                .footer-content {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: 20px;
                    margin-bottom: 20px;
                }
                
                .footer-logo {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    font-weight: 700;
                    font-size: 16px;
                    color: #667eea;
                }
                
                .footer-logo i {
                    font-size: 28px;
                }
                
                .footer-info {
                    text-align: center;
                    padding-top: 20px;
                    border-top: 1px solid #dee2e6;
                }
                
                code {
                    background: #f1f3f5;
                    padding: 4px 10px;
                    border-radius: 6px;
                    font-family: 'Courier New', monospace;
                    font-size: 12px;
                    color: #495057;
                    border: 1px solid #dee2e6;
                }
                
                @media print {
                    body {
                        background: white;
                        padding: 0;
                    }
                    
                    .print-container {
                        box-shadow: none;
                        border-radius: 0;
                        padding: 20px;
                    }
                    
                    .print-logo {
                        animation: none;
                    }
                }
            </style>
        </head>
        <body>
            <div class="print-container">
                <!-- Header -->
                <div class="print-header">
                    <div class="print-logo">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <h1>Audit Logs Report</h1>
                    <div class="report-meta">
                        <span>
                            <i class="bi bi-calendar-check"></i>
                            ${formattedDate}
                        </span>
                        <span>
                            <i class="bi bi-clock"></i>
                            ${formattedTime}
                        </span>
                    </div>
                </div>
                
                <!-- Filters (if any) -->
                ${filterHtml}
                
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-icon">
                            <i class="bi bi-list-ul"></i>
                        </div>
                        <div class="stat-label">Total Records</div>
                        <div class="stat-value">${stats.total}</div>
                    </div>
                    
                    <div class="stat-box">
                        <div class="stat-icon">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <div class="stat-label">Today's Activity</div>
                        <div class="stat-value">${stats.today}</div>
                    </div>
                    
                    <div class="stat-box">
                        <div class="stat-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-label">Active Users</div>
                        <div class="stat-value">${stats.users}</div>
                    </div>
                    
                    <div class="stat-box">
                        <div class="stat-icon">
                            <i class="bi bi-database"></i>
                        </div>
                        <div class="stat-label">Tables Monitored</div>
                        <div class="stat-value">${stats.tables}</div>
                    </div>
                </div>
                
                <!-- Table -->
                <table>
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 10%;">ID</th>
                            <th style="width: 20%;">USER</th>
                            <th style="width: 18%;">ACTION</th>
                            <th style="width: 16%;">TABLE</th>
                            <th style="width: 36%;">DATE & TIME</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${tableHtml || '<tr><td colspan="5" class="text-center">No records found</td></tr>'}
                    </tbody>
                </table>
                
                <!-- Footer -->
                <div class="print-footer">
                    <div class="footer-content">
                        <div class="footer-logo">
                            <i class="bi bi-shield-check"></i>
                            <span>Audit Logs System</span>
                        </div>
                        <div>
                            <strong>Reference:</strong> 
                            <code>AUD-${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}</code>
                        </div>
                    </div>
                    <div class="footer-info">
                        <p style="margin: 0; color: #999;">
                            <i class="bi bi-info-circle"></i> 
                            This is an automatically generated report. For questions or concerns, please contact your system administrator.
                        </p>
                    </div>
                </div>
            </div>
            
            <script>
                window.onload = function() {
                    // Auto-print after content loads
                    setTimeout(function() {
                        window.print();
                    }, 800);
                };
                
                // Close after printing
                window.onafterprint = function() {
                    setTimeout(function() {
                        window.close();
                    }, 500);
                };
            <\/script>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}

/**
 * Export to CSV
 */
function exportToCSV() {
    try {
        const table = document.getElementById('auditLogsTable');
        const rows = table.querySelectorAll('tr');
        const csv = [];
        
        // Add header
        csv.push('"AUDIT LOGS REPORT"');
        csv.push('"Generated: ' + new Date().toLocaleString() + '"');
        csv.push('""'); // Empty line
        
        // Process rows
        rows.forEach((row, index) => {
            const cols = row.querySelectorAll('td, th');
            const csvRow = [];
            
            // Skip last column (Actions button)
            for (let i = 0; i < cols.length - 1; i++) {
                let text = cols[i].innerText.trim().replace(/"/g, '""').replace(/\s+/g, ' ');
                csvRow.push('"' + text + '"');
            }
            
            if (csvRow.length > 0) {
                csv.push(csvRow.join(','));
            }
        });
        
        // Create download
        const csvContent = csv.join('\n');
        const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        const filename = `audit_logs_${new Date().toISOString().slice(0, 10)}.csv`;
        
        link.href = url;
        link.download = filename;
        link.style.display = 'none';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Show success message
        showNotification('CSV exported successfully!', 'success');
    } catch (error) {
        console.error('Export error:', error);
        showNotification('Failed to export CSV. Please try again.', 'error');
    }
}

/**
 * Export to PDF
 */
function exportToPDF() {
    try {
        const { jsPDF } = window.jspdf;
        
        if (!jsPDF) {
            alert('PDF library not loaded. Please refresh and try again.');
            return;
        }
        
        const doc = new jsPDF('l', 'mm', 'a4'); // Landscape A4
        
        // Header background
        doc.setFillColor(102, 126, 234);
        doc.rect(0, 0, 297, 45, 'F');
        
        // Title
        doc.setFontSize(26);
        doc.setTextColor(255, 255, 255);
        doc.setFont(undefined, 'bold');
        doc.text('AUDIT LOGS REPORT', 148.5, 22, { align: 'center' });
        
        // Date/Time
        doc.setFontSize(11);
        doc.setFont(undefined, 'normal');
        doc.text('Generated: ' + new Date().toLocaleString(), 148.5, 32, { align: 'center' });
        
        // Get table data
        const table = document.getElementById('auditLogsTable');
        const rows = table.querySelectorAll('tbody tr');
        const tableData = [];
        
        rows.forEach(row => {
            if (row.cells.length > 1) { // Skip empty rows
                const rowData = [];
                // Skip last column (Actions)
                for (let i = 0; i < row.cells.length - 1; i++) {
                    rowData.push(row.cells[i].innerText.trim().replace(/\s+/g, ' '));
                }
                tableData.push(rowData);
            }
        });
        
        // Add table
        doc.autoTable({
            startY: 52,
            head: [['ID', 'User', 'Action', 'Table', 'Date & Time']],
            body: tableData,
            theme: 'striped',
            headStyles: {
                fillColor: [102, 126, 234],
                textColor: 255,
                fontStyle: 'bold',
                halign: 'center',
                fontSize: 10,
                cellPadding: 5
            },
            styles: {
                fontSize: 8,
                cellPadding: 5,
                overflow: 'linebreak',
                lineColor: [222, 226, 230],
                lineWidth: 0.1
            },
            columnStyles: {
                0: { cellWidth: 20, halign: 'center', fontStyle: 'bold' },
                1: { cellWidth: 50 },
                2: { cellWidth: 40 },
                3: { cellWidth: 35 },
                4: { cellWidth: 50 }
            },
            alternateRowStyles: {
                fillColor: [245, 247, 250]
            },
            margin: { top: 52, left: 14, right: 14 }
        });
        
        // Add footer to all pages
        const pageCount = doc.internal.getNumberOfPages();
        for (let i = 1; i <= pageCount; i++) {
            doc.setPage(i);
            
            // Footer background
            doc.setFillColor(248, 249, 250);
            doc.rect(0, 193, 297, 17, 'F');
            
            // Footer text
            doc.setFontSize(9);
            doc.setTextColor(100, 100, 100);
            doc.setFont(undefined, 'normal');
            
            const footerText = `Page ${i} of ${pageCount} | Audit Logs System | ${new Date().toLocaleDateString()}`;
            doc.text(footerText, 148.5, 202, { align: 'center' });
        }
        
        // Save
        const filename = `audit_logs_${new Date().toISOString().slice(0, 10)}.pdf`;
        doc.save(filename);
        
        // Show success message
        showNotification('PDF exported successfully!', 'success');
    } catch (error) {
        console.error('PDF export error:', error);
        showNotification('Failed to export PDF. Please try again.', 'error');
    }
}

/**
 * Change page size
 */
function changePageSize(perPage) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', perPage);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

/**
 * Show notification (optional - for better UX)
 */
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'error' ? 'danger' : 'success'} position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bi bi-${type === 'error' ? 'exclamation-triangle' : 'check-circle'}-fill me-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.transition = 'opacity 0.3s';
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Initialize tooltips on page load
document.addEventListener('DOMContentLoaded', function() {
    // Enable Bootstrap tooltips if available
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});
</script>