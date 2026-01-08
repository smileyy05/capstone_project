<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header('Location: admin-login.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../DB/DB_connection.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fetch analytics data
$current_month = date('Y-m');
$previous_month = date('Y-m', strtotime('-1 month'));

// Debug: Check total records
$debug_sql = "SELECT COUNT(*) as total, 
              COUNT(CASE WHEN exit_time IS NOT NULL THEN 1 END) as with_exit,
              COUNT(CASE WHEN status = 'exited' THEN 1 END) as exited_status
              FROM parking_logs";
$debug_result = pg_query($debug_sql);
$debug_data = pg_fetch_assoc($debug_result);
error_log("Total records: " . print_r($debug_data, true));

// Get current month's revenue - Modified query to be more flexible
$current_month_sql = "SELECT COALESCE(SUM(parking_fee), 0) as total 
                      FROM parking_logs 
                      WHERE exit_time IS NOT NULL
                      AND parking_fee > 0
                      AND TO_CHAR(exit_time, 'YYYY-MM') = $1";

try {
    $current_month_result = db_prepare($current_month_sql, [$current_month]);
    $current_month_data = db_fetch_assoc($current_month_result);
    $current_month_revenue = floatval($current_month_data['total'] ?? 0);
    
    error_log("Current month revenue: $current_month_revenue");
} catch (Exception $e) {
    error_log("Error fetching current month revenue: " . $e->getMessage());
    $current_month_revenue = 0;
}

// Get previous month's revenue
$previous_month_sql = "SELECT COALESCE(SUM(parking_fee), 0) as total 
                       FROM parking_logs 
                       WHERE exit_time IS NOT NULL
                       AND parking_fee > 0
                       AND TO_CHAR(exit_time, 'YYYY-MM') = $1";

try {
    $previous_month_result = db_prepare($previous_month_sql, [$previous_month]);
    $previous_month_data = db_fetch_assoc($previous_month_result);
    $previous_month_revenue = floatval($previous_month_data['total'] ?? 0);
} catch (Exception $e) {
    error_log("Error fetching previous month revenue: " . $e->getMessage());
    $previous_month_revenue = 0;
}

// Calculate growth rate
$growth_rate = 0;
if ($previous_month_revenue > 0) {
    $growth_rate = (($current_month_revenue - $previous_month_revenue) / $previous_month_revenue) * 100;
} elseif ($current_month_revenue > 0) {
    $growth_rate = 100;
}

// Get total transactions this month
$transactions_sql = "SELECT COUNT(*) as count 
                    FROM parking_logs 
                    WHERE exit_time IS NOT NULL
                    AND parking_fee > 0
                    AND TO_CHAR(exit_time, 'YYYY-MM') = $1";

try {
    $transactions_result = db_prepare($transactions_sql, [$current_month]);
    $transactions_data = db_fetch_assoc($transactions_result);
    $total_transactions = intval($transactions_data['count'] ?? 0);
} catch (Exception $e) {
    error_log("Error fetching transactions: " . $e->getMessage());
    $total_transactions = 0;
}

// Get average daily revenue this month
$days_in_month = date('t');
$average_daily_revenue = $days_in_month > 0 ? $current_month_revenue / $days_in_month : 0;

// Get peak hour revenue
$peak_revenue_sql = "SELECT EXTRACT(HOUR FROM exit_time) as hour, 
                           COALESCE(SUM(parking_fee), 0) as revenue 
                    FROM parking_logs 
                    WHERE exit_time IS NOT NULL
                    AND parking_fee > 0
                    AND TO_CHAR(exit_time, 'YYYY-MM') = $1
                    GROUP BY EXTRACT(HOUR FROM exit_time) 
                    ORDER BY revenue DESC 
                    LIMIT 1";

try {
    $peak_revenue_result = db_prepare($peak_revenue_sql, [$current_month]);
    $peak_revenue_data = db_fetch_assoc($peak_revenue_result);
    $peak_revenue = floatval($peak_revenue_data['revenue'] ?? 0);
} catch (Exception $e) {
    error_log("Error fetching peak revenue: " . $e->getMessage());
    $peak_revenue = 0;
}

// Get daily revenue data for chart
$daily_revenue_sql = "SELECT 
                        TO_CHAR(exit_time, 'Mon DD') as date_label,
                        EXTRACT(DAY FROM exit_time)::INTEGER as day,
                        COALESCE(SUM(parking_fee), 0) as revenue
                      FROM parking_logs
                      WHERE exit_time IS NOT NULL
                      AND parking_fee > 0
                      AND TO_CHAR(exit_time, 'YYYY-MM') = $1
                      GROUP BY TO_CHAR(exit_time, 'Mon DD'), EXTRACT(DAY FROM exit_time)
                      ORDER BY day";

try {
    $daily_revenue_result = db_prepare($daily_revenue_sql, [$current_month]);
    $daily_revenue_data = db_fetch_all($daily_revenue_result);
} catch (Exception $e) {
    error_log("Error fetching daily revenue: " . $e->getMessage());
    $daily_revenue_data = [];
}

// Prepare chart data
$chart_labels = [];
$chart_values = [];

if ($daily_revenue_data && is_array($daily_revenue_data)) {
    foreach ($daily_revenue_data as $row) {
        $chart_labels[] = $row['date_label'];
        $chart_values[] = floatval($row['revenue']);
    }
}

// If no data, add debug info
if (empty($chart_labels)) {
    error_log("No chart data found for month: $current_month");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Predictive Analytics Dashboard - Southwoods Mall</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f0f2f5;
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #2563eb 0%, #1e40af 100%);
            color: white;
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            padding: 0 1.5rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1rem;
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #10b981;
            line-height: 1.2;
        }

        .nav-menu {
            list-style: none;
            padding: 0 1rem;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.875rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 1rem;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .nav-link.active {
            background: white;
            color: #2563eb;
        }

        .nav-link i {
            margin-right: 0.75rem;
            font-size: 1.25rem;
            width: 24px;
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1.25rem;
            left: 1rem;
            z-index: 101;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.6rem 0.8rem;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .mobile-menu-toggle:active {
            transform: scale(0.95);
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 2rem;
            width: calc(100% - 280px);
        }

        .top-header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .top-header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .top-header h1 i {
            margin-right: 0.25rem;
        }

        .logout-btn {
            background: white;
            color: #2563eb;
            border: none;
            padding: 0.625rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            color: #2563eb;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            word-wrap: break-word;
        }

        .stat-description {
            color: #9ca3af;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .stat-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .stat-badge.positive {
            background: #dcfce7;
            color: #16a34a;
        }

        .stat-badge.negative {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Prediction Section */
        .prediction-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .prediction-section h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .prediction-section h2 i {
            margin-right: 0.25rem;
            color: #2563eb;
        }

        .prediction-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
            cursor: pointer;
            transition: border-color 0.3s;
        }

        .form-group select:focus {
            outline: none;
            border-color: #2563eb;
        }

        .predict-btn {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            border: none;
            padding: 0.875rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .predict-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(37, 99, 235, 0.3);
        }

        /* Chart Section */
        .chart-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .chart-section h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .chart-container {
            position: relative;
            height: 400px;
        }

        /* AI Insights */
        .insights-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .insights-section h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .insights-section h2 i {
            margin-right: 0.25rem;
            color: #f59e0b;
        }

        .insight-box {
            background: #f0f9ff;
            border-left: 4px solid #2563eb;
            padding: 1.5rem;
            border-radius: 8px;
        }

        .insight-box h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .insight-box h3 i {
            margin-right: 0.25rem;
            color: #2563eb;
        }

        .insight-box p {
            color: #6b7280;
            line-height: 1.6;
            word-wrap: break-word;
        }

        /* Tablet Styles (768px - 1024px) */
        @media (max-width: 1024px) {
            .sidebar {
                width: 240px;
            }

            .sidebar-header {
                padding: 0 1.25rem 1.75rem;
            }

            .sidebar-header h2 {
                font-size: 1.3rem;
            }

            .nav-link {
                padding: 0.75rem 0.875rem;
                font-size: 0.95rem;
            }

            .main-content {
                margin-left: 240px;
                width: calc(100% - 240px);
                padding: 1.75rem;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            }

            .stat-value {
                font-size: 2.25rem;
            }

            .chart-container {
                height: 350px;
            }
        }

        /* Mobile Styles (up to 767px) */
        @media (max-width: 767px) {
            .mobile-menu-toggle {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .overlay.active {
                display: block;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 1.5rem 1rem;
            }

            .top-header {
                padding: 1.25rem 1.5rem 1.25rem 4.5rem;
                flex-direction: column;
                align-items: stretch;
            }

            .top-header h1 {
                font-size: 1.4rem;
                margin-bottom: 0.75rem;
            }

            .logout-btn {
                width: 100%;
                padding: 0.7rem 1.25rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1.25rem;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .stat-value {
                font-size: 2rem;
            }

            .prediction-section,
            .chart-section,
            .insights-section {
                padding: 1.5rem;
            }

            .prediction-section h2,
            .insights-section h2 {
                font-size: 1.3rem;
            }

            .prediction-form {
                grid-template-columns: 1fr;
                gap: 1.25rem;
            }

            .predict-btn {
                width: 100%;
                justify-content: center;
            }

            .chart-section h3 {
                font-size: 1.1rem;
            }

            .chart-container {
                height: 300px;
            }

            .insight-box {
                padding: 1.25rem;
            }

            .insight-box h3 {
                font-size: 1rem;
            }
        }

        /* Small Mobile Styles (up to 480px) */
        @media (max-width: 480px) {
            .sidebar {
                width: 260px;
            }

            .sidebar-header h2 {
                font-size: 1.2rem;
            }

            .nav-link {
                font-size: 0.9rem;
                padding: 0.7rem 0.75rem;
            }

            .nav-link i {
                font-size: 1.1rem;
            }

            .main-content {
                padding: 1.25rem 0.875rem;
            }

            .top-header {
                padding: 1rem 1.25rem 1rem 4rem;
                border-radius: 10px;
            }

            .top-header h1 {
                font-size: 1.25rem;
            }

            .logout-btn {
                font-size: 0.9rem;
                padding: 0.65rem 1rem;
            }

            .stat-card {
                padding: 1.25rem;
            }

            .stat-value {
                font-size: 1.75rem;
            }

            .stat-label,
            .stat-description {
                font-size: 0.8rem;
            }

            .prediction-section,
            .chart-section,
            .insights-section {
                padding: 1.25rem;
                border-radius: 10px;
            }

            .prediction-section h2,
            .insights-section h2 {
                font-size: 1.2rem;
            }

            .form-group label {
                font-size: 0.85rem;
            }

            .form-group select {
                padding: 0.65rem;
                font-size: 0.95rem;
            }

            .predict-btn {
                padding: 0.75rem 1.5rem;
                font-size: 0.95rem;
            }

            .chart-section h3 {
                font-size: 1rem;
            }

            .chart-container {
                height: 280px;
            }

            .insight-box {
                padding: 1rem;
            }

            .insight-box h3 {
                font-size: 0.95rem;
            }

            .insight-box p {
                font-size: 0.9rem;
            }
        }

        /* Extra Small Mobile (up to 360px) */
        @media (max-width: 360px) {
            .sidebar {
                width: 240px;
            }

            .mobile-menu-toggle {
                top: 1rem;
                left: 0.75rem;
                padding: 0.5rem 0.7rem;
                font-size: 1.3rem;
            }

            .main-content {
                padding: 1rem 0.75rem;
            }

            .top-header {
                padding: 0.875rem 1rem 0.875rem 3.75rem;
            }

            .top-header h1 {
                font-size: 1.15rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-value {
                font-size: 1.6rem;
            }

            .prediction-section,
            .chart-section,
            .insights-section {
                padding: 1rem;
            }

            .chart-container {
                height: 260px;
            }
        }

        /* Landscape Mobile */
        @media (max-height: 500px) and (orientation: landscape) {
            .sidebar {
                padding: 1rem 0;
            }

            .sidebar-header {
                padding: 0 1rem 1rem;
                margin-bottom: 0.75rem;
            }

            .sidebar-header h2 {
                font-size: 1rem;
            }

            .nav-link {
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
            }

            .chart-container {
                height: 240px;
            }
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">☰</button>
    <div class="overlay" onclick="toggleMobileMenu()"></div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>SOUTHWOODS<br>MALL</h2>
        </div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="admin-dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-vehicle-entry.php" class="nav-link">
                    <i class="fas fa-car"></i>
                    <span>Vehicle Entry</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-vehicle-logs.php" class="nav-link">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Vehicle Logs</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-reports.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-analytics.php" class="nav-link active">
                    <i class="fas fa-chart-line"></i>
                    <span>Analytics</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-archived.php" class="nav-link">
                    <i class="fas fa-archive"></i>
                    <span>Archived</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-account-settings.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    <span>Account Settings</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="top-header">
            <h1>
                <i class="fas fa-chart-line"></i>
                <span>Predictive Analytics Dashboard</span>
            </h1>
            <button class="logout-btn" onclick="window.location.href='admin-logout.php'">
                LOGOUT
            </button>
        </div>

         <?php if ($current_month_revenue == 0 && $total_transactions == 0): ?>
        <div class="debug-info">
            <strong>⚠️ Debug Info:</strong> No revenue data found for <?php echo $current_month; ?>. 
            Please check: (1) Records have exit_time filled, (2) parking_fee > 0, (3) Date format matches YYYY-MM.
            <br>Total DB records: <?php echo $debug_data['total'] ?? 'unknown'; ?> | 
            With exit time: <?php echo $debug_data['with_exit'] ?? 'unknown'; ?> | 
            Status 'exited': <?php echo $debug_data['exited_status'] ?? 'unknown'; ?>
        </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">THIS MONTH'S REVENUE</div>
                <div class="stat-value">₱<?php echo number_format($current_month_revenue, 2); ?></div>
                <div class="stat-description">Total earnings this month</div>
                <span class="stat-badge <?php echo $growth_rate >= 0 ? 'positive' : 'negative'; ?>">
                    <i class="fas fa-<?php echo $growth_rate >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                    <?php echo number_format(abs($growth_rate), 1); ?>%
                </span>
            </div>

            <div class="stat-card">
                <div class="stat-label">TOTAL TRANSACTIONS</div>
                <div class="stat-value"><?php echo number_format($total_transactions); ?></div>
                <div class="stat-description">Completed parking sessions</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">AVERAGE REVENUE/DAY</div>
                <div class="stat-value">₱<?php echo number_format($average_daily_revenue, 2); ?></div>
                <div class="stat-description">Daily average earnings</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">PEAK HOUR REVENUE</div>
                <div class="stat-value">₱<?php echo number_format($peak_revenue, 2); ?></div>
                <div class="stat-description">Highest earning period</div>
            </div>
        </div>

        <!-- Prediction Section -->
        <div class="prediction-section">
            <h2>
                <i class="fas fa-chart-line"></i>
                <span>Revenue Prediction</span>
            </h2>
            <form class="prediction-form" id="predictionForm">
                <div class="form-group">
                    <label>Predict for:</label>
                    <select id="predictionPeriod" name="period">
                        <option value="1">Next 1 Month</option>
                        <option value="3">Next 3 Months</option>
                        <option value="6">Next 6 Months</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Expected Growth Rate:</label>
                    <select id="growthRate" name="growth">
                        <option value="5">Low (5%)</option>
                        <option value="10" selected>Moderate (10%)</option>
                        <option value="15">High (15%)</option>
                    </select>
                </div>
            </form>
            <button class="predict-btn" onclick="generatePrediction()">
                <i class="fas fa-magic"></i>
                Generate Prediction
            </button>
        </div>

        <!-- Chart Section -->
        <div class="chart-section">
            <h3>Daily Revenue Trend (Current Month)</h3>
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- AI Insights -->
        <div class="insights-section">
            <h2>
                <i class="fas fa-lightbulb"></i>
                <span>AI-Powered Insights</span>
            </h2>
            <div class="insight-box" id="insightBox">
                <h3>
                    <i class="fas fa-chart-bar"></i>
                    <span>Waiting for Analysis</span>
                </h3>
                <p>Generate a prediction to receive AI-powered insights about your revenue trends.</p>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        function toggleMobileMenu() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        // Close mobile menu when clicking on a link
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 767) {
                    toggleMobileMenu();
                }
            });
        });

        // Chart Data
        const chartLabels = <?php echo json_encode($chart_labels); ?>;
        const chartValues = <?php echo json_encode($chart_values); ?>;

        // Initialize Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Daily Revenue',
                    data: chartValues,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: window.innerWidth <= 480 ? 10 : 15,
                            font: {
                                size: window.innerWidth <= 480 ? 11 : 14,
                                weight: 'bold'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: window.innerWidth <= 480 ? 10 : 12,
                        titleFont: {
                            size: window.innerWidth <= 480 ? 12 : 14
                        },
                        bodyFont: {
                            size: window.innerWidth <= 480 ? 11 : 13
                        },
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: ₱' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toFixed(0);
                            },
                            font: {
                                size: window.innerWidth <= 480 ? 10 : 12
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: window.innerWidth <= 480 ? 10 : 12
                            },
                            maxRotation: window.innerWidth <= 480 ? 45 : 0,
                            minRotation: window.innerWidth <= 480 ? 45 : 0
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Update chart on window resize
        window.addEventListener('resize', () => {
            revenueChart.options.plugins.legend.labels.padding = window.innerWidth <= 480 ? 10 : 15;
            revenueChart.options.plugins.legend.labels.font.size = window.innerWidth <= 480 ? 11 : 14;
            revenueChart.options.plugins.tooltip.padding = window.innerWidth <= 480 ? 10 : 12;
            revenueChart.options.plugins.tooltip.titleFont.size = window.innerWidth <= 480 ? 12 : 14;
            revenueChart.options.plugins.tooltip.bodyFont.size = window.innerWidth <= 480 ? 11 : 13;
            revenueChart.options.scales.y.ticks.font.size = window.innerWidth <= 480 ? 10 : 12;
            revenueChart.options.scales.x.ticks.font.size = window.innerWidth <= 480 ? 10 : 12;
            revenueChart.options.scales.x.ticks.maxRotation = window.innerWidth <= 480 ? 45 : 0;
            revenueChart.options.scales.x.ticks.minRotation = window.innerWidth <= 480 ? 45 : 0;
            revenueChart.update();
        });

        function generatePrediction() {
            const period = parseInt(document.getElementById('predictionPeriod').value);
            const growth = parseFloat(document.getElementById('growthRate').value);
            const currentRevenue = <?php echo $current_month_revenue; ?>;
            
            // Calculate prediction
            let predictedRevenue;
            if (period === 1) {
                predictedRevenue = currentRevenue * (1 + (growth / 100));
            } else {
                predictedRevenue = currentRevenue * Math.pow((1 + (growth / 100)), period);
            }
            
            const growthAmount = predictedRevenue - currentRevenue;
            const totalTransactions = <?php echo $total_transactions; ?>;
            const avgTransaction = totalTransactions > 0 ? currentRevenue / totalTransactions : 0;
            const projectedTransactions = Math.round(totalTransactions * (1 + (growth / 100)) * period);
            
            // Update insights
            const insightBox = document.getElementById('insightBox');
            insightBox.innerHTML = `
                <h3>
                    <i class="fas fa-chart-bar"></i>
                    <span>Revenue Prediction Analysis</span>
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <p style="margin-bottom: 0.5rem;"><strong>Current Revenue:</strong></p>
                        <p style="font-size: 1.5rem; color: #2563eb; font-weight: 700;">₱${currentRevenue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                    </div>
                    <div>
                        <p style="margin-bottom: 0.5rem;"><strong>Predicted (${period}mo):</strong></p>
                        <p style="font-size: 1.5rem; color: #10b981; font-weight: 700;">₱${predictedRevenue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                    </div>
                </div>
                
                <div style="background: #f0f9ff; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <p style="margin-bottom: 0.5rem; font-size: 0.9rem;"><strong>Growth Rate:</strong> ${growth}% per month</p>
                    <p style="margin-bottom: 0.5rem; font-size: 0.9rem;"><strong>Growth Amount:</strong> ₱${growthAmount.toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                    <p style="margin-bottom: 0.5rem; font-size: 0.9rem;"><strong>Transactions:</strong> ${totalTransactions.toLocaleString()} → ${projectedTransactions.toLocaleString()}</p>
                    <p style="font-size: 0.9rem;"><strong>Avg Transaction:</strong> ₱${avgTransaction.toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                </div>
                
                <div style="padding: 1rem; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-radius: 8px; border-left: 4px solid #2563eb;">
                    <p style="line-height: 1.6; color: #1f2937; font-size: 0.95rem; margin-bottom: 0.75rem;">
                        <strong>📊 Analysis:</strong> Based on ${growth}% monthly growth, projected revenue is 
                        <strong>₱${predictedRevenue.toLocaleString('en-US', {minimumFractionDigits: 2})}</strong> over ${period} month${period > 1 ? 's' : ''}. 
                        This is a <strong>${((growthAmount / currentRevenue) * 100).toFixed(1)}%</strong> increase.
                    </p>
                    <p style="line-height: 1.6; color: #1f2937; font-size: 0.95rem; margin-bottom: 0.75rem;">
                        ${growth >= 15 ? '🚀 <strong>High Growth:</strong> Ambitious target. Focus on service quality and capacity expansion.' : 
                          growth >= 10 ? '📈 <strong>Moderate Growth:</strong> Healthy growth. Optimize peak hours and pricing.' : 
                          '📉 <strong>Conservative Growth:</strong> Stable projection. Consider promotional strategies.'}
                    </p>
                    <p style="line-height: 1.6; color: #1f2937; font-size: 0.95rem;">
                        💡 <strong>Tip:</strong> Monitor trends and adjust strategies based on performance.
                    </p>
                </div>
            `;
            
            // Success feedback
            const btn = document.querySelector('.predict-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Prediction Generated!';
            btn.style.background = '#10b981';
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.style.background = '';
            }, 2000);
        }
    </script>
</body>
</html>

