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
$currentError = '';
$newError = '';
$reError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPass = $_POST['currentPass'] ?? '';
    $newPass = $_POST['newPass'] ?? '';
    $rePass = $_POST['rePass'] ?? '';
    $email = $_SESSION['admin'];

    // Validate inputs
    if (!$currentPass) $currentError = "Current Password is Required!";
    if (!$newPass) $newError = "New Password is Required!";
    if (!$rePass) $reError = "Re-enter Password is Required!";

    if ($currentError || $newError || $reError) {
        $error = "All fields are required!";
    } elseif ($newPass !== $rePass) {
        $reError = "Passwords do not match!";
        $error = "New passwords do not match!";
    } else {
        // Check if connection exists
        if (!$conn) {
            $error = "Database connection failed";
        } else {
            // Verify current password using prepared statement
            $sql = "SELECT * FROM admins WHERE email = $1 AND password = $2";
            $result = db_prepare($sql, [$email, $currentPass]);
            
            if (!$result || db_num_rows($result) === 0) {
                $currentError = "Current password is incorrect!";
                $error = "Current password is incorrect!";
            } else {
                // Update password using prepared statement
                $update_sql = "UPDATE admins SET password = $1 WHERE email = $2";
                $update_result = db_prepare($update_sql, [$newPass, $email]);
                
                if ($update_result && db_affected_rows($update_result) > 0) {
                    $success = "Your Password Has Been Updated!";
                } else {
                    $error = "Failed to update password!";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin - Account Settings</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      background: #f5f5f5;
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
    }
    
    .admin-dashboard {
      display: flex;
      min-height: 100vh;
    }
    
    .admin-sidebar {
      background: #2563eb;
      color: #fff;
      width: 240px;
      padding: 2rem 1rem;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
    }
    
    .admin-sidebar h3 {
      font-size: 1.3rem;
      margin-bottom: 3rem;
      color: #5eead4;
      letter-spacing: 0.5px;
      line-height: 1.3;
      font-weight: 700;
    }
    
    .admin-sidebar nav {
      display: flex;
      flex-direction: column;
      gap: 0.3rem;
      width: 100%;
    }
    
    .admin-sidebar a {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      background: none;
      color: #fff;
      text-decoration: none;
      font-size: 0.95rem;
      padding: 0.75rem 1rem;
      border-radius: 8px;
      transition: all 0.2s ease;
      font-weight: 500;
    }
    
    .admin-sidebar a.active {
      background: #ffffff;
      color: #2563eb;
      font-weight: 600;
    }
    
    .admin-sidebar a:hover:not(.active) {
      background: rgba(255, 255, 255, 0.1);
    }
    
    .admin-main {
      flex: 1;
      background: #f5f5f5;
      min-height: 100vh;
    }
    
    .admin-header {
      background: #2563eb;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.2rem 2rem;
      gap: 1rem;
    }
    
    .admin-header h1 {
      font-size: 1.3rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .header-right {
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    
    .admin-header input[type="text"] {
      padding: 0.6rem 1rem;
      border-radius: 6px;
      border: none;
      font-size: 0.9rem;
      width: 250px;
      background: #ffffff;
    }
    
    .admin-header input[type="text"]:focus {
      outline: none;
      box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
    }
    
    .admin-header button {
      background: transparent;
      border: 2px solid #fff;
      color: #fff;
      border-radius: 6px;
      padding: 0.5rem 1.5rem;
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .admin-header button:hover {
      background: #fff;
      color: #2563eb;
    }
    
    .admin-content {
      padding: 2rem;
    }
    
    .settings-card {
      background: #ffffff;
      border-radius: 8px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      padding: 2rem;
      max-width: 600px;
      margin: 0 auto;
    }
    
    .settings-card h2 {
      color: #1f2937;
      font-size: 1.4rem;
      margin-bottom: 1.5rem;
      font-weight: 600;
    }
    
    .msg-success {
      background: #dcfce7;
      color: #16a34a;
      padding: 1rem;
      border-radius: 6px;
      margin-bottom: 1.5rem;
      font-weight: 600;
      border-left: 4px solid #16a34a;
    }
    
    .msg-error {
      background: #fee2e2;
      color: #dc2626;
      padding: 1rem;
      border-radius: 6px;
      margin-bottom: 1.5rem;
      font-weight: 600;
      border-left: 4px solid #dc2626;
    }
    
    .form-group {
      margin-bottom: 1.5rem;
    }
    
    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 0.5rem;
      color: #374151;
      font-size: 0.95rem;
    }
    
    .form-group input[type="password"] {
      width: 100%;
      padding: 0.75rem;
      border-radius: 6px;
      border: 1px solid #d1d5db;
      font-size: 1rem;
      transition: all 0.2s ease;
    }
    
    .form-group input[type="password"]:focus {
      outline: none;
      border-color: #2563eb;
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
    
    .field-error {
      color: #dc2626;
      font-size: 0.875rem;
      margin-top: 0.3rem;
      font-weight: 500;
      display: block;
    }
    
    .btn-update {
      width: 100%;
      background: #2563eb;
      color: #fff;
      border: none;
      border-radius: 6px;
      padding: 0.85rem;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      margin-top: 1rem;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      transition: background 0.2s ease;
    }
    
    .btn-update:hover {
      background: #1d4ed8;
    }
    
    /* Modal for success */
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
      border-radius: 12px;
      padding: 2rem;
      min-width: 350px;
      max-width: 90%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      text-align: center;
    }
    
    .modal-content h3 {
      color: #16a34a;
      font-size: 1.4rem;
      margin-bottom: 0.5rem;
      font-weight: 700;
    }
    
    .modal-content p {
      color: #6b7280;
      margin-bottom: 1.5rem;
      font-size: 1rem;
    }
    
    .btn-ok {
      background: #2563eb;
      color: #fff;
      border: none;
      border-radius: 6px;
      padding: 0.7rem 2rem;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s ease;
    }
    
    .btn-ok:hover {
      background: #1d4ed8;
    }
    
    @media (max-width: 768px) {
      .admin-sidebar {
        width: 70px;
        padding: 1.5rem 0.5rem;
      }
      
      .admin-sidebar h3 {
        font-size: 0.7rem;
        text-align: center;
        width: 100%;
      }
      
      .admin-sidebar nav a {
        font-size: 0.8rem;
        padding: 0.6rem;
        justify-content: center;
      }
      
      .admin-header {
        flex-direction: column;
        padding: 1rem;
      }
      
      .header-right {
        width: 100%;
        justify-content: space-between;
      }
      
      .admin-header input[type="text"] {
        width: 100%;
        margin-bottom: 0.5rem;
      }
      
      .admin-content {
        padding: 1rem;
      }
      
      .settings-card {
        padding: 1.5rem;
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
        <a href="admin-archived.php">📦 Archived</a>
        <a href="admin-account-settings.php" class="active">⚙️ Account Settings</a>
        <a href="admin-logout.php">🔒 Log Out</a>
      </nav>
    </aside>
    
    <main class="admin-main">
      <div class="admin-header">
        <h1>⚙️ Account Settings</h1>
        <div class="header-right">
          <input type="text" placeholder="Search..." id="searchInput">
          <button onclick="window.location.href='admin-logout.php'">LOGOUT</button>
        </div>
      </div>
      
      <div class="admin-content">
        <div class="settings-card">
          <h2>Change Your Password</h2>
          
          <?php if($success): ?>
            <div class="msg-success">✓ <?php echo htmlspecialchars($success); ?></div>
          <?php elseif($error && !$currentError && !$newError && !$reError): ?>
            <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>
          
          <form method="POST" autocomplete="off">
            <div class="form-group">
              <label for="currentPass">Current Password*</label>
              <input type="password" id="currentPass" name="currentPass" placeholder="Enter your Current Password" required>
              <?php if($currentError): ?><span class="field-error"><?php echo htmlspecialchars($currentError); ?></span><?php endif; ?>
            </div>
            
            <div class="form-group">
              <label for="newPass">New Password*</label>
              <input type="password" id="newPass" name="newPass" placeholder="Enter your New Password" required>
              <?php if($newError): ?><span class="field-error"><?php echo htmlspecialchars($newError); ?></span><?php endif; ?>
            </div>
            
            <div class="form-group">
              <label for="rePass">Re-enter Password*</label>
              <input type="password" id="rePass" name="rePass" placeholder="Re-enter Password" required>
              <?php if($reError): ?><span class="field-error"><?php echo htmlspecialchars($reError); ?></span><?php endif; ?>
            </div>
            
            <button type="submit" class="btn-update">Update Password</button>
          </form>
        </div>
      </div>
    </main>
  </div>
  
  <!-- Success Modal -->
  <?php if($success): ?>
  <div class="modal-overlay active" id="successModal">
    <div class="modal-content">
      <h3>✓ Successfully Changed!</h3>
      <p>Your Password Has Been Updated!</p>
      <button class="btn-ok" onclick="window.location.href='admin-account-settings.php'">OK</button>
    </div>
  </div>
  <?php endif; ?>
  
  <script>
    // Close modal
    function closeModal() {
      document.getElementById('successModal')?.classList.remove('active');
    }
    
    // Close modal when clicking outside
    document.getElementById('successModal')?.addEventListener('click', function(e) {
      if (e.target === this) {
        window.location.href = 'admin-account-settings.php';
      }
    });
  </script>
</body>
</html>