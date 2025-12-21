<?php
session_start();
require_once __DIR__ . '/../DB/DB_connection.php';

// Initialize messages
$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $amount = trim($_POST['amount']);

    // Validate inputs (must be whole numbers only)
    if (!$email || !$password || !$amount || !ctype_digit($amount) || intval($amount) <= 0) {
        $error = "Amount must be a whole number greater than 0!";
    } else {
        try {
            // Authenticate customer
            $result = db_prepare("SELECT id, password, balance FROM customers WHERE email = $1", [$email]);
            $customer = db_fetch_assoc($result);

            if ($customer && password_verify($password, $customer['password'])) {
                $new_balance = $customer['balance'] + intval($amount);

                $updateResult = db_prepare("UPDATE customers SET balance = $1 WHERE id = $2", [$new_balance, $customer['id']]);
                
                if ($updateResult) {
                    $success = "✅ Successfully loaded ₱" . number_format($amount, 0) . ".<br>💳 New Balance: ₱" . number_format($new_balance, 2);
                } else {
                    $error = "⚠️ Failed to update balance. Please try again.";
                }
            } else {
                $error = "❌ Invalid email or password!";
            }
        } catch (Exception $e) {
            error_log("Load Balance Error: " . $e->getMessage());
            $error = "⚠️ An error occurred. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Load Balance</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 1rem;
      overflow-x: hidden;
    }

    .container {
      background: #ffffff;
      padding: 2.5rem;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      width: 100%;
      max-width: 450px;
      animation: slideUp 0.5s ease-out;
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .header {
      text-align: center;
      margin-bottom: 2rem;
    }

    .header h2 {
      font-size: 1.8rem;
      color: #333;
      margin-bottom: 0.5rem;
      font-weight: 700;
    }

    .header p {
      font-size: 0.95rem;
      color: #64748b;
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    label {
      font-weight: 600;
      display: block;
      margin-bottom: 0.5rem;
      color: #475569;
      font-size: 0.95rem;
    }

    input[type="text"],
    input[type="password"] {
      width: 100%;
      padding: 0.875rem 1rem;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      font-size: 1rem;
      transition: all 0.3s ease;
      background: #f8fafc;
    }

    input[type="text"]:focus,
    input[type="password"]:focus {
      outline: none;
      border-color: #667eea;
      background: #ffffff;
      box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }

    .amount-input-wrapper {
      position: relative;
    }

    .amount-input-wrapper::before {
      content: "₱";
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      font-size: 1.1rem;
      font-weight: 700;
      color: #667eea;
    }

    .amount-input-wrapper input {
      padding-left: 2.25rem;
    }

    .quick-amounts {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 0.5rem;
      margin-top: 0.75rem;
    }

    .quick-amount-btn {
      padding: 0.6rem;
      background: #f1f5f9;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 600;
      color: #475569;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .quick-amount-btn:hover {
      background: #e0e7ff;
      border-color: #667eea;
      color: #667eea;
    }

    .quick-amount-btn:active {
      transform: scale(0.95);
    }

    input[type="submit"] {
      margin-top: 1.5rem;
      width: 100%;
      padding: 1rem;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border: none;
      color: #ffffff;
      font-size: 1.1rem;
      font-weight: 700;
      border-radius: 10px;
      cursor: pointer;
      transition: all 0.3s ease;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }

    input[type="submit"]:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    }

    input[type="submit]:active {
      transform: translateY(0);
    }

    .message {
      margin-top: 1.5rem;
      text-align: center;
      font-weight: 600;
      padding: 1rem 1.25rem;
      border-radius: 10px;
      animation: fadeIn 0.5s ease-out;
      line-height: 1.6;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .success {
      background: #dcfce7;
      color: #166534;
      border: 2px solid #86efac;
    }

    .error {
      background: #fee2e2;
      color: #991b1b;
      border: 2px solid #fca5a5;
    }

    .back-link {
      text-align: center;
      margin-top: 1.5rem;
    }

    .back-link a {
      color: #667eea;
      text-decoration: none;
      font-weight: 600;
      font-size: 0.95rem;
      transition: color 0.2s ease;
    }

    .back-link a:hover {
      color: #764ba2;
      text-decoration: underline;
    }

    /* Tablet Styles (768px - 1024px) */
    @media (max-width: 1024px) {
      .container {
        max-width: 420px;
        padding: 2.25rem;
      }

      .header h2 {
        font-size: 1.7rem;
      }
    }

    /* Mobile Styles (up to 767px) */
    @media (max-width: 767px) {
      body {
        padding: 1rem 0.75rem;
      }

      .container {
        padding: 2rem 1.5rem;
        border-radius: 16px;
        max-width: 100%;
      }

      .header {
        margin-bottom: 1.75rem;
      }

      .header h2 {
        font-size: 1.6rem;
      }

      .header p {
        font-size: 0.9rem;
      }

      .form-group {
        margin-bottom: 1.25rem;
      }

      label {
        font-size: 0.9rem;
      }

      input[type="text"],
      input[type="password"] {
        padding: 0.8rem 0.875rem;
        font-size: 0.95rem;
      }

      .amount-input-wrapper input {
        padding-left: 2rem;
      }

      .quick-amounts {
        gap: 0.4rem;
      }

      .quick-amount-btn {
        padding: 0.5rem;
        font-size: 0.8rem;
      }

      input[type="submit"] {
        padding: 0.875rem;
        font-size: 1rem;
        margin-top: 1.25rem;
      }

      .message {
        padding: 0.875rem 1rem;
        font-size: 0.95rem;
      }
    }

    /* Small Mobile Styles (up to 480px) */
    @media (max-width: 480px) {
      body {
        padding: 0.875rem 0.5rem;
      }

      .container {
        padding: 1.75rem 1.25rem;
        border-radius: 14px;
      }

      .header {
        margin-bottom: 1.5rem;
      }

      .header h2 {
        font-size: 1.4rem;
      }

      .header p {
        font-size: 0.85rem;
      }

      .form-group {
        margin-bottom: 1.15rem;
      }

      label {
        font-size: 0.875rem;
        margin-bottom: 0.4rem;
      }

      input[type="text"],
      input[type="password"] {
        padding: 0.75rem 0.875rem;
        font-size: 0.9rem;
        border-radius: 8px;
      }

      .amount-input-wrapper::before {
        font-size: 1rem;
        left: 0.875rem;
      }

      .amount-input-wrapper input {
        padding-left: 1.875rem;
      }

      .quick-amounts {
        grid-template-columns: repeat(3, 1fr);
        gap: 0.35rem;
      }

      .quick-amount-btn {
        padding: 0.45rem 0.25rem;
        font-size: 0.75rem;
        border-radius: 6px;
      }

      input[type="submit"] {
        padding: 0.8rem;
        font-size: 0.95rem;
        border-radius: 8px;
      }

      .message {
        padding: 0.75rem 0.875rem;
        font-size: 0.875rem;
        margin-top: 1.25rem;
      }

      .back-link {
        margin-top: 1.25rem;
      }

      .back-link a {
        font-size: 0.875rem;
      }
    }

    /* Extra Small Mobile (up to 360px) */
    @media (max-width: 360px) {
      body {
        padding: 0.75rem 0.4rem;
      }

      .container {
        padding: 1.5rem 1rem;
        border-radius: 12px;
      }

      .header h2 {
        font-size: 1.3rem;
      }

      .header p {
        font-size: 0.8rem;
      }

      input[type="text"],
      input[type="password"] {
        padding: 0.7rem 0.75rem;
        font-size: 0.875rem;
      }

      .quick-amounts {
        gap: 0.3rem;
      }

      .quick-amount-btn {
        padding: 0.4rem 0.2rem;
        font-size: 0.7rem;
      }

      input[type="submit"] {
        padding: 0.75rem;
        font-size: 0.9rem;
      }
    }

    /* Landscape Mobile */
    @media (max-height: 500px) and (orientation: landscape) {
      body {
        padding: 0.75rem;
      }

      .container {
        padding: 1.5rem;
        max-width: 600px;
      }

      .header {
        margin-bottom: 1.25rem;
      }

      .header h2 {
        font-size: 1.4rem;
      }

      .form-group {
        margin-bottom: 1rem;
      }

      input[type="submit"] {
        margin-top: 1rem;
        padding: 0.75rem;
      }

      .message {
        margin-top: 1rem;
        padding: 0.75rem 1rem;
      }
    }

    /* Very Wide Screens */
    @media (min-width: 1440px) {
      .container {
        max-width: 500px;
        padding: 3rem;
      }

      .header h2 {
        font-size: 2rem;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h2>💳 Load Balance</h2>
      <p>Add funds to your parking account</p>
    </div>

    <form method="post">
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="text" id="email" name="email" required autocomplete="email" placeholder="your@email.com">
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
      </div>

      <div class="form-group">
        <label for="amount">Amount to Load</label>
        <div class="amount-input-wrapper">
          <input 
            type="text" 
            id="amount" 
            name="amount" 
            placeholder="Enter amount" 
            required 
            autocomplete="off"
            inputmode="numeric" 
            pattern="[0-9]+" 
            oninput="this.value = this.value.replace(/[^0-9]/g, '')">
        </div>
        <div class="quick-amounts">
          <button type="button" class="quick-amount-btn" onclick="setAmount(100)">₱100</button>
          <button type="button" class="quick-amount-btn" onclick="setAmount(200)">₱200</button>
          <button type="button" class="quick-amount-btn" onclick="setAmount(500)">₱500</button>
          <button type="button" class="quick-amount-btn" onclick="setAmount(1000)">₱1,000</button>
          <button type="button" class="quick-amount-btn" onclick="setAmount(2000)">₱2,000</button>
          <button type="button" class="quick-amount-btn" onclick="setAmount(5000)">₱5,000</button>
        </div>
      </div>

      <input type="submit" value="Load Balance">
    </form>

    <?php if ($success): ?>
      <div class="message success"><?= $success ?></div>
    <?php elseif ($error): ?>
      <div class="message error"><?= $error ?></div>
    <?php endif; ?>

    <div class="back-link">
      <a href="customer-dashboard.php">← Back to Dashboard</a>
    </div>
  </div>

  <script>
    function setAmount(value) {
      document.getElementById('amount').value = value;
    }

    // Add enter key support for quick amount buttons
    document.querySelectorAll('.quick-amount-btn').forEach(btn => {
      btn.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          this.click();
        }
      });
    });

    // Add loading state to submit button
    const form = document.querySelector('form');
    const submitBtn = document.querySelector('input[type="submit"]');
    
    form.addEventListener('submit', function() {
      submitBtn.value = 'Processing...';
      submitBtn.disabled = true;
    });
  </script>
</body>
</html>
