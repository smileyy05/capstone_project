<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: admin-login.php");
    exit;
}

// Include database connection
require_once '../DB/DB_connection.php';

$success = '';
$error = '';

// Handle restore action
if (isset($_GET['action']) && $_GET['action'] === 'restore' && isset($_GET['id'])) {
    $customerId = intval($_GET['id']);
    
    $sql = "UPDATE customers SET archived = 0, archived_at = NULL WHERE id = $1";
    $result = db_prepare($sql, [$customerId]);
    
    if ($result && db_affected_rows($result) > 0) {
        $success = "Customer restored successfully!";
    } else {
        $error = "Failed to restore customer!";
    }
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $customerId = intval($_GET['id']);
    
    // Delete customer permanently (CASCADE will handle related records)
    $sql = "DELETE FROM customers WHERE id = $1";
    $result = db_prepare($sql, [$customerId]);
    
    if ($result && db_affected_rows($result) > 0) {
        $success = "Customer deleted permanently!";
    } else {
        $error = "Failed to delete customer!";
    }
}

// Fetch archived customers
$sql = "SELECT * FROM customers WHERE archived = 1 ORDER BY archived_at DESC";
$result = db_query($sql);
$customers = db_fetch_all($result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Archived Customers</title>
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
    
    .admin-header h1 {
      font-size: 1.5rem;
      font-weight: 700;
      letter-spacing: 0.5px;
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
    
    .msg-error {
      background: #fee2e2;
      color: #dc2626;
      padding: 1rem 1.5rem;
      border-radius: 10px;
      margin-bottom: 1.5rem;
      font-weight: 600;
      border-left: 4px solid #dc2626;
    }
    
    .archived-info {
      background: #fef3c7;
      color: #92400e;
      padding: 1rem 1.5rem;
      border-radius: 10px;
      margin-bottom: 1.5rem;
      font-weight: 600;
      border-left: 4px solid #f59e0b;
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
    
    .action-buttons {
      display: flex;
      gap: 0.5rem;
    }
    
    .btn-restore {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: #ffffff;
      border: none;
      border-radius: 8px;
      padding: 0.5rem 1rem;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      letter-spacing: 0.3px;
    }
    
    .btn-restore:hover {
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }
    
    .btn-delete {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: #ffffff;
      border: none;
      border-radius: 8px;
      padding: 0.5rem 1rem;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      letter-spacing: 0.3px;
    }
    
    .btn-delete:hover {
      background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
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
    
    .archived-badge {
      background: #fef3c7;
      color: #92400e;
      padding: 0.3rem 0.7rem;
      border-radius: 6px;
      font-size: 0.8rem;
      font-weight: 600;
      text-transform: uppercase;
    }
    
    /* Modal Styles */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }
    
    .modal-overlay.active {
      display: flex;
    }
    
    .modal-content {
      background: #ffffff;
      border-radius: 16px;
      padding: 2rem;
      max-width: 500px;
      width: 90%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }
    
    .modal-content h3 {
      color: #dc2626;
      font-size: 1.4rem;
      margin-bottom: 1rem;
    }
    
    .modal-content p {
      color: #64748b;
      margin-bottom: 1.5rem;
      line-height: 1.6;
    }
    
    .modal-buttons {
      display: flex;
      gap: 1rem;
    }
    
    .btn-confirm,
    .btn-cancel {
      flex: 1;
      padding: 0.8rem;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    .btn-confirm {
      background: #dc2626;
      color: #fff;
    }
    
    .btn-confirm:hover {
      background: #b91c1c;
    }
    
    .btn-cancel {
      background: #e5e7eb;
      color: #1f2937;
    }
    
    .btn-cancel:hover {
      background: #d1d5db;
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
        <a href="admin-vehicle-entry.php">🚗 Vehicle Entry</a>
        <a href="admin-vehicle-logs.php">📋 Vehicle Logs</a>
        <a href="admin-reports.php">📊 Reports</a>
        <a href="admin-analytics.php">📈 Analytics</a>
        <a href="admin-archived.php" class="active">📦 Archived</a>
        <a href="admin-account-settings.php">⚙️ Account Settings</a>
        <a href="admin-logout.php">🔒 Log Out</a>
      </nav>
    </aside>
    
    <main class="admin-main">
      <div class="admin-header">
        <h1>📦 Archived Customers</h1>
        <div style="display: flex; gap: 1rem; align-items: center;">
          <input type="text" placeholder="Search..." id="searchInput">
          <button onclick="window.location.href='admin-logout.php'">LOGOUT</button>
        </div>
      </div>
      
      <div class="admin-content">
        <?php if($success): ?>
          <div class="msg-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if($error): ?>
          <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="archived-info">
          ℹ️ These customers have been archived. You can restore them to the active list or permanently delete them.
        </div>
        
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
                <th>Archived Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              if ($customers && count($customers) > 0) {
                  $counter = 1;
                  foreach($customers as $row) {
                      $id = $row['id'] ?? '';
                      $name = $row['name'] ?? 'N/A';
                      $email = $row['email'] ?? 'N/A';
                      $plate = $row['plate'] ?? 'N/A';
                      $vehicle_type = $row['vehicle'] ?? 'N/A';
                      $balance = $row['balance'] ?? 0;
                      $archived_at = $row['archived_at'] ?? date('Y-m-d H:i:s');
                      
                      $qr_data = $id . '-' . $plate;
                      
                      echo "<tr>";
                      echo "<td>" . $counter++ . "</td>";
                      echo "<td>" . htmlspecialchars($name) . " <span class='archived-badge'>Archived</span></td>";
                      echo "<td>" . htmlspecialchars($email) . "</td>";
                      echo "<td>" . htmlspecialchars($plate) . "</td>";
                      echo "<td>" . htmlspecialchars($vehicle_type) . "</td>";
                      echo "<td><img src='https://api.qrserver.com/v1/create-qr-code/?size=40x40&data=" . urlencode($qr_data) . "' class='qr-code-small' alt='QR Code'></td>";
                      echo "<td class='balance-positive'>₱" . number_format($balance, 2) . "</td>";
                      echo "<td>" . date('M d, Y h:i A', strtotime($archived_at)) . "</td>";
                      echo "<td>";
                      echo "<div class='action-buttons'>";
                      echo "<button class='btn-restore' onclick='restoreCustomer(" . $id . ", \"" . htmlspecialchars($name) . "\")'>↩️ Restore</button>";
                      echo "<button class='btn-delete' onclick='showDeleteModal(" . $id . ", \"" . htmlspecialchars($name) . "\")'>🗑️ Delete</button>";
                      echo "</div>";
                      echo "</td>";
                      echo "</tr>";
                  }
              } else {
                  echo "<tr><td colspan='9' class='empty-state'><h3>No Archived Customers</h3><p>No customers have been archived yet.</p></td></tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
  
  <!-- Restore Confirmation Modal -->
  <div class="modal-overlay" id="restoreModal">
    <div class="modal-content">
      <h3 style="color: #10b981;">🔄 Restore Customer</h3>
      <p>Restore <strong><span id="restoreCustomerName"></span></strong> back to active customers?</p>
      <div class="modal-buttons">
        <button class="btn-cancel" onclick="closeRestoreModal()">Cancel</button>
        <button class="btn-confirm" style="background: #10b981;" onclick="confirmRestore()">OK</button>
      </div>
    </div>
  </div>
  
  <!-- Delete Confirmation Modal -->
  <div class="modal-overlay" id="deleteModal">
    <div class="modal-content">
      <h3>⚠️ Permanently Delete Customer</h3>
      <p>Are you sure you want to <strong>permanently delete</strong> <span id="customerName"></span>?</p>
      <p style="color: #dc2626; font-weight: 600;">This action cannot be undone! All customer data and history will be lost forever.</p>
      <div class="modal-buttons">
        <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
        <button class="btn-confirm" onclick="confirmDelete()">Delete Permanently</button>
      </div>
    </div>
  </div>
  
  <script>
    let customerToDelete = null;
    
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
    
    // Restore customer
    function restoreCustomer(id, name) {
      showRestoreModal(id, name);
    }
    
    let customerToRestore = null;
    
    // Show restore modal
    function showRestoreModal(id, name) {
      customerToRestore = id;
      document.getElementById('restoreCustomerName').textContent = name;
      document.getElementById('restoreModal').classList.add('active');
    }
    
    // Close restore modal
    function closeRestoreModal() {
      document.getElementById('restoreModal').classList.remove('active');
      customerToRestore = null;
    }
    
    // Confirm restore
    function confirmRestore() {
      if (customerToRestore) {
        window.location.href = 'admin-archived.php?action=restore&id=' + customerToRestore;
      }
    }
    
    // Show delete modal
    function showDeleteModal(id, name) {
      customerToDelete = id;
      document.getElementById('customerName').textContent = name;
      document.getElementById('deleteModal').classList.add('active');
    }
    
    // Close delete modal
    function closeDeleteModal() {
      document.getElementById('deleteModal').classList.remove('active');
      customerToDelete = null;
    }
    
    // Confirm delete
    function confirmDelete() {
      if (customerToDelete) {
        window.location.href = 'admin-archived.php?action=delete&id=' + customerToDelete;
      }
    }
    
    // Close modal when clicking outside
    document.getElementById('deleteModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeDeleteModal();
      }
    });
    
    document.getElementById('restoreModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeRestoreModal();
      }
    });
  </script>
</body>
</html>