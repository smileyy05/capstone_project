<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Southwoods Smart Parking System</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      background: url('/img/southwoods_bg.jpg') no-repeat center center fixed;
      background-size: cover;
      background-color: #e5e7eb;
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      position: relative;
    }
    
    body::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.3);
      z-index: 0;
    }
    
    .main-landing {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 24px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
      padding: 3.5rem 3rem;
      max-width: 480px;
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1rem;
      position: relative;
      z-index: 1;
      animation: fadeIn 0.5s ease;
    }
    
    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .main-landing .logo {
      width: 140px;
      height: auto;
      margin-bottom: 0.5rem;
      transition: transform 0.3s ease;
      background-color: #f3f4f6;
      padding: 10px;
      border-radius: 8px;
    }
    
    .main-landing .logo:hover {
      transform: scale(1.05);
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
    
    /* Tablet Landscape */
    @media (max-width: 1024px) {
      body {
        padding: 15px;
      }
      
      .main-landing {
        padding: 3rem 2.5rem;
        max-width: 450px;
      }
      
      .main-landing h1 {
        font-size: 1.65rem;
      }
    }
    
    /* Tablet Portrait */
    @media (max-width: 768px) {
      body {
        padding: 15px;
      }
      
      .main-landing {
        padding: 2.75rem 2rem;
        border-radius: 22px;
        max-width: 420px;
      }
      
      .main-landing .logo {
        width: 130px;
      }
      
      .main-landing h1 {
        font-size: 1.55rem;
      }
      
      .main-landing p {
        font-size: 0.98rem;
        margin-bottom: 1.75rem;
      }
      
      .main-landing .btn-group {
        gap: 0.9rem;
      }
      
      .main-landing .btn-group button {
        padding: 0.95rem 1.75rem;
        font-size: 0.98rem;
      }
    }
    
    /* Mobile Large */
    @media (max-width: 600px) {
      body {
        padding: 12px;
      }
      
      .main-landing {
        padding: 2.5rem 1.5rem;
        border-radius: 20px;
        max-width: 380px;
      }
      
      .main-landing .logo {
        width: 120px;
      }
      
      .main-landing h1 { 
        font-size: 1.5rem;
        letter-spacing: 0.3px;
      }
      
      .main-landing p {
        font-size: 0.95rem;
        margin-bottom: 1.5rem;
      }
      
      .main-landing .btn-group {
        gap: 0.85rem;
      }
      
      .main-landing .btn-group button {
        padding: 0.875rem 1.5rem;
        font-size: 0.95rem;
      }
    }
    
    /* Mobile Medium */
    @media (max-width: 480px) {
      body {
        padding: 10px;
      }
      
      .main-landing {
        padding: 2.25rem 1.25rem;
        border-radius: 18px;
        max-width: 340px;
      }
      
      .main-landing .logo {
        width: 110px;
        margin-bottom: 0.4rem;
      }
      
      .main-landing h1 {
        font-size: 1.35rem;
        letter-spacing: 0.2px;
        margin-bottom: 0.2rem;
      }
      
      .main-landing p {
        font-size: 0.9rem;
        margin-bottom: 1.4rem;
      }
      
      .main-landing .btn-group {
        gap: 0.8rem;
      }
      
      .main-landing .btn-group button {
        padding: 0.85rem 1.25rem;
        font-size: 0.92rem;
        border-radius: 10px;
      }
    }
    
    /* Mobile Small */
    @media (max-width: 360px) {
      body {
        padding: 8px;
      }
      
      .main-landing {
        padding: 2rem 1rem;
        border-radius: 16px;
        max-width: 310px;
      }
      
      .main-landing .logo {
        width: 100px;
        margin-bottom: 0.3rem;
      }
      
      .main-landing h1 {
        font-size: 1.25rem;
        letter-spacing: 0.1px;
      }
      
      .main-landing p {
        font-size: 0.85rem;
        margin-bottom: 1.25rem;
      }
      
      .main-landing .btn-group {
        gap: 0.75rem;
      }
      
      .main-landing .btn-group button {
        padding: 0.8rem 1rem;
        font-size: 0.88rem;
        border-radius: 10px;
      }
    }
    
    /* Very Small Devices */
    @media (max-width: 320px) {
      body {
        padding: 6px;
      }
      
      .main-landing {
        padding: 1.75rem 0.85rem;
        border-radius: 14px;
        max-width: 290px;
        gap: 0.85rem;
      }
      
      .main-landing .logo {
        width: 90px;
        margin-bottom: 0.25rem;
      }
      
      .main-landing h1 {
        font-size: 1.15rem;
        letter-spacing: 0;
      }
      
      .main-landing p {
        font-size: 0.8rem;
        margin-bottom: 1.1rem;
      }
      
      .main-landing .btn-group {
        gap: 0.7rem;
      }
      
      .main-landing .btn-group button {
        padding: 0.75rem 0.85rem;
        font-size: 0.85rem;
        border-radius: 9px;
      }
    }
    
    /* Landscape orientation for mobile */
    @media (max-height: 600px) and (orientation: landscape) {
      body {
        padding: 10px;
      }
      
      .main-landing {
        padding: 1.75rem 2rem;
        max-width: 500px;
        gap: 0.6rem;
      }
      
      .main-landing .logo {
        width: 90px;
        margin-bottom: 0.25rem;
      }
      
      .main-landing h1 {
        font-size: 1.3rem;
        margin-bottom: 0.15rem;
      }
      
      .main-landing p {
        font-size: 0.9rem;
        margin-bottom: 1rem;
      }
      
      .main-landing .btn-group {
        gap: 0.65rem;
      }
      
      .main-landing .btn-group button {
        padding: 0.7rem 1.5rem;
        font-size: 0.9rem;
      }
    }
    
    /* Extra large screens */
    @media (min-width: 1440px) {
      .main-landing {
        max-width: 520px;
        padding: 4rem 3.5rem;
      }
      
      .main-landing .logo {
        width: 160px;
      }
      
      .main-landing h1 {
        font-size: 1.9rem;
      }
      
      .main-landing p {
        font-size: 1.1rem;
      }
      
      .main-landing .btn-group button {
        padding: 1.1rem 2.25rem;
        font-size: 1.05rem;
      }
    }
  </style>
</head>
<body>
  <div class="main-landing">
    <img src="/img/Without-Background.png" class="logo">
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

