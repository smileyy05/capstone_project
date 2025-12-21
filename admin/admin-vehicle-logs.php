<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: admin-login.php");
    exit;
}

// Include PostgreSQL database connection
require_once '../DB/DB_connection.php';

// Check if parking_logs table exists, if not create it
$table_check = db_query("SELECT EXISTS (
    SELECT FROM information_schema.tables 
    WHERE table_schema = 'public' 
    AND table_name = 'parking_logs'
)");
$row = db_fetch_assoc($table_check);

if ($row['exists'] === 'f' || $row['exists'] === false) {
    $create_table = "CREATE TABLE parking_logs (
        id SERIAL PRIMARY KEY,
        customer_id INTEGER NULL,
        customer_name VARCHAR(100) NULL,
        plate VARCHAR(20) NULL,
        vehicle VARCHAR(50) NULL,
        entry_time TIMESTAMP,
        exit_time TIMESTAMP NULL,
        parking_fee NUMERIC(10,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id)
    )";
    db_query($create_table);
}

// Fetch parking logs with JOIN to get customer info if available
$sql = "SELECT 
    pl.id,
    pl.customer_id,
    -- If customer_id exists, get info from customers; otherwise use log info
    COALESCE(c.name, pl.customer_name) AS customer_name,
    COALESCE(c.plate, pl.plate) AS plate,
    COALESCE(c.vehicle, pl.vehicle) AS vehicle,
    pl.entry_time,
    pl.exit_time,
    pl.parking_fee
FROM parking_logs pl
LEFT JOIN customers c ON pl.customer_id = c.id
ORDER BY pl.entry_time DESC";

$result = db_query($sql);

// Calculate statistics
$active_query = db_query("SELECT COUNT(*) as count FROM parking_logs WHERE exit_time IS NULL");
$active_count = db_fetch_assoc($active_query)['count'];

$completed_query = db_query("SELECT COUNT(*) as count FROM parking_logs WHERE DATE(exit_time) = CURRENT_DATE");
$completed_today = db_fetch_assoc($completed_query)['count'];

$revenue_query = db_query("SELECT COALESCE(SUM(parking_fee), 0) as total FROM parking_logs WHERE DATE(exit_time) = CURRENT_DATE");
$revenue_today = db_fetch_assoc($revenue_query)['total'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin - Vehicle Logs</title>
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
      gap: 1rem;
    }
    
    .admin-header h2 {
      font-size: 1.5rem;
      font-weight: 700;
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .admin-header input[type="text"] {
      padding: 0.65rem 1.2rem;
      border-radius: 8px;
      border: none;
      font-size: 0.95rem;
      width: 250px;
      background: #ffffff;
      transition: all 0.3s ease;
    }

    .admin-header input[type="text"]:focus {
      outline: none;
      box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
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
    
    .logs-table-container {
      background: #ffffff;
      border-radius: 16px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      overflow: hidden;
    }
    
    .logs-table {
      width: 100%;
      border-collapse: collapse;
    }
    
    .logs-table thead {
      background: #f8fafc;
      border-bottom: 2px solid #e2e8f0;
    }
    
    .logs-table th {
      padding: 1rem 1.5rem;
      text-align: left;
      font-weight: 700;
      font-size: 0.95rem;
      color: #1a1a1a;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .logs-table td {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid #f1f5f9;
      font-size: 0.95rem;
      color: #4a5568;
    }
    
    .logs-table tbody tr {
      transition: background 0.2s ease;
    }
    
    .logs-table tbody tr:hover {
      background: #f8fafc;
    }
    
    .logs-table tbody tr:last-child td {
      border-bottom: none;
    }
    
    .status-active {
      color: #059669;
      font-weight: 600;
      background: #dcfce7;
      padding: 0.25rem 0.75rem;
      border-radius: 6px;
      display: inline-block;
    }
    
    .status-completed {
      color: #1e5bb8;
      font-weight: 600;
    }
    
    .empty-state {
      text-align: center;
      padding: 4rem 2rem;
      color: #9ca3af;
    }
    
    .empty-state h3 {
      font-size: 1.5rem;
      margin-bottom: 0.5rem;
      color: #6b7280;
    }
    
    .empty-state p {
      font-size: 1rem;
    }
    
    @media (max-width: 1024px) {
      .logs-table {
        font-size: 0.85rem;
      }
      
      .logs-table th,
      .logs-table td {
        padding: 0.875rem 1rem;
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
        flex-direction: column;
      }

      .header-right {
        width: 100%;
        flex-direction: column;
      }

      .admin-header input[type="text"] {
        width: 100%;
      }
      
      .admin-content {
        padding: 1.5rem;
      }
      
      .logs-table-container {
        overflow-x: auto;
      }
      
      .logs-table {
        min-width: 800px;
      }
    }
  </style>
</head>
<body>
  <div class="admin-dashboard">
    <aside class="admin-sidebar">
      <h3>SOUTHWOODS<br>MALL</h3>
      <nav>
        <a href="admin-dashboard.php">🏠 Home</a>
        <a href="admin-vehicle-entry.php">🚗 Vehicle Entry</a>
        <a href="admin-vehicle-logs.php" class="active">📋 Vehicle Logs</a>
        <a href="admin-reports.php">📊 Reports</a>
        <a href="admin-analytics.php">📈 Analytics</a>
        <a href="admin-archived.php">📦 Archived</a>
        <a href="admin-account-settings.php">⚙️ Account Settings</a>
        <a href="admin-logout.php">🔒 Log Out</a>
      </nav>
    </aside>
    
    <main class="admin-main">
      <div class="admin-header">
        <h2>Vehicle Logs</h2>
        <div class="header-right">
          <input type="text" placeholder="Search logs..." id="searchInput">
          <button onclick="window.location.href='admin-logout.php'">LOGOUT</button>
        </div>
      </div>
      
      <div class="admin-content">
        <div class="logs-table-container">
          <table class="logs-table" id="logsTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Customer Name</th>
                <th>Plate</th>
                <th>Vehicle</th>
                <th>Entry Time</th>
                <th>Exit Time</th>
                <th>Parking Fee</th>
              </tr>
            </thead>
            <tbody>
              <?php
              if ($result && db_num_rows($result) > 0) {
                  $counter = 1;
                  while($row = db_fetch_assoc($result)) {
                      $customer_name = !empty($row['customer_name']) ? $row['customer_name'] : 'Guest';
                      $plate = !empty($row['plate']) ? $row['plate'] : 'N/A';
                      $vehicle = !empty($row['vehicle']) ? $row['vehicle'] : 'N/A';
                      $entry_time = $row['entry_time'];
                      $exit_time = $row['exit_time'];
                      $parking_fee = $row['parking_fee'] ?? 0;
                      
                      echo "<tr>";
                      echo "<td>" . $counter++ . "</td>";
                      echo "<td>" . htmlspecialchars($customer_name) . "</td>";
                      echo "<td>" . htmlspecialchars($plate) . "</td>";
                      echo "<td>" . htmlspecialchars($vehicle) . "</td>";
                      echo "<td>" . date('M d, Y h:i A', strtotime($entry_time)) . "</td>";
                      
                      if ($exit_time) {
                          echo "<td class='status-completed'>" . date('M d, Y h:i A', strtotime($exit_time)) . "</td>";
                      } else {
                          echo "<td><span class='status-active'>Currently Parked</span></td>";
                      }
                      
                      echo "<td><strong>₱" . number_format($parking_fee, 2) . "</strong></td>";
                      echo "</tr>";
                  }
              } else {
                  echo "<tr><td colspan='7' class='empty-state'><h3>No vehicle logs found</h3><p>Vehicle entry and exit logs will appear here.</p></td></tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
  
  <script>
    document.getElementById('searchInput').addEventListener('input', function() {
      const filter = this.value.toLowerCase();
      const rows = document.querySelectorAll('#logsTable tbody tr');
      rows.forEach(row => {
        const hasEmptyState = row.querySelector('.empty-state');
        if (!hasEmptyState) {
          row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
        }
      });
    });
    
    setInterval(() => {
      location.reload();
    }, 30000);
  </script>
</body>
</html>