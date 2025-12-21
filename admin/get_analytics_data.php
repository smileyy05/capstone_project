<?php
session_start(); // ADD THIS
header('Content-Type: application/json');

// ADD AUTHENTICATION CHECK
if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

require_once __DIR__ . '/../DB/DB_connection.php';

try {
    $current_month = date('Y-m');
    
    // ... rest of your existing queries ...
    
    // FIX: Average daily revenue calculation
    $days_in_month = date('t'); // Total days in month, not current day
    $avg_daily_revenue = $days_in_month > 0 ? $current_revenue / $days_in_month : 0;
    
    // ... rest of your code ...
    
} catch (Exception $e) {
    error_log("Analytics Data Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch analytics data'
    ]);
}
?>
