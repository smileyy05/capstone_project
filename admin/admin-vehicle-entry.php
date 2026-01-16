<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: admin-login.php");
    exit;
}

// Include PostgreSQL database connection
require_once '../DB/DB_connection.php';

// Show success message if customer was archived
$success = '';
if (isset($_GET['archived']) && $_GET['archived'] === 'success') {
    $success = 'Customer has been archived successfully!';
}

// Get ONLY non-archived customers (archived = 0 or IS NULL)
$sql = "SELECT * FROM customers WHERE (archived = 0 OR archived IS NULL) ORDER BY id DESC";
$result = db_query($sql);

// Debug information
$debug_info = '';
if ($result) {
    $row_count = db_num_rows($result);
    $debug_info = "Found $row_count active customers in database";
} else {
    $debug_info = "Query failed or returned no result";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Manage Vehicles</title>
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
      justify-content: flex-end;
      padding: 1.5rem 2.5rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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
    
    .msg-success {
      background: #dcfce7;
      color: #16a34a;
      padding: 1rem 1.5rem;
      border-radius: 10px;
      margin-bottom: 1.5rem;
      font-weight: 600;
      border-left: 4px solid #16a34a;
    }
    
    .msg-debug {
      background: #e0f2fe;
      color: #0c4a6e;
      padding: 1rem 1.5rem;
      border-radius: 10px;
      margin-bottom: 1.5rem;
      font-weight: 600;
      border-left: 4px solid #0284c7;
    }
    
    .vehicle-table-container {
      background: #ffffff;
      border-radius: 16px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      overflow: hidden;
    }
    
    .vehicle-table {
      width: 100%;
      border-collapse: collapse;
    }
    
    .vehicle-table thead {
      background: #f8fafc;
      border-bottom: 2px solid #e2e8f0;
    }
    
    .vehicle-table th {
      padding: 1rem 1.5rem;
      text-align: left;
      font-weight: 700;
      font-size: 0.95rem;
      color: #1a1a1a;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .vehicle-table td {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid #f1f5f9;
      font-size: 0.95rem;
      color: #4a5568;
    }
    
    .vehicle-table tbody tr {
      transition: background 0.2s ease;
    }
    
    .vehicle-table tbody tr:hover {
      background: #f8fafc;
    }
    
    .vehicle-table tbody tr:last-child td {
      border-bottom: none;
    }
    
    .balance-positive {
      color: #059669;
      font-weight: 700;
    }
    
    .qr-code-small {
      width: 40px;
      height: 40px;
      object-fit: contain;
    }
    
    .btn-view {
      background: linear-gradient(135deg, #3b82f6 0%, #1e5bb8 100%);
      color: #ffffff;
      border: none;
      border-radius: 8px;
      padding: 0.5rem 1.5rem;
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      letter-spacing: 0.3px;
    }
    
    .btn-view:hover {
      background: linear-gradient(135deg, #1e5bb8 0%, #1e40af 100%);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(30, 91, 184, 0.4);
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
      .vehicle-table {
        font-size: 0.85rem;
      }
      
      .vehicle-table th,
      .vehicle-table td {
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
        flex-wrap: wrap;
      }
      
      .admin-header input[type="text"] {
        width: 100%;
        margin-bottom: 0.5rem;
      }
      
      .admin-content {
        padding: 1.5rem;
      }
      
      .vehicle-table-container {
        overflow-x: auto;
      }
      
      .vehicle-table {
        min-width: 900px;
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
        <a href="admin-vehicle-entry.php" class="active">🚗 Vehicle Entry</a>
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
        <input type="text" placeholder="Search..." id="searchInput">
      </div>
      
      <div class="admin-content">
        <?php if($success): ?>
          <div class="msg-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Debug Information -->
        <div class="msg-debug"><?php echo $debug_info; ?></div>
        
        <div class="vehicle-table-container">
          <table class="vehicle-table" id="vehicleTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Plate</th>
                <th>Vehicle Type</th>
                <th>QR Code</th>
                <th>Balance</th>
                <th>Created At</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              if ($result && db_num_rows($result) > 0) {
                  $counter = 1;
                  while($row = db_fetch_assoc($result)) {
                      // Get available columns dynamically
                      $id = $row['id'] ?? '';
                      $name = $row['name'] ?? 'N/A';
                      $email = $row['email'] ?? 'N/A';
                      $plate = $row['plate'] ?? 'N/A';
                      $vehicle_type = $row['vehicle'] ?? 'N/A';
                      $balance = $row['balance'] ?? 0;
                      $created_at = $row['created_at'] ?? date('Y-m-d H:i:s');
                      
                      $qr_data = $id . '-' . $plate;
                      
                      echo "<tr>";
                      echo "<td>" . $counter++ . "</td>";
                      echo "<td>" . htmlspecialchars($name) . "</td>";
                      echo "<td>" . htmlspecialchars($email) . "</td>";
                      echo "<td>" . htmlspecialchars($plate) . "</td>";
                      echo "<td>" . htmlspecialchars($vehicle_type) . "</td>";
                      echo "<td><img src='https://api.qrserver.com/v1/create-qr-code/?size=40x40&data=" . urlencode($qr_data) . "' class='qr-code-small' alt='QR Code'></td>";
                      echo "<td class='balance-positive'>₱" . number_format($balance, 2) . "</td>";
                      echo "<td>" . date('Y-m-d H:i:s', strtotime($created_at)) . "</td>";
                      echo "<td><button class='btn-view' onclick='viewCustomer(" . $id . ")'>View</button></td>";
                      echo "</tr>";
                  }
              } else {
                  echo "<tr><td colspan='9' class='empty-state'><h3>No Active Customers</h3><p>No active customers found. Archived customers can be viewed in the Archived section.</p></td></tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
  
  <script>
    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function() {
      const filter = this.value.toLowerCase();
      const rows = document.querySelectorAll('#vehicleTable tbody tr');
      rows.forEach(row => {
        const hasEmptyState = row.querySelector('.empty-state');
        if (!hasEmptyState) {
          row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
        }
      });
    });
    
    // View customer details
    function viewCustomer(id) {
      window.location.href = 'admin-view-user.php?id=' + id;
    }
  </script>
</body>
</html>
