<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header('Location: admin-login.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/../DB/DB_connection.php';

// Fetch analytics data
$current_month = date('Y-m');
$previous_month = date('Y-m', strtotime('-1 month'));

// Get current month's revenue
$current_month_sql = "SELECT COALESCE(SUM(parking_fee), 0) as total 
                      FROM parking_logs 
                      WHERE status = 'exited' 
                      AND exit_time IS NOT NULL
                      AND TO_CHAR(exit_time, 'YYYY-MM') = ?";

$current_month_result = db_prepare($current_month_sql, [$current_month]);
$current_month_data = db_fetch_assoc($current_month_result);
$current_month_revenue = floatval($current_month_data['total'] ?? 0);

// Get previous month's revenue
$previous_month_sql = "SELECT COALESCE(SUM(parking_fee), 0) as total 
                       FROM parking_logs 
                       WHERE status = 'exited' 
                       AND exit_time IS NOT NULL
                       AND TO_CHAR(exit_time, 'YYYY-MM') = ?";

$previous_month_result = db_prepare($previous_month_sql, [$previous_month]);
$previous_month_data = db_fetch_assoc($previous_month_result);
$previous_month_revenue = floatval($previous_month_data['total'] ?? 0);

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
                    WHERE status = 'exited' 
                    AND exit_time IS NOT NULL
                    AND TO_CHAR(exit_time, 'YYYY-MM') = ?";

$transactions_result = db_prepare($transactions_sql, [$current_month]);
$transactions_data = db_fetch_assoc($transactions_result);
$total_transactions = intval($transactions_data['count'] ?? 0);

// Get average daily revenue this month
$days_in_month = date('t');
$average_daily_revenue = $days_in_month > 0 ? $current_month_revenue / $days_in_month : 0;

// Get peak hour revenue
$peak_revenue_sql = "SELECT EXTRACT(HOUR FROM exit_time) as hour, 
                           COALESCE(SUM(parking_fee), 0) as revenue 
                    FROM parking_logs 
                    WHERE status = 'exited' 
                    AND exit_time IS NOT NULL
                    AND TO_CHAR(exit_time, 'YYYY-MM') = ?
                    GROUP BY EXTRACT(HOUR FROM exit_time) 
                    ORDER BY revenue DESC 
                    LIMIT 1";

$peak_revenue_result = db_prepare($peak_revenue_sql, [$current_month]);
$peak_revenue_data = db_fetch_assoc($peak_revenue_result);
$peak_revenue = floatval($peak_revenue_data['revenue'] ?? 0);

// Get daily revenue data for chart
$daily_revenue_sql = "SELECT 
                        TO_CHAR(exit_time, 'Mon DD') as date_label,
                        EXTRACT(DAY FROM exit_time)::INTEGER as day,
                        COALESCE(SUM(parking_fee), 0) as revenue
                      FROM parking_logs
                      WHERE status = 'exited'
                      AND exit_time IS NOT NULL
                      AND TO_CHAR(exit_time, 'YYYY-MM') = ?
                      GROUP BY TO_CHAR(exit_time, 'Mon DD'), EXTRACT(DAY FROM exit_time)
                      ORDER BY day";

$daily_revenue_result = db_prepare($daily_revenue_sql, [$current_month]);
$daily_revenue_data = db_fetch_all($daily_revenue_result);

// Prepare chart data
$chart_labels = [];
$chart_values = [];

if ($daily_revenue_data && is_array($daily_revenue_data)) {
    foreach ($daily_revenue_data as $row) {
        $chart_labels[] = $row['date_label'];
        $chart_values[] = floatval($row['revenue']);
    }
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
        }

        .top-header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .top-header h1 i {
            margin-right: 0.75rem;
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
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
            background: #fee2e2;
            color: #dc2626;
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
        }

        .prediction-section h2 i {
            margin-right: 0.75rem;
            color: #2563eb;
        }

        .prediction-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        }

        .insights-section h2 i {
            margin-right: 0.75rem;
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
        }

        .insight-box h3 i {
            margin-right: 0.5rem;
            color: #2563eb;
        }

        .insight-box p {
            color: #6b7280;
            line-height: 1.6;
        }
    </style>
</head>
<body>
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
                Predictive Analytics Dashboard
            </h1>
            <button class="logout-btn" onclick="window.location.href='admin-logout.php'">
                LOGOUT
            </button>
        </div>

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
                Revenue Prediction
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
                AI-Powered Insights
            </h2>
            <div class="insight-box">
                <h3>
                    <i class="fas fa-chart-bar"></i>
                    Waiting for Analysis
                </h3>
                <p>Generate a prediction to receive AI-powered insights about your revenue trends.</p>
            </div>
        </div>
    </div>

    <script>
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
                            padding: 15,
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 14
                        },
                        bodyFont: {
                            size: 13
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
                                size: 12
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 12
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        function generatePrediction() {
            const period = document.getElementById('predictionPeriod').value;
            const growth = document.getElementById('growthRate').value;
            const currentRevenue = <?php echo $current_month_revenue; ?>;
            
            // Calculate prediction
            const predictedRevenue = currentRevenue * (1 + (growth / 100));
            
            // Update insights
            const insightBox = document.querySelector('.insight-box');
            insightBox.innerHTML = `
                <h3>
                    <i class="fas fa-chart-bar"></i>
                    Revenue Prediction Analysis
                </h3>
                <p><strong>Current Month Revenue:</strong> ₱${currentRevenue.toFixed(2)}</p>
                <p><strong>Predicted Revenue (${period} month${period > 1 ? 's' : ''}):</strong> ₱${predictedRevenue.toFixed(2)}</p>
                <p><strong>Expected Growth:</strong> ${growth}% (₱${(predictedRevenue - currentRevenue).toFixed(2)})</p>
                <p style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                    Based on the ${growth}% growth rate, your parking facility is projected to earn approximately 
                    ₱${predictedRevenue.toFixed(2)} in the next ${period} month${period > 1 ? 's' : ''}. 
                    ${growth >= 10 ? 'This represents strong growth potential. Consider optimizing peak hours to maximize revenue.' : 
                    'Consider implementing promotional strategies to boost growth.'}
                </p>
            `;
        }
    </script>
</body>
</html>
