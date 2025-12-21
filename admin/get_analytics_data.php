<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Include PostgreSQL database connection
require_once __DIR__ . '/../DB/DB_connection.php';

try {
    // Get current month's revenue
    $current_month = date('Y-m');
    $revenue_sql = "SELECT 
                        COALESCE(SUM(parking_fee), 0) as total_revenue,
                        COUNT(*) as total_transactions,
                        COALESCE(AVG(parking_fee), 0) as avg_fee,
                        COALESCE(MAX(parking_fee), 0) as max_fee
                    FROM parking_logs 
                    WHERE exit_time IS NOT NULL 
                    AND TO_CHAR(exit_time, 'YYYY-MM') = $1";

    $result = db_prepare($revenue_sql, [$current_month]);
    $revenue_data = db_fetch_assoc($result);

    // Get previous month's revenue for comparison
    $previous_month = date('Y-m', strtotime('-1 month'));
    $prev_sql = "SELECT COALESCE(SUM(parking_fee), 0) as prev_revenue
                 FROM parking_logs 
                 WHERE exit_time IS NOT NULL 
                 AND TO_CHAR(exit_time, 'YYYY-MM') = $1";

    $prev_result = db_prepare($prev_sql, [$previous_month]);
    $prev_data = db_fetch_assoc($prev_result);

    // Calculate growth rate
    $current_revenue = floatval($revenue_data['total_revenue'] ?? 0);
    $prev_revenue = floatval($prev_data['prev_revenue'] ?? 0);
    $growth_rate = 0;

    if ($prev_revenue > 0) {
        $growth_rate = (($current_revenue - $prev_revenue) / $prev_revenue) * 100;
    }

    // Get daily revenue for current month
    $daily_sql = "SELECT 
                    DATE(exit_time) as date,
                    SUM(parking_fee) as daily_revenue
                  FROM parking_logs 
                  WHERE exit_time IS NOT NULL 
                  AND TO_CHAR(exit_time, 'YYYY-MM') = $1
                  GROUP BY DATE(exit_time)
                  ORDER BY date ASC";

    $daily_result = db_prepare($daily_sql, [$current_month]);

    $daily_labels = [];
    $daily_revenue = [];

    while ($row = db_fetch_assoc($daily_result)) {
        $daily_labels[] = date('M d', strtotime($row['date']));
        $daily_revenue[] = floatval($row['daily_revenue']);
    }

    // Calculate average daily revenue
    $days_in_month = date('j'); // Current day of month
    $avg_daily_revenue = $days_in_month > 0 ? $current_revenue / $days_in_month : 0;

    // Get peak revenue (highest single transaction or highest daily revenue)
    $peak_revenue = floatval($revenue_data['max_fee'] ?? 0);

    // Get hourly data for peak analysis
    $hourly_sql = "SELECT 
                    EXTRACT(HOUR FROM exit_time) as hour,
                    SUM(parking_fee) as hourly_revenue
                   FROM parking_logs 
                   WHERE exit_time IS NOT NULL 
                   AND TO_CHAR(exit_time, 'YYYY-MM') = $1
                   GROUP BY EXTRACT(HOUR FROM exit_time)
                   ORDER BY hourly_revenue DESC
                   LIMIT 1";

    $hourly_result = db_prepare($hourly_sql, [$current_month]);
    $peak_hour_data = db_fetch_assoc($hourly_result);

    $peak_hour_revenue = floatval($peak_hour_data['hourly_revenue'] ?? 0);
    if ($peak_hour_revenue > $peak_revenue) {
        $peak_revenue = $peak_hour_revenue;
    }

    // Compile response
    echo json_encode([
        'success' => true,
        'current_month_revenue' => number_format($current_revenue, 2, '.', ''),
        'total_transactions' => intval($revenue_data['total_transactions'] ?? 0),
        'average_daily_revenue' => number_format($avg_daily_revenue, 2, '.', ''),
        'peak_revenue' => number_format($peak_revenue, 2, '.', ''),
        'growth_rate' => round($growth_rate, 2),
        'daily_labels' => $daily_labels,
        'daily_revenue' => $daily_revenue,
        'previous_month_revenue' => number_format($prev_revenue, 2, '.', ''),
        'avg_transaction_value' => number_format(floatval($revenue_data['avg_fee'] ?? 0), 2, '.', '')
    ]);

} catch (Exception $e) {
    error_log("Analytics Data Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch analytics data'
    ]);
}

?>
