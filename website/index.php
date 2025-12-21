<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Southwoods Smart Parking System</title>
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
    
    .main-landing {
      background: #ffffff;
      border-radius: 24px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
      padding: 3.5rem 3rem;
      max-width: 480px;
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1rem;
    }
    
    .main-landing .logo {
      width: 140px;
      height: auto;
      margin-bottom: 0.5rem;
    }
    
    .main-landing h1 {
      font-size: 1.75rem;
      color: #1e5bb8;
      font-weight: 700;
      text-align: center;
      margin-bottom: 0.25rem;
      letter-spacing: 0.5px;
      line-height: 1.3;
    }
    
    .main-landing p {
      color: #4a5568;
      font-size: 1rem;
      text-align: center;
      margin-bottom: 2rem;
      font-weight: 400;
    }
    
    .main-landing .btn-group {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      width: 100%;
    }
    
    .main-landing .btn-group button {
      width: 100%;
      padding: 1rem 2rem;
      font-size: 1rem;
      font-weight: 600;
      border-radius: 12px;
      border: none;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      letter-spacing: 0.3px;
    }
    
    .main-landing .btn-group .register-btn {
      background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
      color: #1e5bb8;
      font-weight: 700;
    }
    
    .main-landing .btn-group .register-btn:hover {
      background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(34, 197, 94, 0.3);
    }
    
    .main-landing .btn-group .login-btn {
      background: linear-gradient(135deg, #93c5fd 0%, #60a5fa 100%);
      color: #1e3a8a;
      font-weight: 600;
    }
    
    .main-landing .btn-group .login-btn:hover {
      background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(96, 165, 250, 0.3);
    }
    
    .main-landing .btn-group .admin-btn {
      background: linear-gradient(135deg, #3b82f6 0%, #1e5bb8 100%);
      color: #ffffff;
      font-weight: 600;
    }
    
    .main-landing .btn-group .admin-btn:hover {
      background: linear-gradient(135deg, #1e5bb8 0%, #1e40af 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(30, 91, 184, 0.4);
    }
    
    .main-landing .btn-group button:active {
      transform: translateY(0);
    }
    
    @media (max-width: 600px) {
      .main-landing {
        padding: 2.5rem 1.5rem;
        border-radius: 20px;
        max-width: 380px;
      }
      
      .main-landing h1 { 
        font-size: 1.5rem; 
      }
      
      .main-landing .logo { 
        width: 120px; 
      }
      
      .main-landing p {
        font-size: 0.95rem;
      }
      
      .main-landing .btn-group button {
        padding: 0.875rem 1.5rem;
        font-size: 0.95rem;
      }
    }
  </style>
</head>
<body>
  <div class="main-landing">
    <img src="Without-Background.png" alt="Southwoods Mall Logo" class="logo">
    <h1>SOUTHWOODS SMART PARKING SYSTEM</h1>
    <p>Welcome! Choose an option below to get started.</p>
    <div class="btn-group">
      <button class="register-btn" onclick="window.location.href='../customers/customer-register.php'">Register as Customer</button>
      <button class="login-btn" onclick="window.location.href='../customers/customer-login.php'">Customer Login</button>
      <button class="admin-btn" onclick="window.location.href='../admin/homepage.php'">Admin Login</button>
    </div>
  </div>
</body>
</html>