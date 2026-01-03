<?php
session_start();

// Check if already logged in
if (isset($_SESSION['customer_id'])) {
    header("Location: customer-account.php");
    exit();
}

// Get error message from session if exists
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Login - Southwoods Parking</title>
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
      background: linear-gradient(135deg, rgba(5, 150, 105, 0.1) 0%, rgba(16, 185, 129, 0.1) 100%);
    }
    
    .logo-overlay {
      position: relative;
      z-index: 1;
      text-align: center;
      padding: 2rem;
      background: rgba(255, 255, 255, 0.95);
      border-radius: 24px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
      backdrop-filter: blur(10px);
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
    }
    
    .logo-overlay p {
      font-size: 1rem;
      color: #4a5568;
      font-weight: 500;
    }
    
    .login-right {
      flex: 1;
      background: linear-gradient(135deg, #059669 0%, #10b981 100%);
      color: #fff;
      padding: 3rem 3rem;
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
      background: linear-gradient(135deg, #047857 0%, #059669 100%);
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
      background: linear-gradient(135deg, #065f46 0%, #047857 100%);
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
    }
    
    .login-btn:active {
      transform: translateY(0);
    }
    
    .back-link, .register-link {
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
    
    .back-link:hover, .register-link:hover {
      background: rgba(255, 255, 255, 0.25);
      border-color: rgba(255, 255, 255, 0.5);
      transform: translateY(-2px);
    }
    
    .back-link::before {
      content: "←";
      font-size: 1.3rem;
    }
    
    @media (max-width: 1024px) {
      .login-container {
        flex-direction: column;
      }
      
      .login-left {
        min-height: 40vh;
        padding: 2rem;
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
    }
    
    @media (max-width: 768px) {
      .login-left {
        min-height: 35vh;
        padding: 1.5rem;
      }
      
      .logo-overlay {
        padding: 1.5rem;
      }
      
      .logo-overlay img {
        width: 120px;
      }
      
      .logo-overlay h1 {
        font-size: 1.2rem;
      }
      
      .logo-overlay p {
        font-size: 0.9rem;
      }
      
      .login-right {
        padding: 2.5rem 1.5rem;
      }
      
      .login-right h2 {
        font-size: 1.5rem;
      }
      
      .login-right .subtitle {
        font-size: 1rem;
        margin-bottom: 2rem;
      }
    }
    
    @media (max-width: 480px) {
      .login-right h2 {
        font-size: 1.3rem;
        letter-spacing: 1px;
      }
      
      .form-group input {
        padding: 0.85rem 1rem;
        font-size: 0.95rem;
      }
      
      .login-btn {
        padding: 1rem;
        font-size: 1rem;
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
      </div>
    </div>
    
    <div class="login-right">
      <div class="login-form-wrapper">
        <h2>Customer Login</h2>
        <p class="subtitle">Access Your Parking Account</p>
        
        <?php if($error): ?>
          <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form action="customer-login-process.php" method="POST" autocomplete="off">
          <div class="form-group">
            <label for="email">Email Address*</label>
            <input type="email" id="email" name="email" placeholder="Enter your Email Address" required>
          </div>
          
          <div class="form-group">
            <label for="password">Password*</label>
            <input type="password" id="password" name="password" placeholder="Enter your Password" required>
          </div>
          
          <button type="submit" class="login-btn">Login</button>
          <a href="customer-register.php" class="register-link">Don't have an account? Register</a>
          <a href="/index.php" class="back-link">Back to Main</a>
        </form>
      </div>
    </div>
  </div>
</body>
</html>

