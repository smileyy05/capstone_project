<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    header('Location: customer-login.php');
    exit();
}

// Include database connection
require_once '../DB/DB_connection.php';

// Get customer ID from session
$customer_id = $_SESSION['customer_id'];

// Fetch fresh customer data from database
$sql = "SELECT id, name, email, plate, vehicle, balance, qr_code FROM customers WHERE id = $1 AND archived = 0";
$result = db_prepare($sql, [$customer_id]);

if ($result && db_num_rows($result) > 0) {
    $customer = db_fetch_assoc($result);
    
    // Update session with fresh data
    $_SESSION['customer_name'] = $customer['name'];
    $_SESSION['customer_email'] = $customer['email'];
    $_SESSION['plate_number'] = $customer['plate'];
    $_SESSION['vehicle_type'] = $customer['vehicle'];
    $_SESSION['balance'] = $customer['balance'];
    $_SESSION['qr_code'] = $customer['qr_code'];
    
    // Set variables for display
    $customer_name = $customer['name'];
    $customer_email = $customer['email'];
    $plate_number = $customer['plate'];
    $vehicle_type = $customer['vehicle'];
    $balance = $customer['balance'];
    $qr_code = $customer['qr_code'];
} else {
    // Customer not found or archived, logout
    header('Location: customer-logout.php');
    exit();
}

// Generate QR code data
$qr_data = $qr_code; // Use the stored QR code from database
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Account - Parking System</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      background: linear-gradient(135deg, #e8eef5 0%, #b8d4f1 100%);
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    
    .account-container {
      background: #ffffff;
      border-radius: 24px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
      padding: 3rem 3rem 2.5rem 3rem;
      max-width: 540px;
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    
    .account-container h1 {
      font-size: 2rem;
      color: #1e5bb8;
      font-weight: 700;
      text-align: center;
      margin-bottom: 2rem;
      letter-spacing: 0.5px;
    }
    
    .account-info {
      width: 100%;
      margin-bottom: 1.5rem;
    }
    
    .info-row {
      display: flex;
      align-items: baseline;
      margin-bottom: 1rem;
      font-size: 1.05rem;
    }
    
    .info-row label {
      font-weight: 700;
      color: #1a1a1a;
      margin-right: 0.5rem;
    }
    
    .info-row span {
      color: #4a5568;
      font-weight: 400;
    }
    
    .balance-box {
      width: 100%;
      background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
      border: 2px solid #6ee7b7;
      border-radius: 12px;
      padding: 1.25rem;
      text-align: center;
      margin: 1.5rem 0 2rem 0;
    }
    
    .balance-box .balance-label {
      font-size: 1.1rem;
      font-weight: 700;
      color: #065f46;
    }
    
    .qr-section {
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 2rem;
    }
    
    .qr-section h3 {
      font-size: 1.1rem;
      color: #1a1a1a;
      font-weight: 600;
      margin-bottom: 1rem;
    }
    
    .qr-code-box {
      background: #ffffff;
      border: 3px solid #3b82f6;
      border-radius: 16px;
      padding: 1.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .qr-code-box img {
      width: 180px;
      height: 180px;
      display: block;
    }
    
    .action-buttons {
      display: flex;
      gap: 1rem;
      margin-top: 0.5rem;
      flex-wrap: wrap;
      justify-content: center;
    }
    
    .btn {
      padding: 0.875rem 2rem;
      font-size: 1.05rem;
      font-weight: 600;
      border-radius: 10px;
      border: none;
      cursor: pointer;
      transition: all 0.3s ease;
      letter-spacing: 0.5px;
      text-decoration: none;
      display: inline-block;
    }
    
    .logout-btn {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: #ffffff;
    }
    
    .logout-btn:hover {
      background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(220, 38, 38, 0.4);
    }
    
    .topup-btn {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: #ffffff;
    }
    
    .topup-btn:hover {
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(5, 150, 105, 0.4);
    }
    
    .btn:active {
      transform: translateY(0);
    }
    
    /* Top-up Modal Styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      align-items: center;
      justify-content: center;
    }
    
    .modal-content {
      background-color: #fff;
      padding: 2rem;
      border-radius: 16px;
      max-width: 400px;
      width: 90%;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    }
    
    .modal-content h2 {
      color: #1e5bb8;
      margin-bottom: 1.5rem;
      text-align: center;
    }
    
    .modal-content input {
      width: 100%;
      padding: 0.875rem;
      border: 2px solid #d1d5db;
      border-radius: 8px;
      font-size: 1rem;
      margin-bottom: 1rem;
    }
    
    .modal-content input:focus {
      outline: none;
      border-color: #3b82f6;
    }
    
    .modal-buttons {
      display: flex;
      gap: 1rem;
      justify-content: center;
    }
    
    .modal-btn {
      padding: 0.75rem 1.5rem;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    .modal-btn.submit {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
    }
    
    .modal-btn.cancel {
      background: #e5e7eb;
      color: #374151;
    }
    
    .modal-btn:hover {
      transform: translateY(-2px);
    }
    
    @media (max-width: 600px) {
      .account-container {
        padding: 2.5rem 1.5rem;
        border-radius: 20px;
        max-width: 400px;
      }
      
      .account-container h1 { 
        font-size: 1.75rem; 
      }
      
      .info-row {
        font-size: 0.95rem;
      }
      
      .qr-code-box img {
        width: 150px;
        height: 150px;
      }
      
      .btn {
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
      }
      
      .action-buttons {
        flex-direction: column;
        width: 100%;
      }
      
      .btn {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <div class="account-container">
    <h1>Welcome, <?php echo htmlspecialchars($customer_name); ?>!</h1>
    
    <div class="account-info">
      <div class="info-row">
        <label>Email:</label>
        <span><?php echo htmlspecialchars($customer_email); ?></span>
      </div>
      
      <div class="info-row">
        <label>Plate Number:</label>
        <span><?php echo htmlspecialchars($plate_number); ?></span>
      </div>
      
      <div class="info-row">
        <label>Vehicle Type:</label>
        <span><?php echo htmlspecialchars($vehicle_type); ?></span>
      </div>
    </div>
    
    <div class="balance-box">
      <div class="balance-label">Balance: ₱<?php echo number_format($balance, 2); ?></div>
    </div>
    
    <div class="qr-section">
      <h3>Your QR Code:</h3>
      <div class="qr-code-box">
        <!-- QR Code generated using stored QR code value -->
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?php echo urlencode($qr_data); ?>" 
             alt="Customer QR Code">
      </div>
    </div>
    
    <div class="action-buttons">
      <button class="btn topup-btn" onclick="openTopUpModal()">Top Up Balance</button>
      <button class="btn logout-btn" onclick="location.href='customer-logout.php'">Logout</button>
    </div>
  </div>

  <!-- Top-up Modal -->
  <div id="topupModal" class="modal">
    <div class="modal-content">
      <h2>Top Up Balance</h2>
      <form id="topupForm">
        <input type="number" id="topupAmount" name="amount" placeholder="Enter amount (₱)" min="1" step="0.01" required>
        <div class="modal-buttons">
          <button type="submit" class="modal-btn submit">Confirm</button>
          <button type="button" class="modal-btn cancel" onclick="closeTopUpModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openTopUpModal() {
      document.getElementById('topupModal').style.display = 'flex';
    }

    function closeTopUpModal() {
      document.getElementById('topupModal').style.display = 'none';
      document.getElementById('topupForm').reset();
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
      const modal = document.getElementById('topupModal');
      if (event.target == modal) {
        closeTopUpModal();
      }
    }

    // Handle top-up form submission
    document.getElementById('topupForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const amount = parseFloat(document.getElementById('topupAmount').value);
      
      if (amount <= 0) {
        alert('Please enter a valid amount');
        return;
      }

      // Send AJAX request to process top-up
      fetch('process-topup.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'amount=' + amount
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('Top-up successful! Your new balance is ₱' + data.new_balance);
          location.reload(); // Reload to show new balance
        } else {
          alert('Top-up failed: ' + (data.error || 'Unknown error'));
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred during top-up');
      });

      closeTopUpModal();
    });
  </script>
</body>
</html>