<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_admin();
prevent_cache();

$page_title = "Backup";

// Create backups table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS backups (
    backup_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    filesize BIGINT NOT NULL,
    created_by INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
)";
mysqli_query($conn, $create_table_sql);

// Handle backup creation
if (isset($_POST['create_backup'])) {
    $backup_dir = '../backups/';
    
    // Create backups directory if it doesn't exist
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backup_dir . $filename;
    
    // Get database credentials
    $host = DB_HOST;
    $user = DB_USER;
    $pass = DB_PASS;
    $name = DB_NAME;
    
    // For Windows XAMPP, find mysqldump in the correct path
    $mysqldump_path = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
    
    // Check if mysqldump exists
    if (!file_exists($mysqldump_path)) {
        $mysqldump_path = 'mysqldump'; // Try system PATH
    }
    
    // Execute mysqldump command
    if (!empty($pass)) {
        $command = "\"$mysqldump_path\" --user=$user --password=$pass --host=$host $name > \"$filepath\" 2>&1";
    } else {
        $command = "\"$mysqldump_path\" --user=$user --host=$host $name > \"$filepath\" 2>&1";
    }
    
    exec($command, $output, $return_var);
    
    if ($return_var === 0 && file_exists($filepath) && filesize($filepath) > 0) {
        // Record backup in database
        $filesize = filesize($filepath);
        $insert_sql = "INSERT INTO backups (filename, filesize, created_by) 
                      VALUES ('$filename', $filesize, {$_SESSION['user_id']})";
        mysqli_query($conn, $insert_sql);
        
        $_SESSION['success'] = "Backup created successfully: $filename";
    } else {
        $_SESSION['error'] = "Error creating backup. Please check mysqldump is installed.";
        // Clean up failed backup file
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
    
    header('Location: backup.php');
    exit();
}

// Handle backup download
if (isset($_GET['download'])) {
    $backup_id = (int)$_GET['download'];
    $sql = "SELECT filename FROM backups WHERE backup_id = $backup_id";
    $result = mysqli_query($conn, $sql);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $filepath = '../backups/' . $row['filename'];
        
        if (file_exists($filepath)) {
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . $row['filename'] . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit();
        }
    }
    
    $_SESSION['error'] = "Backup file not found";
    header('Location: backup.php');
    exit();
}

// Handle backup deletion
if (isset($_GET['delete'])) {
    $backup_id = (int)$_GET['delete'];
    $sql = "SELECT filename FROM backups WHERE backup_id = $backup_id";
    $result = mysqli_query($conn, $sql);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $filepath = '../backups/' . $row['filename'];
        
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        
        $delete_sql = "DELETE FROM backups WHERE backup_id = $backup_id";
        mysqli_query($conn, $delete_sql);
        
        $_SESSION['success'] = "Backup deleted successfully";
    }
    
    header('Location: backup.php');
    exit();
}

// Get all backups
$backups_sql = "SELECT b.*, u.full_name
                FROM backups b
                LEFT JOIN users u ON b.created_by = u.user_id
                ORDER BY b.created_at DESC";
$backups_result = mysqli_query($conn, $backups_sql);

// Get database size
$size_sql = "SELECT 
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
    FROM information_schema.tables
    WHERE table_schema = '" . DB_NAME . "'";
$size_result = mysqli_query($conn, $size_sql);
$size_data = mysqli_fetch_assoc($size_result);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col">
                <h2><i class="bi bi-database me-2"></i>Database Backup</h2>
                <p class="text-muted">Create and manage database backups</p>
            </div>
            <div class="col-auto">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="create_backup" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Create New Backup
                    </button>
                </form>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Database Info -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-primary">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Database Name</h6>
                        <h4 class="mb-0"><?php echo DB_NAME; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-info">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Database Size</h6>
                        <h4 class="mb-0"><?php echo $size_data['size_mb'] ?? '0'; ?> MB</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-success">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Total Backups</h6>
                        <h4 class="mb-0"><?php echo mysqli_num_rows($backups_result); ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Backup Instructions -->
        <div class="alert alert-info">
            <h5><i class="bi bi-info-circle me-2"></i>Backup Instructions</h5>
            <ul class="mb-0">
                <li>Regular backups are recommended daily or before major system changes</li>
                <li>Store backups in a secure location outside the server</li>
                <li>Test backup restoration periodically to ensure data integrity</li>
                <li>Keep at least 7 days of backup history</li>
            </ul>
        </div>

        <!-- Backups Table -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-archive me-2"></i>Backup History
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Size</th>
                                <th>Created By</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($backups_result) > 0): ?>
                                <?php 
                                mysqli_data_seek($backups_result, 0);
                                while ($backup = mysqli_fetch_assoc($backups_result)): 
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($backup['filename']); ?></strong></td>
                                        <td><?php echo number_format($backup['filesize'] / 1024 / 1024, 2); ?> MB</td>
                                        <td><?php echo htmlspecialchars($backup['full_name'] ?? 'Unknown'); ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($backup['created_at'])); ?></td>
                                        <td>
                                            <a href="?download=<?php echo $backup['backup_id']; ?>" 
                                               class="btn btn-sm btn-success">
                                                <i class="bi bi-download"></i> Download
                                            </a>
                                            <a href="?delete=<?php echo $backup['backup_id']; ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Delete this backup?')">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        No backups found. Click "Create New Backup" to get started.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Restore Instructions -->
        <div class="card mt-4">
            <div class="card-header bg-warning bg-opacity-10">
                <i class="bi bi-exclamation-triangle me-2"></i>How to Restore from Backup
            </div>
            <div class="card-body">
                <h5>Via Command Line:</h5>
                <pre class="bg-dark text-light p-3 rounded"><code>mysql -u <?php echo DB_USER; ?> -p <?php echo DB_NAME; ?> < backup_filename.sql</code></pre>
                
                <h5 class="mt-3">Via phpMyAdmin:</h5>
                <ol>
                    <li>Open phpMyAdmin</li>
                    <li>Select the database: <strong><?php echo DB_NAME; ?></strong></li>
                    <li>Click on "Import" tab</li>
                    <li>Choose the backup file and click "Go"</li>
                </ol>

                <h5 class="mt-3">Via XAMPP MySQL Command Line:</h5>
                <pre class="bg-dark text-light p-3 rounded"><code>C:\xampp\mysql\bin\mysql.exe -u <?php echo DB_USER; ?> -p <?php echo DB_NAME; ?> < C:\xampp\htdocs\town-gas\backups\backup_filename.sql</code></pre>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>