<?php
session_start();require_once __DIR__ . '/../DB/DB_connection.php';

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
  <title>Load Balance</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f4f4f9;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      margin: 0;
    }
    .container {
      background: #fff;
      padding: 25px;
      border-radius: 10px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      width: 350px;
    }
    h2 {
      text-align: center;
      margin-bottom: 20px;
      color: #333;
    }
    label {
      font-weight: bold;
      display: block;
      margin-top: 10px;
      color: #555;
    }
    input[type="text"], input[type="password"] {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      border: 1px solid #ccc;
      border-radius: 5px;
      font-size: 14px;
    }
    input[type="submit"] {
      margin-top: 20px;
      width: 100%;
      padding: 10px;
      background: #28a745;
      border: none;
      color: #fff;
      font-size: 16px;
      border-radius: 5px;
      cursor: pointer;
      transition: background 0.3s;
    }
    input[type="submit"]:hover {
      background: #218838;
    }
    .message {
      margin-top: 15px;
      text-align: center;
      font-weight: bold;
      padding: 10px;
      border-radius: 5px;
    }
    .success {
      background: #e6ffed;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    .error {
      background: #ffe6e6;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>💳 Load Balance</h2>
    <form method="post">
      <label for="email">Email</label>
      <input type="text" id="email" name="email" required autocomplete="off">

      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autocomplete="off">

      <label for="amount">Amount to Load</label>
      <input 
        type="text" 
        id="amount" 
        name="amount" 
        placeholder="Enter whole number (e.g. 100)" 
        required 
        autocomplete="off"
        inputmode="numeric" 
        pattern="[0-9]+" 
        oninput="this.value = this.value.replace(/[^0-9]/g, '')">

      <input type="submit" value="Load Balance">
    </form>

    <?php if ($success): ?>
      <p class="message success"><?= $success ?></p>
    <?php elseif ($error): ?>
      <p class="message error"><?= $error ?></p>
    <?php endif; ?>
  </div>
</body>

</html>
