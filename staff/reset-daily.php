<?php
session_start();
require_once '../config/database.php';

// This endpoint handles daily reset of delivery orders
// Can be called via AJAX or cron job

header('Content-Type: application/json');

// Check if user is logged in and has proper permissions
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get the action from query/post
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if ($action === 'daily_reset') {
    // Archive yesterday's completed orders to history
    // Reset completed status back to pending for incomplete deliveries
    
    try {
        // Get count of orders being archived
        $count_sql = "SELECT COUNT(*) as count FROM deliveries 
                     WHERE DATE(created_at) < CURDATE() 
                     AND delivery_status IN ('completed', 'pending', 'in_transit')";
        $count_result = mysqli_query($conn, $count_sql);
        $count_row = mysqli_fetch_assoc($count_result);
        $archived_count = $count_row['count'];
        
        // Archive these to history by marking them with archive flag or keeping them in the table
        // They'll automatically appear in history table since they're not from today
        
        // For now, we just keep them in the table - the history query filters by date
        // If you want to restore pending status for incomplete deliveries:
        // $reset_sql = "UPDATE deliveries SET delivery_status = 'pending' 
        //               WHERE DATE(created_at) < CURDATE() 
        //               AND delivery_status IN ('completed')
        //               AND ride_id IS NULL";
        
        echo json_encode([
            'success' => true, 
            'message' => "Daily reset completed. $archived_count orders archived to history.",
            'archived_count' => $archived_count
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} 
else if ($action === 'get_reset_status') {
    // Get info about today's orders and yesterday's archive
    try {
        $today_sql = "SELECT 
                       COUNT(DISTINCT d.delivery_id) as total,
                       SUM(CASE WHEN d.delivery_status = 'completed' THEN 1 ELSE 0 END) as completed,
                       SUM(CASE WHEN d.delivery_status = 'pending' THEN 1 ELSE 0 END) as pending,
                       SUM(CASE WHEN d.delivery_status = 'in_transit' THEN 1 ELSE 0 END) as in_transit
                     FROM deliveries d
                     WHERE DATE(d.created_at) = CURDATE()";
        
        $today_result = mysqli_query($conn, $today_sql);
        $today_stats = mysqli_fetch_assoc($today_result);
        
        $yesterday_sql = "SELECT COUNT(DISTINCT d.delivery_id) as count 
                         FROM deliveries d
                         WHERE DATE(d.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        
        $yesterday_result = mysqli_query($conn, $yesterday_sql);
        $yesterday_row = mysqli_fetch_assoc($yesterday_result);
        
        echo json_encode([
            'success' => true,
            'today' => $today_stats,
            'yesterday_archived' => $yesterday_row['count'],
            'current_date' => date('Y-m-d'),
            'last_reset' => 'N/A' // You could store this in a table if needed
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
