<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: admin-login.php");
    exit;
}

// Include PostgreSQL database connection
require_once '../DB/DB_connection.php';

// Initialize variables
$totalEarnings = 0;
$departedVehicles = 0;
$totalSlots = 5;
$occupiedSlots = 0;
$availableSlots = $totalSlots;

try {
    // Calculate Total Earnings
    // Sum all parking fees from vehicles that have exited (exit_time is not null)
    $earningsQuery = "SELECT COALESCE(SUM(parking_fee), 0) as total FROM parking_logs WHERE exit_time IS NOT NULL";
    $earningsResult = db_query($earningsQuery);
    if ($earningsResult && $row = db_fetch_assoc($earningsResult)) {
        $totalEarnings = $row['total'] ?? 0;
    }

    // Count Departed Vehicles (vehicles that have exit_time set)
    $departedQuery = "SELECT COUNT(*) as count FROM parking_logs WHERE exit_time IS NOT NULL";
    $departedResult = db_query($departedQuery);
    if ($departedResult && $row = db_fetch_assoc($departedResult)) {
        $departedVehicles = $row['count'] ?? 0;
    }

    // Count Occupied Slots (currently parked vehicles - no exit_time)
    $occupiedQuery = "SELECT COUNT(*) as count FROM parking_logs WHERE exit_time IS NULL";
    $occupiedResult = db_query($occupiedQuery);
    if ($occupiedResult && $row = db_fetch_assoc($occupiedResult)) {
        $occupiedSlots = $row['count'] ?? 0;
    }

    // Calculate Available Slots
    $availableSlots = $totalSlots - $occupiedSlots;

} catch (Exception $e) {
    // Log error but don't show to user
    error_log("Dashboard Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard HOME</title>
  <link rel="stylesheet" href="admin-style.css">
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
    }
    
    .admin-dashboard {
      display: flex;
      min-height: 100vh;
    }
    
    .admin-sidebar {
      background: linear-gradient(180deg, #1e5bb8 0%, #1651c6 100%);
      color: #fff;
      width: 240px;
      padding: 2rem 1.2rem;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
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
    
    .admin-main {
      flex: 1;
      background: #f3f4f6;
      min-height: 100vh;
    }
    
    .admin-header {
      background: linear-gradient(90deg, #1e5bb8 0%, #1651c6 100%);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.5rem 2.5rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .admin-header span {
      font-weight: 700;
      font-size: 1.1rem;
      letter-spacing: 1px;
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
    
    .admin-cards {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 2rem;
      max-width: 1100px;
      margin: 0 auto;
    }
    
    .admin-card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      padding: 2.5rem 2rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 180px;
      border: 3px solid;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .admin-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    }
    
    .admin-card.white {
      border-color: #e5e7eb;
      background: #ffffff;
    }
    
    .admin-card.yellow {
      background: #fef9c3;
      border-color: #fde047;
    }
    
    .admin-card.red {
      background: #fecdd3;
      border-color: #f87171;
    }
    
    .admin-card.green {
      background: #d1fae5;
      border-color: #6ee7b7;
    }
    
    .admin-card .card-title {
      font-size: 1rem;
      font-weight: 700;
      margin-bottom: 1rem;
      text-align: center;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #1a1a1a;
    }
    
    .admin-card .card-value {
      font-size: 3rem;
      font-weight: 800;
      text-align: center;
      line-height: 1;
    }
    
    .admin-card.white .card-value {
      color: #1a1a1a;
    }
    
    .admin-card.yellow .card-value {
      color: #92400e;
    }
    
    .admin-card.red .card-value {
      color: #991b1b;
    }
    
    .admin-card.green .card-value {
      color: #065f46;
    }
    
    @media (max-width: 1024px) {
      .admin-cards {
        grid-template-columns: 1fr;
        gap: 1.5rem;
      }
    }
    
    @media (max-width: 768px) {
      .admin-sidebar {
        width: 80px;
        padding: 1.5rem 0.5rem;
      }
      
      .admin-sidebar h3 {
        font-size: 0.75rem;
        margin-bottom: 1.5rem;
        text-align: center;
        width: 100%;
      }
      
      .admin-sidebar nav a {
        font-size: 0.85rem;
        padding: 0.7rem 0.5rem;
        justify-content: center;
        gap: 0.5rem;
      }
      
      .admin-header {
        padding: 1.2rem 1.5rem;
      }
      
      .admin-header span {
        font-size: 0.95rem;
      }
      
      .admin-header button {
        padding: 0.5rem 1.2rem;
        font-size: 0.85rem;
      }
      
      .admin-content {
        padding: 1.5rem;
      }
      
      .admin-card {
        padding: 2rem 1.5rem;
        min-height: 160px;
      }
      
      .admin-card .card-title {
        font-size: 0.9rem;
      }
      
      .admin-card .card-value {
        font-size: 2.5rem;
      }
    }
  </style>
</head>
<body>
  <div class="admin-dashboard">
    <aside class="admin-sidebar">
      <h3>SOUTHWOODS<br>MALL</h3>
      <nav>
        <a href="admin-dashboard.php" class="active">🏠 Home</a>
        <a href="admin-vehicle-entry.php">🚗 Vehicle Entry</a>
        <a href="admin-vehicle-logs.php">📋 Vehicle Logs</a>
        <a href="admin-reports.php">📊 Reports</a>
        <a href="admin-analytics.php">📈 Analytics</a>
        <a href="admin-archived.php">📦 Archived</a>
        <a href="admin-account-settings.php">⚙️ Account Settings</a>
        <a href="admin-logout.php">🔒 Log Out</a>
      </nav>
    </aside>
    
    <main class="admin-main">
      <div class="admin-header">
        <span>WELCOME, ADMIN!</span>
        <button onclick="window.location.href='admin-logout.php'">LOGOUT</button>
      </div>
      
      <div class="admin-content">
        <div class="admin-cards">
          <div class="admin-card white">
            <div class="card-title">Total Earnings</div>
            <div class="card-value">₱ <?php echo number_format($totalEarnings, 2); ?></div>
          </div>
          
          <div class="admin-card yellow">
            <div class="card-title">Exited Vehicles</div>
            <div class="card-value"><?php echo number_format($departedVehicles); ?></div>
          </div>
          
          <div class="admin-card green">
            <div class="card-title">Parking Slots Available</div>
            <div class="card-value"><?php echo number_format($availableSlots); ?></div>
          </div>
          
          <div class="admin-card red">
            <div class="card-title">Parking Slots Occupied</div>
            <div class="card-value"><?php echo number_format($occupiedSlots); ?></div>
          </div>
        </div>
      </div>
    </main>
  </div>
  
  <script>
    // Auto-refresh dashboard every 5 seconds
    setInterval(function() {
      location.reload();
    }, 5000);
  </script>
</body>
</html>