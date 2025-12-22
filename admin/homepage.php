<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include PostgreSQL database connection
require_once __DIR__ . '/../DB/DB_connection.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!$email || !$password) {
        $error = "Both fields are required!";
    } else {
        try {
            // Query admin by email
            $result = db_prepare("SELECT * FROM admins WHERE email = $1", [$email]);
            
            // Check if query worked
            if (!$result) {
                // Generic error - don't reveal database issues
                $error = "Invalid email or password. Please try again.";
                error_log("Database query failed: " . db_error());
            } elseif (db_num_rows($result) === 0) {
                // Generic error - don't reveal if email exists or not
                $error = "Invalid email or password. Please try again.";
            } else {
                $admin = db_fetch_assoc($result);
                
                // Check if password is hashed or plain text
                if (substr($admin['password'], 0, 4) === '$2y$' || substr($admin['password'], 0, 4) === '$2a$') {
                    // Password is hashed, use password_verify
                    if (password_verify($password, $admin['password'])) {
                        $_SESSION['admin'] = $email;
                        $_SESSION['admin_id'] = $admin['id'];
                        header("Location: admin-dashboard.php");
                        exit;
                    } else {
                        // Generic error - don't reveal password verification details
                        $error = "Invalid email or password. Please try again.";
                    }
                } else {
                    // Password might be plain text, compare directly
                    if ($password === $admin['password']) {
                        $_SESSION['admin'] = $email;
                        $_SESSION['admin_id'] = $admin['id'];
                        header("Location: admin-dashboard.php");
                        exit;
                    } else {
                        // Generic error - don't reveal password verification details
                        $error = "Invalid email or password. Please try again.";
                    }
                }
            }
        } catch (Exception $e) {
            // Log the actual error but show generic message to user
            error_log("Admin Login Error: " . $e->getMessage());
            $error = "An error occurred. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login - Southwoods Parking</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      background: #f0f2f5;
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
      min-height: 100vh;
      overflow-x: hidden;
    }
    
    .login-container {
      display: flex;
      min-height: 100vh;
      align-items: stretch;
    }
    
    .login-left {
      flex: 1;
      background: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 3rem;
      position: relative;
      overflow: hidden;
    }
    
    .login-left::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('img/southwoods_bg.jpg') no-repeat center center;
      background-size: cover;
      transition: transform 0.3s ease;
    }
    
    .login-left:hover::before {
      transform: scale(1.05);
    }
    
    .login-left::after {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, rgba(30, 91, 184, 0.15) 0%, rgba(37, 99, 235, 0.15) 100%);
    }
    
    .logo-overlay {
      position: relative;
      z-index: 1;
      text-align: center;
      padding: 2.5rem;
      background: rgba(255, 255, 255, 0.95);
      border-radius: 24px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
      backdrop-filter: blur(10px);
      max-width: 400px;
    }
    
    .logo-overlay img {
      width: 180px;
      height: auto;
      margin-bottom: 1rem;
    }
    
    .logo-overlay h1 {
      font-size: 1.5rem;
      color: #1e5bb8;
      font-weight: 700;
      margin-bottom: 0.5rem;
      letter-spacing: 0.5px;
      line-height: 1.3;
    }
    
    .logo-overlay p {
      font-size: 1rem;
      color: #4a5568;
      font-weight: 500;
    }
    
    .admin-badge {
      display: inline-block;
      background: linear-gradient(135deg, #1e5bb8 0%, #2563eb 100%);
      color: white;
      padding: 0.5rem 1.25rem;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 700;
      letter-spacing: 0.5px;
      margin-top: 1rem;
      text-transform: uppercase;
    }
    
    .login-right {
      flex: 1;
      background: linear-gradient(135deg, #1e5bb8 0%, #2563eb 100%);
      color: #fff;
      padding: 3rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      position: relative;
      box-shadow: -5px 0 30px rgba(0, 0, 0, 0.1);
    }
    
    .login-right::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: radial-gradient(circle at top right, rgba(255,255,255,0.1) 0%, transparent 60%);
      pointer-events: none;
    }
    
    .login-form-wrapper {
      width: 100%;
      max-width: 420px;
      position: relative;
      z-index: 1;
    }
    
    .login-right h2 {
      font-size: 2rem;
      font-weight: 800;
      margin-bottom: 0.75rem;
      letter-spacing: 1.5px;
      text-align: center;
      line-height: 1.2;
      text-transform: uppercase;
    }
    
    .login-right .subtitle {
      font-size: 1.1rem;
      margin-bottom: 3rem;
      text-align: center;
      color: rgba(255, 255, 255, 0.9);
      font-weight: 400;
    }
    
    .msg-error {
      color: #dc2626;
      background: #fee2e2;
      border-radius: 10px;
      padding: 1rem;
      margin-bottom: 1.5rem;
      text-align: center;
      font-weight: 600;
      font-size: 0.95rem;
      border: 2px solid #fca5a5;
      animation: slideDown 0.3s ease;
      word-break: break-word;
    }
    
    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .form-group {
      margin-bottom: 1.75rem;
    }
    
    .form-group label {
      font-weight: 600;
      display: block;
      margin-bottom: 0.6rem;
      color: #fff;
      font-size: 1rem;
      letter-spacing: 0.3px;
    }
    
    .form-group input {
      width: 100%;
      padding: 0.95rem 1.2rem;
      border-radius: 10px;
      border: none;
      font-size: 1rem;
      background: rgba(255, 255, 255, 0.95);
      color: #1a1a1a;
      transition: all 0.3s ease;
      font-family: inherit;
    }
    
    .form-group input::placeholder {
      color: #9ca3af;
    }
    
    .form-group input:focus {
      outline: none;
      background: #ffffff;
      box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
      transform: translateY(-2px);
    }
    
    .login-btn {
      width: 100%;
      background: linear-gradient(135deg, #1e40af 0%, #1e5bb8 100%);
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 1.1rem;
      font-size: 1.1rem;
      font-weight: 700;
      cursor: pointer;
      letter-spacing: 1px;
      transition: all 0.3s ease;
      margin-top: 1rem;
      text-transform: uppercase;
    }
    
    .login-btn:hover {
      background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
    }
    
    .login-btn:active {
      transform: translateY(0);
    }
    
    .back-link {
      width: 100%;
      background: rgba(255, 255, 255, 0.15);
      color: #fff;
      border: 2px solid rgba(255, 255, 255, 0.3);
      border-radius: 10px;
      padding: 0.95rem;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      letter-spacing: 0.5px;
      transition: all 0.3s ease;
      margin-top: 1rem;
      text-align: center;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }
    
    .back-link:hover {
      background: rgba(255, 255, 255, 0.25);
      border-color: rgba(255, 255, 255, 0.5);
      transform: translateY(-2px);
    }
    
    .back-link::before {
      content: "←";
      font-size: 1.3rem;
    }
    
    /* Tablet Landscape */
    @media (max-width: 1024px) {
      .login-container {
        flex-direction: column;
      }
      
      .login-left {
        min-height: 40vh;
        padding: 2rem;
      }
      
      .logo-overlay {
        padding: 2rem;
        max-width: 350px;
      }
      
      .logo-overlay img {
        width: 150px;
      }
      
      .logo-overlay h1 {
        font-size: 1.3rem;
      }
      
      .login-right {
        padding: 3rem 2rem;
      }
      
      .login-right h2 {
        font-size: 1.75rem;
      }
    }
    
    /* Tablet Portrait */
    @media (max-width: 768px) {
      .login-left {
        min-height: 35vh;
        padding: 1.5rem;
      }
      
      .logo-overlay {
        padding: 1.75rem;
        max-width: 320px;
      }
      
      .logo-overlay img {
        width: 130px;
      }
      
      .logo-overlay h1 {
        font-size: 1.2rem;
      }
      
      .logo-overlay p {
        font-size: 0.9rem;
      }
      
      .admin-badge {
        font-size: 0.75rem;
        padding: 0.4rem 1rem;
      }
      
      .login-right {
        padding: 2.5rem 1.5rem;
      }
      
      .login-right h2 {
        font-size: 1.5rem;
        letter-spacing: 1px;
      }
      
      .login-right .subtitle {
        font-size: 1rem;
        margin-bottom: 2rem;
      }
      
      .form-group {
        margin-bottom: 1.5rem;
      }
    }
    
    /* Mobile Large */
    @media (max-width: 480px) {
      .login-left {
        min-height: 30vh;
        padding: 1rem;
      }
      
      .logo-overlay {
        padding: 1.5rem;
        max-width: 280px;
        border-radius: 20px;
      }
      
      .logo-overlay img {
        width: 110px;
      }
      
      .logo-overlay h1 {
        font-size: 1.1rem;
        margin-bottom: 0.4rem;
      }
      
      .logo-overlay p {
        font-size: 0.85rem;
      }
      
      .admin-badge {
        font-size: 0.7rem;
        padding: 0.35rem 0.85rem;
        margin-top: 0.75rem;
      }
      
      .login-right {
        padding: 2rem 1.25rem;
      }
      
      .login-right h2 {
        font-size: 1.3rem;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
      }
      
      .login-right .subtitle {
        font-size: 0.95rem;
        margin-bottom: 1.75rem;
      }
      
      .login-form-wrapper {
        max-width: 100%;
      }
      
      .form-group {
        margin-bottom: 1.25rem;
      }
      
      .form-group label {
        font-size: 0.95rem;
        margin-bottom: 0.5rem;
      }
      
      .form-group input {
        padding: 0.85rem 1rem;
        font-size: 0.95rem;
      }
      
      .login-btn {
        padding: 1rem;
        font-size: 1rem;
      }
      
      .back-link {
        padding: 0.85rem;
        font-size: 0.95rem;
      }
      
      .msg-error {
        padding: 0.85rem;
        font-size: 0.85rem;
      }
    }
    
    /* Mobile Small */
    @media (max-width: 360px) {
      .logo-overlay {
        padding: 1.25rem;
        max-width: 260px;
      }
      
      .logo-overlay img {
        width: 100px;
      }
      
      .logo-overlay h1 {
        font-size: 1rem;
      }
      
      .logo-overlay p {
        font-size: 0.8rem;
      }
      
      .login-right {
        padding: 1.75rem 1rem;
      }
      
      .login-right h2 {
        font-size: 1.2rem;
      }
      
      .login-right .subtitle {
        font-size: 0.9rem;
      }
      
      .form-group input {
        padding: 0.75rem 0.9rem;
        font-size: 0.9rem;
      }
      
      .login-btn, .back-link {
        padding: 0.8rem;
        font-size: 0.9rem;
      }
    }
    
    /* Landscape orientation fix for mobile */
    @media (max-height: 600px) and (orientation: landscape) {
      .login-container {
        flex-direction: row;
      }
      
      .login-left {
        min-height: 100vh;
        flex: 0.8;
      }
      
      .login-right {
        flex: 1.2;
        padding: 2rem 1.5rem;
      }
      
      .logo-overlay {
        padding: 1.5rem;
        max-width: 280px;
      }
      
      .logo-overlay img {
        width: 100px;
      }
      
      .logo-overlay h1 {
        font-size: 1.1rem;
      }
      
      .login-right h2 {
        font-size: 1.3rem;
        margin-bottom: 0.5rem;
      }
      
      .login-right .subtitle {
        font-size: 0.9rem;
        margin-bottom: 1.5rem;
      }
      
      .form-group {
        margin-bottom: 1rem;
      }
      
      .form-group input {
        padding: 0.7rem 1rem;
      }
      
      .login-btn, .back-link {
        padding: 0.8rem;
        font-size: 0.9rem;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-left">
      <div class="logo-overlay">
        <img src="img/Without-Background.png" alt="Southwoods Logo">
        <h1>SOUTHWOODS MALL</h1>
        <p>Smart Parking System</p>
        <span class="admin-badge">Admin Portal</span>
      </div>
    </div>
    
    <div class="login-right">
      <div class="login-form-wrapper">
        <h2>Admin Login</h2>
        <p class="subtitle">Secure Access to Dashboard</p>
        
        <?php if($error): ?>
          <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" autocomplete="off">
          <div class="form-group">
            <label for="email">Email Address*</label>
            <input type="email" id="email" name="email" placeholder="Enter your Email Address" required>
          </div>
          
          <div class="form-group">
            <label for="password">Password*</label>
            <input type="password" id="password" name="password" placeholder="Enter your Password" required>
          </div>
          
          <button type="submit" class="login-btn">Login</button>
          <a href="../website/index.php" class="back-link">Back to Main</a>
        </form>
      </div>
    </div>
  </div>
</body>
</html>

