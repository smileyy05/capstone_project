<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: admin-login.php");
    exit;
}

// Include PostgreSQL database connection
require_once __DIR__ . '/../DB/DB_connection.php';

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
      justify-content: space-between;
      padding: 1.5rem 2.5rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      gap: 1rem;
      flex-wrap: wrap;
    }
    
    .admin-header h2 {
      font-size: 1.5rem;
      font-weight: 700;
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .admin-header input[type="text"] {
      padding: 0.65rem 1.2rem;
      border-radius: 8px;
      border: none;
      font-size: 0.95rem;
      min-width: 200px;
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
      white-space: nowrap;
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
    
    /* Desktop Table View */
    .logs-table {
      width: 100%;
      border-collapse: collapse;
      display: table;
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
    
    /* Mobile Card View */
    .mobile-card-view {
      display: none;
    }
    
    .log-card {
      background: #ffffff;
      border-radius: 12px;
      padding: 1.25rem;
      margin-bottom: 1rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      border-left: 4px solid #1e5bb8;
    }
    
    .log-card-header {
      display: flex;
      justify-content: space-between;
      align-items: start;
      margin-bottom: 1rem;
      gap: 1rem;
    }
    
    .log-card-number {
      background: #1e5bb8;
      color: #fff;
      border-radius: 6px;
      padding: 0.25rem 0.75rem;
      font-weight: 700;
      font-size: 0.875rem;
    }
    
    .log-card-name {
      font-size: 1.1rem;
      font-weight: 700;
      color: #1a1a1a;
      flex: 1;
    }
    
    .log-card-details {
      display: grid;
      gap: 0.75rem;
    }
    
    .log-card-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.5rem 0;
      border-bottom: 1px solid #f1f5f9;
    }
    
    .log-card-row:last-child {
      border-bottom: none;
    }
    
    .log-card-label {
      font-size: 0.875rem;
      color: #6b7280;
      font-weight: 600;
    }
    
    .log-card-value {
      font-size: 0.9rem;
      color: #1a1a1a;
      font-weight: 500;
      text-align: right;
    }
    
    .log-card-fee {
      font-size: 1.1rem;
      color: #1e5bb8;
      font-weight: 700;
    }
    
    .status-active {
      color: #059669;
      font-weight: 600;
      background: #dcfce7;
      padding: 0.25rem 0.75rem;
      border-radius: 6px;
      display: inline-block;
      font-size: 0.875rem;
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
      
      .logs-table th,
      .logs-table td {
        padding: 0.875rem 1rem;
        font-size: 0.875rem;
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
        flex-direction: column;
        align-items: stretch;
      }
      
      .admin-header h2 {
        font-size: 1.35rem;
        margin-bottom: 0.75rem;
      }

      .header-right {
        width: 100%;
        flex-direction: column;
        gap: 0.75rem;
      }

      .admin-header input[type="text"] {
        width: 100%;
        min-width: unset;
      }
      
      .admin-header button {
        width: 100%;
        padding: 0.65rem 1.5rem;
      }
      
      .admin-content {
        padding: 1.5rem 1rem;
      }
      
      /* Hide desktop table, show mobile cards */
      .logs-table-container {
        display: none;
      }
      
      .mobile-card-view {
        display: block;
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
      
      .admin-header h2 {
        font-size: 1.2rem;
      }
      
      .admin-header button {
        font-size: 0.875rem;
        padding: 0.6rem 1.25rem;
      }
      
      .admin-content {
        padding: 1.25rem 0.875rem;
      }
      
      .log-card {
        padding: 1rem;
      }
      
      .log-card-name {
        font-size: 1rem;
      }
      
      .log-card-label,
      .log-card-value {
        font-size: 0.85rem;
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
      
      .admin-header h2 {
        font-size: 1.1rem;
      }
      
      .log-card {
        padding: 0.875rem;
      }
      
      .log-card-header {
        flex-direction: column;
        align-items: flex-start;
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
        <!-- Desktop Table View -->
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
        
        <!-- Mobile Card View -->
        <div class="mobile-card-view" id="mobileCardView">
          <?php
          // Reset result pointer for mobile view
          if ($result) {
              db_data_seek($result, 0);
          }
          
          if ($result && db_num_rows($result) > 0) {
              $counter = 1;
              while($row = db_fetch_assoc($result)) {
                  $customer_name = !empty($row['customer_name']) ? $row['customer_name'] : 'Guest';
                  $plate = !empty($row['plate']) ? $row['plate'] : 'N/A';
                  $vehicle = !empty($row['vehicle']) ? $row['vehicle'] : 'N/A';
                  $entry_time = $row['entry_time'];
                  $exit_time = $row['exit_time'];
                  $parking_fee = $row['parking_fee'] ?? 0;
                  
                  echo "<div class='log-card'>";
                  echo "<div class='log-card-header'>";
                  echo "<span class='log-card-number'>#" . $counter++ . "</span>";
                  echo "<div class='log-card-name'>" . htmlspecialchars($customer_name) . "</div>";
                  echo "</div>";
                  
                  echo "<div class='log-card-details'>";
                  echo "<div class='log-card-row'>";
                  echo "<span class='log-card-label'>Plate:</span>";
                  echo "<span class='log-card-value'>" . htmlspecialchars($plate) . "</span>";
                  echo "</div>";
                  
                  echo "<div class='log-card-row'>";
                  echo "<span class='log-card-label'>Vehicle:</span>";
                  echo "<span class='log-card-value'>" . htmlspecialchars($vehicle) . "</span>";
                  echo "</div>";
                  
                  echo "<div class='log-card-row'>";
                  echo "<span class='log-card-label'>Entry:</span>";
                  echo "<span class='log-card-value'>" . date('M d, Y h:i A', strtotime($entry_time)) . "</span>";
                  echo "</div>";
                  
                  echo "<div class='log-card-row'>";
                  echo "<span class='log-card-label'>Exit:</span>";
                  if ($exit_time) {
                      echo "<span class='log-card-value status-completed'>" . date('M d, Y h:i A', strtotime($exit_time)) . "</span>";
                  } else {
                      echo "<span class='status-active'>Currently Parked</span>";
                  }
                  echo "</div>";
                  
                  echo "<div class='log-card-row'>";
                  echo "<span class='log-card-label'>Parking Fee:</span>";
                  echo "<span class='log-card-fee'>₱" . number_format($parking_fee, 2) . "</span>";
                  echo "</div>";
                  
                  echo "</div>";
                  echo "</div>";
              }
          } else {
              echo "<div class='empty-state'><h3>No vehicle logs found</h3><p>Vehicle entry and exit logs will appear here.</p></div>";
          }
          ?>
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
    
    // Search functionality for both table and card views
    document.getElementById('searchInput').addEventListener('input', function() {
      const filter = this.value.toLowerCase();
      
      // Search in table view
      const rows = document.querySelectorAll('#logsTable tbody tr');
      rows.forEach(row => {
        const hasEmptyState = row.querySelector('.empty-state');
        if (!hasEmptyState) {
          row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
        }
      });
      
      // Search in mobile card view
      const cards = document.querySelectorAll('.log-card');
      cards.forEach(card => {
        card.style.display = card.innerText.toLowerCase().includes(filter) ? '' : 'none';
      });
    });
    
    // Auto-refresh every 30 seconds
    setInterval(() => {
      location.reload();
    }, 30000);
  </script>
</body>
</html>
