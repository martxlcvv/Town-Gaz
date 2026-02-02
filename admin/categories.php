<?php
session_start();
require_once '../config/database.php';
require_once '../auth/check-auth.php';
require_admin();
prevent_cache();

$page_title = "Categories";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_category'])) {
    if (!verify_csrf_token($_POST['session_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid session token';
        header('Location: categories.php');
        exit;
    }

    $name = trim($_POST['name']);
    $description = trim($_POST['description']);

    if ($name == '') {
        $_SESSION['error'] = 'Category name is required';
        header('Location: categories.php');
        exit;
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO categories (name, description) VALUES (?, ?) ON DUPLICATE KEY UPDATE description = VALUES(description)");
    mysqli_stmt_bind_param($stmt, "ss", $name, $description);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = 'Category saved';
    } else {
        $_SESSION['error'] = 'Error saving category: ' . mysqli_error($conn);
    }
    header('Location: categories.php');
    exit;
}

$cats_sql = "SELECT * FROM categories ORDER BY name";
$cats_result = mysqli_query($conn, $cats_sql);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="page-content p-3">
    <h3>Categories</h3>
    <?php if (isset($_SESSION['success'])) { echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>'; unset($_SESSION['success']); }
          if (isset($_SESSION['error'])) { echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>'; unset($_SESSION['error']); } ?>

    <div class="card mb-3">
        <div class="card-body">
            <form method="POST">
                <?php echo output_token_field(); ?>
                <input type="hidden" name="save_category" value="1">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" name="name" class="form-control" placeholder="Category name" required>
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="description" class="form-control" placeholder="Description (optional)">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-striped">
                <thead><tr><th>Name</th><th>Description</th></tr></thead>
                <tbody>
                <?php while ($c = mysqli_fetch_assoc($cats_result)) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['name']); ?></td>
                        <td><?php echo htmlspecialchars($c['description']); ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
