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
      background: #0f172a;
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
      background: #1e293b;
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
      background: url('../img/southwoods_bg.jpg') no-repeat center center;
      background-size: cover;
      opacity: 0.3;
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
      background: linear-gradient(135deg, rgba(30, 91, 184, 0.4) 0%, rgba(37, 99, 235, 0.4) 100%);
    }
    
    .logo-overlay {
      position: relative;
      z-index: 1;
      text-align: center;
      padding: 3rem;
      background: rgba(255, 255, 255, 0.98);
      border-radius: 32px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(20px);
      max-width: 450px;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .logo-overlay img {
      width: 200px;
      height: auto;
      margin-bottom: 1.5rem;
      filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.1));
    }
    
    .logo-overlay h1 {
      font-size: 1.75rem;
      background: linear-gradient(135deg, #1e5bb8 0%, #2563eb 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      font-weight: 800;
      margin-bottom: 0.75rem;
      letter-spacing: 1px;
      line-height: 1.3;
    }
    
    .logo-overlay p {
      font-size: 1.1rem;
      color: #475569;
      font-weight: 600;
      margin-bottom: 1rem;
    }
    
    .admin-badge {
      display: inline-block;
      background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
      color: white;
      padding: 0.6rem 1.5rem;
      border-radius: 25px;
      font-size: 0.9rem;
      font-weight: 700;
      letter-spacing: 1px;
      margin-top: 1rem;
      text-transform: uppercase;
      box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }
    
    .login-right {
      flex: 1;
      background: linear-gradient(135deg, #1e40af 0%, #1e5bb8 100%);
      color: #fff;
      padding: 3rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      position: relative;
      box-shadow: -5px 0 30px rgba(0, 0, 0, 0.2);
    }
    
    .login-right::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: 
        radial-gradient(circle at top right, rgba(255,255,255,0.15) 0%, transparent 50%),
        radial-gradient(circle at bottom left, rgba(255,255,255,0.1) 0%, transparent 50%);
      pointer-events: none;
    }
    
    .login-form-wrapper {
      width: 100%;
      max-width: 440px;
      position: relative;
      z-index: 1;
    }
    
    .login-right h2 {
      font-size: 2.25rem;
      font-weight: 800;
      margin-bottom: 0.75rem;
      letter-spacing: 2px;
      text-align: center;
      line-height: 1.2;
      text-transform: uppercase;
      text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }
    
    .login-right .subtitle {
      font-size: 1.15rem;
      margin-bottom: 3rem;
      text-align: center;
      color: rgba(255, 255, 255, 0.95);
      font-weight: 400;
      text-shadow: 0 1px 4px rgba(0, 0, 0, 0.15);
    }
    
    .msg-error {
      color: #dc2626;
      background: #fee2e2;
      border-radius: 12px;
      padding: 1.1rem;
      margin-bottom: 1.5rem;
      text-align: center;
      font-weight: 600;
      font-size: 0.95rem;
      border: 2px solid #fca5a5;
      animation: slideDown 0.3s ease;
      word-break: break-word;
      box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15);
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
      margin-bottom: 0.7rem;
      color: #fff;
      font-size: 1.05rem;
      letter-spacing: 0.3px;
      text-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
    }
    
    .form-group input {
      width: 100%;
      padding: 1rem 1.3rem;
      border-radius: 12px;
      border: 2px solid transparent;
      font-size: 1rem;
      background: rgba(255, 255, 255, 0.98);
      color: #1a1a1a;
      transition: all 0.3s ease;
      font-family: inherit;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .form-group input::placeholder {
      color: #94a3b8;
    }
    
    .form-group input:focus {
      outline: none;
      background: #ffffff;
      border-color: rgba(255, 255, 255, 0.5);
      box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.2), 0 4px 12px rgba(0, 0, 0, 0.15);
      transform: translateY(-2px);
    }
    
    .login-btn {
      width: 100%;
      background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
      color: #fff;
      border: none;
      border-radius: 12px;
      padding: 1.15rem;
      font-size: 1.15rem;
      font-weight: 700;
      cursor: pointer;
      letter-spacing: 1.5px;
      transition: all 0.3s ease;
      margin-top: 1rem;
      text-transform: uppercase;
      box-shadow: 0 6px 20px rgba(220, 38, 38, 0.35);
    }
    
    .login-btn:hover {
      background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);
      transform: translateY(-3px);
      box-shadow: 0 10px 30px rgba(220, 38, 38, 0.5);
    }
    
    .login-btn:active {
      transform: translateY(-1px);
    }
    
    .back-link {
      width: 100%;
      background: rgba(255, 255, 255, 0.15);
      color: #fff;
      border: 2px solid rgba(255, 255, 255, 0.35);
      border-radius: 12px;
      padding: 1rem;
      font-size: 1.05rem;
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
      gap: 0.6rem;
      backdrop-filter: blur(10px);
    }
    
    .back-link:hover {
      background: rgba(255, 255, 255, 0.25);
      border-color: rgba(255, 255, 255, 0.5);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    
    .back-link::before {
      content: "←";
      font-size: 1.4rem;
      font-weight: 700;
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
        padding: 2.5rem;
        max-width: 380px;
      }
      
      .logo-overlay img {
        width: 170px;
      }
      
      .logo-overlay h1 {
        font-size: 1.5rem;
      }
      
      .login-right {
        padding: 3rem 2rem;
      }
      
      .login-right h2 {
        font-size: 2rem;
      }
    }
    
    /* Tablet Portrait */
    @media (max-width: 768px) {
      .login-left {
        min-height: 35vh;
        padding: 1.5rem;
      }
      
      .logo-overlay {
        padding: 2rem;
        max-width: 340px;
      }
      
      .logo-overlay img {
        width: 150px;
      }
      
      .logo-overlay h1 {
        font-size: 1.35rem;
      }
      
      .logo-overlay p {
        font-size: 1rem;
      }
      
      .admin-badge {
        font-size: 0.8rem;
        padding: 0.5rem 1.2rem;
      }
      
      .login-right {
        padding: 2.5rem 1.5rem;
      }
      
      .login-right h2 {
        font-size: 1.75rem;
        letter-spacing: 1.5px;
      }
      
      .login-right .subtitle {
        font-size: 1.05rem;
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
        padding: 1.75rem;
        max-width: 300px;
        border-radius: 24px;
      }
      
      .logo-overlay img {
        width: 130px;
      }
      
      .logo-overlay h1 {
        font-size: 1.2rem;
        margin-bottom: 0.5rem;
      }
      
      .logo-overlay p {
        font-size: 0.95rem;
      }
      
      .admin-badge {
        font-size: 0.75rem;
        padding: 0.45rem 1rem;
        margin-top: 0.75rem;
      }
      
      .login-right {
        padding: 2rem 1.25rem;
      }
      
      .login-right h2 {
        font-size: 1.5rem;
        letter-spacing: 1px;
        margin-bottom: 0.5rem;
      }
      
      .login-right .subtitle {
        font-size: 1rem;
        margin-bottom: 1.75rem;
      }
      
      .login-form-wrapper {
        max-width: 100%;
      }
      
      .form-group {
        margin-bottom: 1.25rem;
      }
      
      .form-group label {
        font-size: 1rem;
        margin-bottom: 0.5rem;
      }
      
      .form-group input {
        padding: 0.9rem 1.1rem;
        font-size: 0.95rem;
      }
      
      .login-btn {
        padding: 1.05rem;
        font-size: 1.05rem;
      }
      
      .back-link {
        padding: 0.9rem;
        font-size: 1rem;
      }
      
      .msg-error {
        padding: 0.9rem;
        font-size: 0.9rem;
      }
    }
    
    /* Mobile Small */
    @media (max-width: 360px) {
      .logo-overlay {
        padding: 1.5rem;
        max-width: 280px;
      }
      
      .logo-overlay img {
        width: 110px;
      }
      
      .logo-overlay h1 {
        font-size: 1.1rem;
      }
      
      .logo-overlay p {
        font-size: 0.85rem;
      }
      
      .login-right {
        padding: 1.75rem 1rem;
      }
      
      .login-right h2 {
        font-size: 1.3rem;
      }
      
      .login-right .subtitle {
        font-size: 0.95rem;
      }
      
      .form-group input {
        padding: 0.8rem 1rem;
        font-size: 0.9rem;
      }
      
      .login-btn, .back-link {
        padding: 0.85rem;
        font-size: 0.95rem;
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
        padding: 1.75rem;
        max-width: 300px;
      }
      
      .logo-overlay img {
        width: 110px;
      }
      
      .logo-overlay h1 {
        font-size: 1.2rem;
      }
      
      .login-right h2 {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
      }
      
      .login-right .subtitle {
        font-size: 0.95rem;
        margin-bottom: 1.5rem;
      }
      
      .form-group {
        margin-bottom: 1rem;
      }
      
      .form-group input {
        padding: 0.75rem 1rem;
      }
      
      .login-btn, .back-link {
        padding: 0.85rem;
        font-size: 0.95rem;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-left">
      <div class="logo-overlay">
        <img src="../img/Without-Background.png" alt="Southwoods Logo">
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
