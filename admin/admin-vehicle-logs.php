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
   /* ================= RESET ================= */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

/* ================= BODY ================= */
body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
  background: #f3f4f6;
  overflow-x: hidden;
}

/* ================= LAYOUT ================= */
.admin-dashboard {
  display: flex;
  min-height: 100vh;
}

.admin-sidebar {
  width: 240px;
  background: linear-gradient(180deg, #1e5bb8, #1651c6);
  color: #fff;
  padding: 2rem 1.2rem;
  position: fixed;
  height: 100vh;
  overflow-y: auto;
  transition: transform .3s ease;
  z-index: 100;
}

.admin-main {
  margin-left: 240px;
  width: calc(100% - 240px);
}

/* ================= HEADER ================= */
.admin-header {
  background: linear-gradient(90deg, #1e5bb8, #1651c6);
  color: #fff;
  padding: 1.5rem 2rem;
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  justify-content: space-between;
  align-items: center;
}

/* ================= TABLE ================= */
.logs-table-container {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 4px 12px rgba(0,0,0,.08);
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.logs-table {
  width: 100%;
  min-width: 900px;
  border-collapse: collapse;
}

.logs-table th,
.logs-table td {
  padding: 1rem;
  font-size: .95rem;
}

/* ================= MOBILE CARDS ================= */
.mobile-card-view {
  display: none;
}

.log-card {
  background: #fff;
  border-radius: 12px;
  padding: 1rem;
  margin-bottom: 1rem;
  box-shadow: 0 2px 8px rgba(0,0,0,.08);
}

/* ================= MOBILE MENU ================= */
.mobile-menu-toggle {
  display: none;
  position: fixed;
  top: 1rem;
  left: 1rem;
  z-index: 101;
  font-size: 1.5rem;
  background: #1e5bb8;
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: .5rem .75rem;
}

/* ================= BREAKPOINTS ================= */

/* Tablets */
@media (max-width: 1024px) {
  .admin-sidebar { width: 200px; }
  .admin-main { margin-left: 200px; width: calc(100% - 200px); }
}

/* Small Tablets */
@media (max-width: 900px) {
  .logs-table th,
  .logs-table td {
    font-size: .85rem;
    padding: .75rem;
  }
}

/* Mobile */
@media (max-width: 767px) {
  .mobile-menu-toggle { display: block; }

  .admin-sidebar {
    transform: translateX(-100%);
    width: 280px;
  }

  .admin-sidebar.active {
    transform: translateX(0);
  }

  .admin-main {
    margin-left: 0;
    width: 100%;
  }

  .logs-table-container {
    display: none;
  }

  .mobile-card-view {
    display: block;
  }
}

/* Small Mobile */
@media (max-width: 480px) {
  .admin-header {
    padding-left: 4rem;
  }

  .log-card {
    padding: .9rem;
  }
}

/* Large Screens */
@media (min-width: 1600px) {
  .admin-content {
    max-width: 1400px;
    margin: auto;
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

