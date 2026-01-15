<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: admin-login.php");
    exit;
}

// Include PostgreSQL database connection
require_once __DIR__ . '/../DB/DB_connection.php';

// Get customer registrations per day (last 7 days)
$sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM customers 
        WHERE created_at >= CURRENT_DATE - INTERVAL '7 days'
        GROUP BY DATE(created_at)
        ORDER BY date ASC";
$result = db_query($sql);

$dates = [];
$counts = [];

if ($result && db_num_rows($result) > 0) {
    while($row = db_fetch_assoc($result)) {
        $dates[] = date('M d', strtotime($row['date']));
        $counts[] = $row['count'];
    }
} else {
    // Default data if no registrations
    $dates = ['Nov 20', 'Nov 21', 'Nov 22', 'Nov 23', 'Nov 24', 'Nov 25', 'Nov 26'];
    $counts = [0, 0, 0, 0, 0, 0, 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Reports</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      background: #f3f4f6;
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
      overflow-x: hidden;
    }
    
    .admin-dashboard {
      display: flex;
      min-height: 100vh;
    }
    
    /* Sidebar Styles */
    .admin-sidebar {
      background: linear-gradient(180deg, #1e5bb8 0%, #1651c6 100%);
      color: #fff;
      width: 240px;
      padding: 2rem 1.2rem;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
      position: fixed;
      left: 0;
      top: 0;
      height: 100vh;
      overflow-y: auto;
      z-index: 100;
      transition: transform 0.3s ease;
    }
    
    .admin-sidebar h3 {
      font-size: 1.4rem;
      margin-bottom: 2.5rem;
      color: #5eead4;
      letter-spacing: 0.5px;
      line-height: 1.2;
      font-weight: 700;
    }
    
    .admin-sidebar nav {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      width: 100%;
    }
    
    .admin-sidebar a {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      background: none;
      color: #fff;
      text-decoration: none;
      font-size: 1rem;
      padding: 0.875rem 1rem;
      border-radius: 10px;
      transition: all 0.2s ease;
      font-weight: 500;
    }
    
    .admin-sidebar a.active {
      background: #ffffff;
      color: #1e5bb8;
      font-weight: 700;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }
    
    .admin-sidebar a:hover:not(.active) {
      background: rgba(255, 255, 255, 0.15);
    }
    
    /* Mobile Menu Toggle */
    .mobile-menu-toggle {
      display: none;
      position: fixed;
      top: 1.25rem;
      left: 1rem;
      z-index: 101;
      background: #1e5bb8;
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
    .admin-main {
      flex: 1;
      background: #f3f4f6;
      min-height: 100vh;
      margin-left: 240px;
      width: calc(100% - 240px);
    }
    
    .admin-header {
      background: linear-gradient(90deg, #1e5bb8 0%, #1651c6 100%);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: flex-end;
      padding: 1.5rem 2.5rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      gap: 1rem;
    }
    
    .admin-header button {
      background: transparent;
      border: 2px solid #fff;
      color: #fff;
      border-radius: 8px;
      padding: 0.6rem 2rem;
      font-size: 0.95rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      letter-spacing: 0.5px;
    }
    
    .admin-header button:hover {
      background: #fff;
      color: #1e5bb8;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
    }
    
    .admin-content {
      padding: 2.5rem;
    }
    
    .reports-container {
      background: #ffffff;
      border-radius: 16px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      padding: 2.5rem;
      max-width: 1200px;
      margin: 0 auto;
    }
    
    .reports-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 2rem;
      flex-wrap: wrap;
      gap: 1rem;
    }
    
    .reports-title {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-size: 1.5rem;
      font-weight: 700;
      color: #1a1a1a;
    }
    
    .reports-title::before {
      content: "📊";
      font-size: 1.8rem;
    }
    
    .download-btn {
      background: linear-gradient(135deg, #3b82f6 0%, #1e5bb8 100%);
      color: #ffffff;
      border: none;
      border-radius: 8px;
      padding: 0.75rem 2rem;
      font-size: 0.95rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      letter-spacing: 0.3px;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      white-space: nowrap;
    }
    
    .download-btn:hover {
      background: linear-gradient(135deg, #1e5bb8 0%, #1e40af 100%);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(30, 91, 184, 0.4);
    }
    
    .download-btn::before {
      content: "⬇";
      font-size: 1.2rem;
    }
    
    .chart-container {
      position: relative;
      height: 400px;
      margin-top: 2rem;
    }
    
    /* Tablet Styles (768px - 1024px) */
    @media (max-width: 1024px) {
      .admin-sidebar {
        width: 200px;
        padding: 1.5rem 1rem;
      }
      
      .admin-sidebar h3 {
        font-size: 1.2rem;
        margin-bottom: 2rem;
      }
      
      .admin-sidebar a {
        font-size: 0.95rem;
        padding: 0.75rem 0.875rem;
        gap: 0.6rem;
      }
      
      .admin-main {
        margin-left: 200px;
        width: calc(100% - 200px);
      }
      
      .admin-header {
        padding: 1.25rem 2rem;
      }
      
      .admin-content {
        padding: 2rem;
      }
      
      .reports-container {
        padding: 2rem;
      }
      
      .reports-title {
        font-size: 1.35rem;
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
      
      .admin-sidebar {
        transform: translateX(-100%);
        width: 280px;
      }
      
      .admin-sidebar.active {
        transform: translateX(0);
      }
      
      .overlay.active {
        display: block;
      }
      
      .admin-main {
        margin-left: 0;
        width: 100%;
      }
      
      .admin-header {
        padding: 1rem 1.5rem 1rem 4.5rem;
        justify-content: flex-end;
      }
      
      .admin-header button {
        padding: 0.5rem 1.5rem;
        font-size: 0.875rem;
      }
      
      .admin-content {
        padding: 1.5rem 1rem;
      }
      
      .reports-container {
        padding: 1.5rem;
        border-radius: 12px;
      }
      
      .reports-header {
        flex-direction: column;
        align-items: stretch;
      }
      
      .reports-title {
        font-size: 1.25rem;
      }
      
      .reports-title::before {
        font-size: 1.5rem;
      }
      
      .download-btn {
        width: 100%;
        justify-content: center;
        padding: 0.875rem 1.5rem;
      }
      
      .chart-container {
        height: 300px;
        margin-top: 1.5rem;
      }
    }
    
    /* Small Mobile Styles (up to 480px) */
    @media (max-width: 480px) {
      .admin-sidebar {
        width: 260px;
        padding: 1.25rem 0.875rem;
      }
      
      .admin-sidebar h3 {
        font-size: 1.1rem;
        margin-bottom: 1.75rem;
      }
      
      .admin-sidebar a {
        font-size: 0.9rem;
        padding: 0.7rem 0.75rem;
      }
      
      .admin-header {
        padding: 0.875rem 1rem 0.875rem 4rem;
      }
      
      .admin-header button {
        padding: 0.5rem 1.25rem;
        font-size: 0.8rem;
      }
      
      .admin-content {
        padding: 1.25rem 0.875rem;
      }
      
      .reports-container {
        padding: 1.25rem;
      }
      
      .reports-title {
        font-size: 1.1rem;
        gap: 0.5rem;
      }
      
      .reports-title::before {
        font-size: 1.3rem;
      }
      
      .download-btn {
        font-size: 0.875rem;
        padding: 0.75rem 1.25rem;
      }
      
      .download-btn::before {
        font-size: 1rem;
      }
      
      .chart-container {
        height: 280px;
      }
    }
    
    /* Extra Small Mobile (up to 360px) */
    @media (max-width: 360px) {
      .admin-sidebar {
        width: 240px;
      }
      
      .mobile-menu-toggle {
        top: 1rem;
        left: 0.75rem;
        padding: 0.5rem 0.7rem;
        font-size: 1.3rem;
      }
      
      .admin-header {
        padding: 0.75rem 0.875rem 0.75rem 3.75rem;
      }
      
      .reports-container {
        padding: 1rem;
      }
      
      .reports-title {
        font-size: 1rem;
      }
      
      .chart-container {
        height: 260px;
      }
    }
    
    /* Landscape Mobile */
    @media (max-height: 500px) and (orientation: landscape) {
      .admin-sidebar {
        padding: 1rem 0.875rem;
      }
      
      .admin-sidebar h3 {
        margin-bottom: 1rem;
        font-size: 1rem;
      }
      
      .admin-sidebar a {
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
  
  <div class="admin-dashboard">
    <aside class="admin-sidebar">
      <h3>SOUTHWOODS<br>MALL</h3>
      <nav>
        <a href="admin-dashboard.php">🏠 Home</a>
        <a href="admin-vehicle-entry.php">🚗 Vehicle Entry</a>
        <a href="admin-vehicle-logs.php">📋 Vehicle Logs</a>
        <a href="admin-reports.php" class="active">📊 Reports</a>
        <a href="admin-analytics.php">📈 Analytics</a>
        <a href="admin-archived.php">📦 Archived</a>
        <a href="admin-account-settings.php">⚙️ Account Settings</a>
        <a href="admin-logout.php">🔒 Log Out</a>
      </nav>
    </aside>
    
    <main class="admin-main">
      <div class="admin-header">
      </div>
      
      <div class="admin-content">
        <div class="reports-container">
          <div class="reports-header">
            <h2 class="reports-title">Customer Registration Reports</h2>
            <button class="download-btn" onclick="downloadReport()">Download Report</button>
          </div>
          
          <div class="chart-container">
            <canvas id="customerChart"></canvas>
          </div>
        </div>
      </div>
    </main>
  </div>
  
  <script>
    // Mobile menu toggle
    function toggleMobileMenu() {
      const sidebar = document.querySelector('.admin-sidebar');
      const overlay = document.querySelector('.overlay');
      sidebar.classList.toggle('active');
      overlay.classList.toggle('active');
    }
    
    // Close mobile menu when clicking on a link
    document.querySelectorAll('.admin-sidebar a').forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 767) {
          toggleMobileMenu();
        }
      });
    });
    
    // Chart data from PHP
    const dates = <?php echo json_encode($dates); ?>;
    const counts = <?php echo json_encode($counts); ?>;
    
    // Create chart with responsive options
    const ctx = document.getElementById('customerChart').getContext('2d');
    const customerChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: dates,
        datasets: [{
          label: 'New Customers per Day',
          data: counts,
          fill: true,
          backgroundColor: 'rgba(59, 130, 246, 0.1)',
          borderColor: '#3b82f6',
          borderWidth: 3,
          tension: 0.4,
          pointRadius: 5,
          pointBackgroundColor: '#3b82f6',
          pointBorderColor: '#ffffff',
          pointBorderWidth: 2,
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
            align: 'end',
            labels: {
              usePointStyle: true,
              padding: window.innerWidth <= 480 ? 10 : 15,
              font: {
                size: window.innerWidth <= 480 ? 11 : 12,
                weight: '500'
              }
            }
          },
          tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: window.innerWidth <= 480 ? 10 : 12,
            titleFont: {
              size: window.innerWidth <= 480 ? 12 : 14,
              weight: 'bold'
            },
            bodyFont: {
              size: window.innerWidth <= 480 ? 11 : 13
            },
            displayColors: false
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1,
              font: {
                size: window.innerWidth <= 480 ? 10 : 12
              }
            },
            grid: {
              color: '#e5e7eb'
            }
          },
          x: {
            grid: {
              display: false
            },
            ticks: {
              font: {
                size: window.innerWidth <= 480 ? 10 : 12
              },
              maxRotation: window.innerWidth <= 480 ? 45 : 0,
              minRotation: window.innerWidth <= 480 ? 45 : 0
            }
          }
        }
      }
    });
    
    // Update chart on window resize
    window.addEventListener('resize', () => {
      customerChart.options.plugins.legend.labels.padding = window.innerWidth <= 480 ? 10 : 15;
      customerChart.options.plugins.legend.labels.font.size = window.innerWidth <= 480 ? 11 : 12;
      customerChart.options.plugins.tooltip.padding = window.innerWidth <= 480 ? 10 : 12;
      customerChart.options.plugins.tooltip.titleFont.size = window.innerWidth <= 480 ? 12 : 14;
      customerChart.options.plugins.tooltip.bodyFont.size = window.innerWidth <= 480 ? 11 : 13;
      customerChart.options.scales.y.ticks.font.size = window.innerWidth <= 480 ? 10 : 12;
      customerChart.options.scales.x.ticks.font.size = window.innerWidth <= 480 ? 10 : 12;
      customerChart.options.scales.x.ticks.maxRotation = window.innerWidth <= 480 ? 45 : 0;
      customerChart.options.scales.x.ticks.minRotation = window.innerWidth <= 480 ? 45 : 0;
      customerChart.update();
    });
    
    // Download report as CSV
    function downloadReport() {
      // Send request to PHP to generate and save report
      window.location.href = 'generate-report.php';
    }
  </script>
</body>
</html>

