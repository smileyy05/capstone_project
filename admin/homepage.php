<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include PostgreSQL database connection
require_once __DIR__ . '/../DB/DB_connection.php';

$error = '';
$debug = ''; // Add debug info

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!$email || !$password) {
        $error = "Both fields are required!";
    } else {
        try {
            // Query admin by email
            $result = db_prepare("SELECT * FROM admins WHERE email = $1", [$email]);
            
            // Debug: Check if query worked
            if (!$result) {
                $error = "Database query failed: " . db_error();
            } elseif (db_num_rows($result) === 0) {
                $error = "No admin found with email: " . htmlspecialchars($email);
            } else {
                $admin = db_fetch_assoc($result);
                
                // Debug: Show password hash info (REMOVE THIS IN PRODUCTION!)
                $debug = "Found admin. Password hash starts with: " . substr($admin['password'], 0, 10);
                
                // Check if password is hashed or plain text
                if (substr($admin['password'], 0, 4) === '$2y$' || substr($admin['password'], 0, 4) === '$2a$') {
                    // Password is hashed, use password_verify
                    if (password_verify($password, $admin['password'])) {
                        $_SESSION['admin'] = $email;
                        $_SESSION['admin_id'] = $admin['id'];
                        header("Location: admin-dashboard.php");
                        exit;
                    } else {
                        $error = "Invalid password (hashed comparison failed)";
                    }
                } else {
                    // Password might be plain text, compare directly
                    if ($password === $admin['password']) {
                        $_SESSION['admin'] = $email;
                        $_SESSION['admin_id'] = $admin['id'];
                        header("Location: admin-dashboard.php");
                        exit;
                    } else {
                        $error = "Invalid password (plain text comparison failed)";
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Admin Login Error: " . $e->getMessage());
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login - Debug</title>
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
    }
    
    .login-left img {
      width: 100%;
      max-width: 650px;
      border-radius: 24px;
      object-fit: cover;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
      transition: transform 0.3s ease;
    }
    
    .login-left img:hover {
      transform: scale(1.02);
    }
    
    .login-right {
      flex: 1;
      background: linear-gradient(135deg, #1e5bb8 0%, #2563eb 100%);
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
    }
    
    .msg-debug {
      color: #0369a1;
      background: #e0f2fe;
      border-radius: 10px;
      padding: 1rem;
      margin-bottom: 1.5rem;
      text-align: center;
      font-weight: 600;
      font-size: 0.85rem;
      border: 2px solid #7dd3fc;
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
    
    @media (max-width: 1024px) {
      .login-container {
        flex-direction: column;
      }
      
      .login-left {
        min-height: 40vh;
        padding: 2rem;
      }
      
      .login-left img {
        max-width: 500px;
      }
      
      .login-right {
        padding: 3rem 2rem;
      }
    }
    
    @media (max-width: 768px) {
      .login-left {
        min-height: 30vh;
        padding: 1.5rem;
      }
      
      .login-left img {
        max-width: 400px;
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
      <img src="southwoods_bg.jpg" alt="Southwoods Mall" onerror="this.src='https://images.unsplash.com/photo-1555529669-e69e7aa0ba9a?w=800&h=600&fit=crop'">
    </div>
    
    <div class="login-right">
      <div class="login-form-wrapper">
        <h2>Southwoods Smart Parking System</h2>
        <p class="subtitle">Admin Login (Debug Mode)</p>
        
        <?php if($debug): ?>
          <div class="msg-debug"><?php echo htmlspecialchars($debug); ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
          <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" autocomplete="off">
          <div class="form-group">
            <label for="email">Email Address*</label>
            <input type="email" id="email" name="email" placeholder="Enter your Email Address" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
          </div>
          
          <div class="form-group">
            <label for="password">Password*</label>
            <input type="password" id="password" name="password" placeholder="Enter your Password" required>
          </div>
          
          <button type="submit" class="login-btn">Login</button>
          <a href="/index.php" class="back-link">Back to Main</a>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
