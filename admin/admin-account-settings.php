<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: admin-login.php");
    exit;
}

// Include database connection
require_once __DIR__ . '/../DB/DB_connection.php';

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
    
    /* Mobile menu toggle */
    .menu-toggle {
      display: none;
      position: fixed;
      top: 1rem;
      left: 1rem;
      z-index: 1001;
      background: #2563eb;
      color: #fff;
      border: none;
      border-radius: 6px;
      padding: 0.6rem 0.8rem;
      font-size: 1.2rem;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }
    
    .admin-sidebar {
      background: #2563eb;
      color: #fff;
      width: 240px;
      padding: 2rem 1rem;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      transition: transform 0.3s ease;
      position: relative;
      z-index: 100;
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
      width: 100%;
    }
    
    .admin-header {
      background: #2563eb;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.2rem 2rem;
      gap: 1rem;
      flex-wrap: wrap;
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
      flex-wrap: wrap;
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
      white-space: nowrap;
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
      width: 100%;
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
    
    /* Tablet responsive */
    @media (max-width: 1024px) {
      .admin-sidebar {
        width: 200px;
      }
      
      .admin-sidebar h3 {
        font-size: 1.1rem;
      }
      
      .admin-sidebar a {
        font-size: 0.9rem;
        padding: 0.65rem 0.85rem;
      }
      
      .admin-header {
        padding: 1rem 1.5rem;
      }
      
      .admin-content {
        padding: 1.5rem;
      }
    }
    
    /* Mobile responsive */
    @media (max-width: 768px) {
      .menu-toggle {
        display: block;
      }
      
      .admin-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        z-index: 1000;
        transform: translateX(-100%);
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
      }
      
      .admin-sidebar.active {
        transform: translateX(0);
      }
      
      .admin-sidebar h3 {
        font-size: 1.2rem;
        margin-bottom: 2rem;
      }
      
      .admin-header {
        flex-direction: column;
        align-items: stretch;
        padding: 1rem;
        padding-top: 4rem;
      }
      
      .admin-header h1 {
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
      }
      
      .header-right {
        width: 100%;
        flex-direction: column;
        gap: 0.75rem;
      }
      
      .admin-header input[type="text"] {
        width: 100%;
      }
      
      .admin-header button {
        width: 100%;
        padding: 0.65rem;
      }
      
      .admin-content {
        padding: 1rem;
      }
      
      .settings-card {
        padding: 1.5rem;
      }
      
      .settings-card h2 {
        font-size: 1.2rem;
      }
      
      .modal-content {
        min-width: auto;
        width: 90%;
        padding: 1.5rem;
      }
      
      .modal-content h3 {
        font-size: 1.2rem;
      }
    }
    
    /* Small mobile devices */
    @media (max-width: 480px) {
      .admin-sidebar {
        width: 200px;
      }
      
      .admin-header h1 {
        font-size: 1rem;
      }
      
      .settings-card {
        padding: 1rem;
      }
      
      .settings-card h2 {
        font-size: 1.1rem;
      }
      
      .form-group label {
        font-size: 0.9rem;
      }
      
      .form-group input[type="password"] {
        font-size: 0.95rem;
        padding: 0.65rem;
      }
      
      .btn-update {
        font-size: 0.95rem;
        padding: 0.75rem;
      }
      
      .modal-content {
        padding: 1.25rem;
      }
      
      .modal-content h3 {
        font-size: 1.1rem;
      }
      
      .modal-content p {
        font-size: 0.9rem;
      }
    }
    
    /* Landscape mobile orientation */
    @media (max-height: 600px) and (orientation: landscape) {
      .admin-sidebar {
        padding: 1rem 0.75rem;
      }
      
      .admin-sidebar h3 {
        margin-bottom: 1.5rem;
        font-size: 1rem;
      }
      
      .admin-sidebar a {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
      }
    }
    
    /* Overlay for mobile sidebar */
    .sidebar-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 999;
    }
    
    .sidebar-overlay.active {
      display: block;
    }
  </style>
</head>
<body>
  <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
  <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
  
  <div class="admin-dashboard">
    <aside class="admin-sidebar" id="sidebar">
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
    // Toggle sidebar for mobile
    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      const overlay = document.querySelector('.sidebar-overlay');
      sidebar.classList.toggle('active');
      overlay.classList.toggle('active');
    }
    
    // Close sidebar when clicking on a link (mobile)
    document.querySelectorAll('.admin-sidebar a').forEach(link => {
      link.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
          toggleSidebar();
        }
      });
    });
    
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
    
    // Handle window resize
    window.addEventListener('resize', function() {
      if (window.innerWidth > 768) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
      }
    });
  </script>
</body>
</html>
