<?php
session_start();
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Include database connection
require_once '../DB/DB_connection.php';

try {
    // Get current month's revenue
    $current_month_sql = "SELECT COALESCE(SUM(parking_fee), 0) as total 
                          FROM parking_logs 
                          WHERE status = 'exited' 
                          AND EXTRACT(MONTH FROM exit_time) = EXTRACT(MONTH FROM CURRENT_DATE)
                          AND EXTRACT(YEAR FROM exit_time) = EXTRACT(YEAR FROM CURRENT_DATE)";
    
    $current_month_result = db_query($current_month_sql);
    $current_month_data = db_fetch_assoc($current_month_result);
    $current_month_revenue = $current_month_data['total'];

    // Get previous month's revenue for comparison
    $previous_month_sql = "SELECT COALESCE(SUM(parking_fee), 0) as total 
                           FROM parking_logs 
                           WHERE status = 'exited' 
                           AND EXTRACT(MONTH FROM exit_time) = EXTRACT(MONTH FROM CURRENT_DATE - INTERVAL '1 month')
                           AND EXTRACT(YEAR FROM exit_time) = EXTRACT(YEAR FROM CURRENT_DATE - INTERVAL '1 month')";
    
    $previous_month_result = db_query($previous_month_sql);
    $previous_month_data = db_fetch_assoc($previous_month_result);
    $previous_month_revenue = $previous_month_data['total'];

    // Calculate growth rate
    $growth_rate = 0;
    if ($previous_month_revenue > 0) {
        $growth_rate = (($current_month_revenue - $previous_month_revenue) / $previous_month_revenue) * 100;
    }

    // Get total transactions this month
    $transactions_sql = "SELECT COUNT(*) as count 
                        FROM parking_logs 
                        WHERE status = 'exited' 
                        AND EXTRACT(MONTH FROM exit_time) = EXTRACT(MONTH FROM CURRENT_DATE)
                        AND EXTRACT(YEAR FROM exit_time) = EXTRACT(YEAR FROM CURRENT_DATE)";
    
    $transactions_result = db_query($transactions_sql);
    $transactions_data = db_fetch_assoc($transactions_result);
    $total_transactions = $transactions_data['count'];

    // Get average daily revenue this month
    $days_in_month = date('t'); // Number of days in current month
    $average_daily_revenue = $current_month_revenue / $days_in_month;

    // Get peak hour revenue (highest earning hour)
    $peak_revenue_sql = "SELECT EXTRACT(HOUR FROM exit_time) as hour, 
                               COALESCE(SUM(parking_fee), 0) as revenue 
                        FROM parking_logs 
                        WHERE status = 'exited' 
                        AND EXTRACT(MONTH FROM exit_time) = EXTRACT(MONTH FROM CURRENT_DATE)
                        AND EXTRACT(YEAR FROM exit_time) = EXTRACT(YEAR FROM CURRENT_DATE)
                        GROUP BY EXTRACT(HOUR FROM exit_time) 
                        ORDER BY revenue DESC 
                        LIMIT 1";
    
    $peak_revenue_result = db_query($peak_revenue_sql);
    $peak_revenue_data = db_fetch_assoc($peak_revenue_result);
    $peak_revenue = $peak_revenue_data ? $peak_revenue_data['revenue'] : 0;

    // Get daily revenue data for the current month
    $daily_revenue_sql = "SELECT 
                            EXTRACT(DAY FROM exit_time) as day,
                            COALESCE(SUM(parking_fee), 0) as revenue
                          FROM parking_logs
                          WHERE status = 'exited'
                          AND EXTRACT(MONTH FROM exit_time) = EXTRACT(MONTH FROM CURRENT_DATE)
                          AND EXTRACT(YEAR FROM exit_time) = EXTRACT(YEAR FROM CURRENT_DATE)
                          GROUP BY EXTRACT(DAY FROM exit_time)
                          ORDER BY day";
    
    $daily_revenue_result = db_query($daily_revenue_sql);
    $daily_revenue_data = db_fetch_all($daily_revenue_result);

    // Create arrays for chart data
    $daily_labels = [];
    $daily_revenue = [];
    
    // Initialize all days with 0
    for ($i = 1; $i <= $days_in_month; $i++) {
        $daily_labels[] = 'Day ' . $i;
        $daily_revenue[] = 0;
    }
    
    // Fill in actual revenue data
    if ($daily_revenue_data) {
        foreach ($daily_revenue_data as $row) {
            $day_index = intval($row['day']) - 1;
            $daily_revenue[$day_index] = floatval($row['revenue']);
        }
    }

    // Return JSON response
    echo json_encode([
        'success' => true,
        'current_month_revenue' => floatval($current_month_revenue),
        'previous_month_revenue' => floatval($previous_month_revenue),
        'growth_rate' => floatval($growth_rate),
        'total_transactions' => intval($total_transactions),
        'average_daily_revenue' => floatval($average_daily_revenue),
        'peak_revenue' => floatval($peak_revenue),
        'daily_labels' => $daily_labels,
        'daily_revenue' => $daily_revenue
    ]);

} catch (Exception $e) {
    error_log("Analytics data error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch analytics data',
        'error' => $e->getMessage()
    ]);
}
?>