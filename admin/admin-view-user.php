<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: admin-login.php");
    exit;
}

// Include PostgreSQL database connection
require_once __DIR__ . '/../DB/DB_connection.php';

$userId = $_GET['id'] ?? null;
if (!$userId) {
    header("Location: admin-vehicle-entry.php");
    exit;
}

$success = '';
$error = '';

// Handle balance reload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reload_balance'])) {
    $reloadAmount = $_POST['reload_amount'] ?? 0;
    
    if ($reloadAmount > 0) {
        $result = db_prepare("UPDATE customers SET balance = balance + $1 WHERE id = $2", [$reloadAmount, $userId]);
        if ($result) {
            $success = "Balance reloaded successfully! Added ₱" . number_format($reloadAmount, 2);
        } else {
            $error = "Failed to reload balance!";
        }
    } else {
        $error = "Please enter a valid amount!";
    }
}

// Handle archive action
if (isset($_GET['action']) && $_GET['action'] === 'archive') {
    $result = db_prepare("UPDATE customers SET archived = true, archived_at = NOW() WHERE id = $1", [$userId]);
    if ($result) {
        header("Location: admin-vehicle-entry.php?archived=success");
        exit;
    } else {
        $error = "Failed to archive customer!";
    }
}

// Fetch user details
$result = db_prepare("SELECT * FROM customers WHERE id = $1", [$userId]);
$user = db_fetch_assoc($result);

if (!$user) {
    header("Location: admin-vehicle-entry.php");
    exit;
}

// Fetch user's parking history
$historyResult = db_prepare("SELECT * FROM parking_logs WHERE customer_id = $1 ORDER BY entry_time DESC LIMIT 10", [$userId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View User Details</title>
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
    
    .admin-header .back-btn {
      background: transparent;
      border: 2px solid #fff;
      color: #fff;
      border-radius: 8px;
      padding: 0.6rem 1.5rem;
      font-size: 0.95rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      letter-spacing: 0.5px;
      text-decoration: none;
      display: inline-block;
    }
    
    .admin-header .back-btn:hover {
      background: #fff;
      color: #1e5bb8;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
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
    
    .user-details-container {
      background: #ffffff;
      border-radius: 16px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      padding: 2.5rem;
      margin-bottom: 2rem;
    }
    
    .user-header {
      display: flex;
      align-items: center;
      gap: 2rem;
      margin-bottom: 2rem;
      padding-bottom: 2rem;
      border-bottom: 2px solid #f1f5f9;
    }
    
    .user-qr-code {
      width: 150px;
      height: 150px;
      object-fit: contain;
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      padding: 0.5rem;
    }
    
    .user-basic-info {
      flex: 1;
    }
    
    .user-basic-info h2 {
      color: #1e5bb8;
      font-size: 1.8rem;
      margin-bottom: 0.5rem;
    }
    
    .user-basic-info p {
      color: #64748b;
      font-size: 1rem;
      margin-bottom: 0.3rem;
    }
    
    .user-details-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 1.5rem;
      margin-top: 2rem;
    }
    
    .detail-item {
      background: #f8fafc;
      padding: 1.5rem;
      border-radius: 12px;
      border-left: 4px solid #1e5bb8;
    }
    
    .detail-item label {
      display: block;
      font-size: 0.85rem;
      font-weight: 700;
      color: #475569;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 0.5rem;
    }
    
    .detail-item .value {
      font-size: 1.1rem;
      color: #1a1a1a;
      font-weight: 600;
    }
    
    .balance-value {
      color: #059669;
      font-size: 1.5rem;
      font-weight: 700;
    }
    
    .reload-section {
      background: #f0f9ff;
      border: 2px solid #3b82f6;
      border-radius: 12px;
      padding: 2rem;
      margin-top: 2rem;
    }
    
    .reload-section h3 {
      color: #1e5bb8;
      font-size: 1.2rem;
      margin-bottom: 1rem;
    }
    
    .reload-form {
      display: flex;
      gap: 1rem;
      align-items: flex-end;
      flex-wrap: wrap;
    }
    
    .form-group {
      flex: 1;
      min-width: 200px;
    }
    
    .form-group label {
      display: block;
      font-size: 0.9rem;
      font-weight: 600;
      color: #475569;
      margin-bottom: 0.5rem;
    }
    
    .form-group input {
      width: 100%;
      padding: 0.8rem;
      border: 2px solid #cbd5e1;
      border-radius: 8px;
      font-size: 1rem;
      transition: all 0.3s ease;
    }
    
    .form-group input:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .btn-reload {
      padding: 0.8rem 2rem;
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      letter-spacing: 0.5px;
    }
    
    .btn-reload:hover {
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }
    
    .action-buttons {
      display: flex;
      gap: 1rem;
      margin-top: 2rem;
    }
    
    .btn-archive {
      flex: 1;
      padding: 0.8rem 2rem;
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      color: #ffffff;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      letter-spacing: 0.5px;
    }
    
    .btn-archive:hover {
      background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
    }
    
    .history-section {
      background: #ffffff;
      border-radius: 16px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      padding: 2.5rem;
    }
    
    .history-section h3 {
      color: #1e5bb8;
      font-size: 1.4rem;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid #f1f5f9;
    }
    
    .history-table {
      width: 100%;
      border-collapse: collapse;
    }
    
    .history-table thead {
      background: #f8fafc;
      border-bottom: 2px solid #e2e8f0;
    }
    
    .history-table th {
      padding: 1rem 1.5rem;
      text-align: left;
      font-weight: 700;
      font-size: 0.9rem;
      color: #1a1a1a;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .history-table td {
      padding: 1rem 1.5rem;
      border-bottom: 1px solid #f1f5f9;
      font-size: 0.95rem;
      color: #4a5568;
    }
    
    .history-table tbody tr:hover {
      background: #f8fafc;
    }
    
    .status-badge {
      padding: 0.4rem 0.8rem;
      border-radius: 6px;
      font-size: 0.85rem;
      font-weight: 600;
      text-transform: uppercase;
    }
    
    .status-active {
      background: #dcfce7;
      color: #166534;
    }
    
    .status-completed {
      background: #dbeafe;
      color: #1e40af;
    }
    
    .empty-history {
      text-align: center;
      padding: 3rem;
      color: #9ca3af;
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
    
    @media (max-width: 768px) {
      .user-header {
        flex-direction: column;
        text-align: center;
      }
      
      .user-details-grid {
        grid-template-columns: 1fr;
      }
      
      .reload-form {
        flex-direction: column;
      }
      
      .form-group {
        width: 100%;
      }
      
      .action-buttons {
        flex-direction: column;
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
        <a href="admin-archived.php">📦 Archived</a>
        <a href="admin-account-settings.php">⚙️ Account Settings</a>
        <a href="admin-logout.php">🔒 Log Out</a>
      </nav>
    </aside>
    
    <main class="admin-main">
      <div class="admin-header">
        <a href="admin-vehicle-entry.php" class="back-btn">← Back to List</a>
        <button onclick="window.location.href='admin-logout.php'">LOGOUT</button>
      </div>
      
      <div class="admin-content">
        <?php if($success): ?>
          <div class="msg-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
          <div class="msg-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="user-details-container">
          <div class="user-header">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($user['id'] . '-' . $user['plate']); ?>" 
                 class="user-qr-code" 
                 alt="User QR Code">
            <div class="user-basic-info">
              <h2><?php echo htmlspecialchars($user['name']); ?></h2>
              <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
              <p><strong>User ID:</strong> #<?php echo htmlspecialchars($user['id']); ?></p>
              <p><strong>Member Since:</strong> <?php echo date('F d, Y', strtotime($user['created_at'])); ?></p>
            </div>
          </div>
          
          <div class="user-details-grid">
            <div class="detail-item">
              <label>Plate Number</label>
              <div class="value"><?php echo htmlspecialchars($user['plate']); ?></div>
            </div>
            
            <div class="detail-item">
              <label>Vehicle Type</label>
              <div class="value"><?php echo htmlspecialchars($user['vehicle'] ?? 'N/A'); ?></div>
            </div>
            
            <div class="detail-item">
              <label>Account Balance</label>
              <div class="balance-value">₱<?php echo number_format($user['balance'], 2); ?></div>
            </div>
            
            <div class="detail-item">
              <label>Contact Number</label>
              <div class="value"><?php echo htmlspecialchars($user['contact'] ?? 'N/A'); ?></div>
            </div>
          </div>
          
          <div class="reload-section">
            <h3>💳 Reload Customer Balance</h3>
            <form method="POST" class="reload-form">
              <div class="form-group">
                <label for="reload_amount">Amount to Reload</label>
                <input type="number" id="reload_amount" name="reload_amount" 
                       placeholder="Enter amount (e.g., 100)" 
                       min="1" step="0.01" required>
              </div>
              <button type="submit" name="reload_balance" class="btn-reload">Reload Balance</button>
            </form>
          </div>
          
          <div class="action-buttons">
            <button class="btn-archive" onclick="showArchiveModal()">📦 Archive Customer</button>
          </div>
        </div>
        
        <div class="history-section">
          <h3>Recent Parking History</h3>
          <?php if ($historyResult && db_num_rows($historyResult) > 0): ?>
          <table class="history-table">
            <thead>
              <tr>
                <th>Entry Time</th>
                <th>Exit Time</th>
                <th>Duration</th>
                <th>Fee</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php while($log = db_fetch_assoc($historyResult)): ?>
              <tr>
                <td><?php echo date('M d, Y h:i A', strtotime($log['entry_time'])); ?></td>
                <td>
                  <?php 
                  echo $log['exit_time'] 
                    ? date('M d, Y h:i A', strtotime($log['exit_time'])) 
                    : '<span class="status-badge status-active">Currently Parked</span>'; 
                  ?>
                </td>
                <td><?php echo $log['duration'] ?? 'N/A'; ?></td>
                <td>₱<?php echo number_format($log['parking_fee'] ?? 0, 2); ?></td>
                <td>
                  <?php if ($log['exit_time']): ?>
                    <span class="status-badge status-completed">Completed</span>
                  <?php else: ?>
                    <span class="status-badge status-active">Active</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          <?php else: ?>
          <div class="empty-history">
            <p>No parking history found for this customer.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>
  
  <!-- Archive Confirmation Modal -->
  <div class="modal-overlay" id="archiveModal">
    <div class="modal-content">
      <h3>⚠️ Archive Customer</h3>
      <p>Are you sure you want to archive <strong><?php echo htmlspecialchars($user['name']); ?></strong>?</p>
      <p>This customer will be moved to the archived section and won't appear in the main list.</p>
      <div class="modal-buttons">
        <button class="btn-cancel" onclick="closeArchiveModal()">Cancel</button>
        <button class="btn-confirm" onclick="archiveCustomer()">Archive</button>
      </div>
    </div>
  </div>
  
  <script>
    function showArchiveModal() {
      document.getElementById('archiveModal').classList.add('active');
    }
    
    function closeArchiveModal() {
      document.getElementById('archiveModal').classList.remove('active');
    }
    
    function archiveCustomer() {
      window.location.href = 'admin-view-user.php?id=<?php echo $userId; ?>&action=archive';
    }
    
    // Close modal when clicking outside
    document.getElementById('archiveModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeArchiveModal();
      }
    });
  </script>
</body>

</html>
