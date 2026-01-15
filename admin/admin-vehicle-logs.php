<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: admin-login.php");
    exit;
}

// Include PostgreSQL database connection
require_once __DIR__ . '/../DB/DB_connection.php';

// Check if parking_logs table exists, if not create it
$table_check = pg_query($conn, "SELECT EXISTS (
    SELECT FROM pg_tables 
    WHERE schemaname = 'public' 
    AND tablename = 'parking_logs'
)");
$exists = pg_fetch_result($table_check, 0, 0);

if ($exists === 'f') {
    $create_table = "CREATE TABLE parking_logs (
        id SERIAL PRIMARY KEY,
        customer_id INTEGER NULL,
        customer_name VARCHAR(100) NULL,
        plate VARCHAR(20) NULL,
        vehicle VARCHAR(50) NULL,
        entry_time TIMESTAMP,
        exit_time TIMESTAMP NULL,
        parking_fee DECIMAL(10,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id)
    )";
    pg_query($conn, $create_table);
}

// Fetch parking logs with JOIN to get customer info if available
$sql = "SELECT 
    pl.id,
    pl.customer_id,
    COALESCE(c.name, pl.customer_name) AS customer_name,
    COALESCE(c.plate, pl.plate) AS plate,
    COALESCE(c.vehicle, pl.vehicle) AS vehicle,
    pl.entry_time,
    pl.exit_time,
    pl.parking_fee
FROM parking_logs pl
LEFT JOIN customers c ON pl.customer_id = c.id
ORDER BY pl.entry_time DESC";

$result = pg_query($conn, $sql);

// Calculate statistics
$active_result = pg_query($conn, "SELECT COUNT(*) as count FROM parking_logs WHERE exit_time IS NULL");
$active_count = pg_fetch_assoc($active_result)['count'];

$completed_result = pg_query($conn, "SELECT COUNT(*) as count FROM parking_logs WHERE DATE(exit_time) = CURRENT_DATE");
$completed_today = pg_fetch_assoc($completed_result)['count'];

$revenue_result = pg_query($conn, "SELECT COALESCE(SUM(parking_fee), 0) as total FROM parking_logs WHERE DATE(exit_time) = CURRENT_DATE");
$revenue_today = pg_fetch_assoc($revenue_result)['total'];
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
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: #f5f5f5;
  overflow-x: hidden;
}

/* Dashboard Layout */
.admin-dashboard {
  display: flex;
  min-height: 100vh;
}

/* Sidebar Styles */
.admin-sidebar {
  width: 250px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 2rem 1rem;
  position: fixed;
  height: 100vh;
  overflow-y: auto;
  z-index: 1000;
  transition: transform 0.3s ease;
}

.admin-sidebar h3 {
  font-size: 1.5rem;
  margin-bottom: 2rem;
  text-align: center;
  font-weight: bold;
  letter-spacing: 1px;
}

.admin-sidebar nav {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.admin-sidebar nav a {
  color: white;
  text-decoration: none;
  padding: 0.875rem 1rem;
  border-radius: 8px;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.95rem;
}

.admin-sidebar nav a:hover {
  background: rgba(255, 255, 255, 0.1);
  transform: translateX(5px);
}

.admin-sidebar nav a.active {
  background: rgba(255, 255, 255, 0.2);
  font-weight: bold;
}

/* Mobile Menu Toggle */
.mobile-menu-toggle {
  display: none;
  position: fixed;
  top: 1rem;
  left: 1rem;
  z-index: 1001;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border: none;
  border-radius: 8px;
  width: 45px;
  height: 45px;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 5px;
  cursor: pointer;
  padding: 10px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.mobile-menu-toggle span {
  display: block;
  width: 25px;
  height: 3px;
  background: white;
  border-radius: 3px;
  transition: all 0.3s;
}

.mobile-menu-toggle.active span:nth-child(1) {
  transform: rotate(45deg) translate(7px, 7px);
}

.mobile-menu-toggle.active span:nth-child(2) {
  opacity: 0;
}

.mobile-menu-toggle.active span:nth-child(3) {
  transform: rotate(-45deg) translate(7px, -7px);
}

/* Main Content Area */
.admin-main {
  margin-left: 250px;
  flex: 1;
  padding: 2rem;
  width: calc(100% - 250px);
}

/* Header Styles */
.admin-header {
  background: white;
  padding: 1.5rem 2rem;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.admin-header h2 {
  color: #333;
  font-size: 1.75rem;
}

.header-right {
  display: flex;
  gap: 1rem;
  align-items: center;
  flex-wrap: wrap;
}

.header-right input {
  padding: 0.625rem 1rem;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  font-size: 0.95rem;
  width: 250px;
  transition: border-color 0.3s;
}

.header-right input:focus {
  outline: none;
  border-color: #667eea;
}

.header-right button,
.logout-btn {
  padding: 0.625rem 1.5rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: bold;
  transition: transform 0.2s, box-shadow 0.2s;
  font-size: 0.9rem;
  white-space: nowrap;
}

.header-right button:hover,
.logout-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

/* Table Container */
.logs-table-container {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.table-wrapper {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

/* Table Styles */
.logs-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 800px;
}

.logs-table thead {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.logs-table thead th {
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  font-size: 0.95rem;
  letter-spacing: 0.5px;
  white-space: nowrap;
}

.logs-table tbody tr {
  border-bottom: 1px solid #f0f0f0;
  transition: background-color 0.2s;
}

.logs-table tbody tr:hover {
  background: #f8f9ff;
}

.logs-table tbody tr:last-child {
  border-bottom: none;
}

.logs-table tbody td {
  padding: 1rem;
  color: #333;
  font-size: 0.95rem;
}

/* Status Badges */
.status-completed {
  color: #10b981;
  font-weight: 500;
}

.status-active {
  display: inline-block;
  padding: 0.375rem 0.875rem;
  background: #fef3c7;
  color: #d97706;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 600;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 4rem 2rem !important;
}

.empty-state h3 {
  color: #666;
  margin-bottom: 0.5rem;
  font-size: 1.25rem;
}

.empty-state p {
  color: #999;
  font-size: 0.95rem;
}

/* Scrollbar Styling */
.table-wrapper::-webkit-scrollbar {
  height: 8px;
}

.table-wrapper::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

.table-wrapper::-webkit-scrollbar-thumb {
  background: #667eea;
  border-radius: 4px;
}

.table-wrapper::-webkit-scrollbar-thumb:hover {
  background: #764ba2;
}

/* Tablet Styles (768px - 1024px) */
@media (max-width: 1024px) {
  .admin-sidebar {
    width: 220px;
  }

  .admin-main {
    margin-left: 220px;
    width: calc(100% - 220px);
  }

  .admin-header h2 {
    font-size: 1.5rem;
  }

  .logs-table {
    min-width: 700px;
  }
}

/* Mobile Styles (max-width: 768px) */
@media (max-width: 768px) {
  .mobile-menu-toggle {
    display: flex;
  }

  .admin-sidebar {
    transform: translateX(-100%);
  }

  .admin-sidebar.active {
    transform: translateX(0);
  }

  .admin-main {
    margin-left: 0;
    width: 100%;
    padding: 1rem;
    padding-top: 4rem;
  }

  .admin-header {
    padding: 1rem;
    flex-direction: column;
    align-items: stretch;
  }

  .admin-header h2 {
    font-size: 1.5rem;
    text-align: center;
  }

  .header-right {
    flex-direction: column;
    width: 100%;
  }

  .header-right input {
    width: 100%;
  }

  .header-right button,
  .logout-btn {
    width: 100%;
  }

  .logs-table {
    min-width: 650px;
  }

  .logs-table thead th,
  .logs-table tbody td {
    padding: 0.75rem 0.5rem;
    font-size: 0.85rem;
  }
}

/* Small Mobile Styles (max-width: 480px) */
@media (max-width: 480px) {
  .admin-main {
    padding: 0.75rem;
    padding-top: 4rem;
  }

  .admin-header {
    padding: 0.875rem;
  }

  .admin-header h2 {
    font-size: 1.25rem;
  }

  .admin-sidebar h3 {
    font-size: 1.25rem;
  }

  .admin-sidebar nav a {
    padding: 0.75rem 0.875rem;
    font-size: 0.9rem;
  }

  .logs-table {
    min-width: 100%;
    display: block;
  }

  .logs-table thead {
    display: none;
  }

  .logs-table tbody {
    display: block;
  }

  .logs-table tbody tr {
    display: block;
    margin-bottom: 1rem;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 0.75rem;
    background: white;
  }

  .logs-table tbody tr:hover {
    background: #f8f9ff;
  }

  .logs-table tbody td {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.625rem 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.9rem;
  }

  .logs-table tbody td:last-child {
    border-bottom: none;
  }

  .logs-table tbody td::before {
    content: attr(data-label);
    font-weight: 600;
    color: #667eea;
    flex-shrink: 0;
    margin-right: 1rem;
  }

  .status-active {
    font-size: 0.8rem;
    padding: 0.3rem 0.7rem;
  }

  .empty-state {
    padding: 2rem 1rem !important;
  }

  .empty-state h3 {
    font-size: 1.1rem;
  }

  .empty-state p {
    font-size: 0.85rem;
  }
}

/* Extra Small Mobile (max-width: 360px) */
@media (max-width: 360px) {
  .admin-main {
    padding: 0.5rem;
    padding-top: 3.5rem;
  }

  .admin-header {
    padding: 0.75rem;
  }

  .admin-header h2 {
    font-size: 1.1rem;
  }

  .mobile-menu-toggle {
    width: 40px;
    height: 40px;
  }

  .mobile-menu-toggle span {
    width: 22px;
  }

  .logs-table tbody td {
    font-size: 0.85rem;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
  }

  .logs-table tbody td::before {
    margin-right: 0;
  }
}

/* Landscape Mobile Orientation */
@media (max-width: 768px) and (orientation: landscape) {
  .admin-sidebar {
    width: 200px;
  }

  .admin-main {
    padding-top: 1rem;
  }

  .mobile-menu-toggle {
    top: 0.5rem;
    left: 0.5rem;
  }
}

/* Print Styles */
@media print {
  .admin-sidebar,
  .mobile-menu-toggle,
  .header-right button,
  .logout-btn {
    display: none !important;
  }

  .admin-main {
    margin-left: 0;
    width: 100%;
  }

  .logs-table {
    border: 1px solid #ddd;
  }

  .logs-table thead {
    background: #667eea !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
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
        </div>
      </div>
      
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
            if ($result && pg_num_rows($result) > 0) {
                $counter = 1;
                while($row = pg_fetch_assoc($result)) {
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
    
    // Auto-refresh every 30 seconds
    setInterval(() => {
      location.reload();
    }, 30000);
  </script>
</body>
</html>
<?php
pg_close($conn);
?>

