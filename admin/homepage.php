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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            overflow: hidden;
            position: relative;
        }
        
        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .main-landing {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 32px;
            padding: 60px 50px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3);
            text-align: center;
            max-width: 520px;
            width: 100%;
            position: relative;
            z-index: 1;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: slideUp 0.6s ease;
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
        
        .logo {
            width: 140px;
            height: auto;
            margin-bottom: 0.5rem;
            transition: transform 0.3s ease;
            filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.15));
        }
        
        .logo:hover {
            transform: scale(1.05);
        }
        
        h1 {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            letter-spacing: 0.5px;
            line-height: 1.3;
        }
        
        p {
            color: #64748b;
            font-size: 1.05rem;
            margin-bottom: 2.5rem;
            font-weight: 500;
        }
        
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .btn {
            padding: 16px 32px;
            border: none;
            border-radius: 14px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .btn span {
            position: relative;
            z-index: 1;
        }
        
        .btn-register {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }
        
        .btn-register:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.5);
        }
        
        .btn-customer {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }
        
        .btn-customer:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.5);
        }
        
        .btn-admin {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-admin:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.5);
        }
        
        .btn:active {
            transform: translateY(-1px);
        }
        
        /* Responsive Design */
        @media (max-width: 640px) {
            .main-landing {
                padding: 45px 35px;
                border-radius: 24px;
            }
            
            .logo {
                width: 120px;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            p {
                font-size: 1rem;
                margin-bottom: 2rem;
            }
            
            .btn {
                padding: 14px 28px;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 400px) {
            .main-landing {
                padding: 35px 25px;
            }
            
            .logo {
                width: 100px;
            }
            
            h1 {
                font-size: 1.3rem;
            }
            
            p {
                font-size: 0.95rem;
            }
            
            .btn {
                padding: 12px 24px;
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-landing">
        <!-- Try different path formats -->
        <img src="img/Without-Background.png" alt="Southwoods Mall Logo" class="logo" 
             onerror="this.onerror=null; this.src='../img/Without-Background.png'; if(this.src.includes('../img/') && this.naturalWidth===0) {this.src='/img/Without-Background.png';} if(this.src.includes('/img/') && this.naturalWidth===0) {this.style.display='none'; this.nextElementSibling.style.display='block';}">
        
        <!-- Fallback text if image doesn't load -->
        <div style="display: none; width: 140px; height: 140px; margin: 0 auto 0.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px; display: none; align-items: center; justify-content: center; color: white; font-size: 2.5rem; font-weight: 900;">SW</div>
        
        <h1>SOUTHWOODS SMART PARKING SYSTEM</h1>
        <p>Welcome! Choose an option below to get started.</p>
        
        <div class="btn-group">
            <a href="customer-register.php" class="btn btn-register">
                <span>Register as Customer</span>
            </a>
            <a href="customer-login.php" class="btn btn-customer">
                <span>Customer Login</span>
            </a>
            <a href="admin/admin-login.php" class="btn btn-admin">
                <span>Admin Login</span>
            </a>
        </div>
    </div>
    
    <script>
        // Debug script to check image loading
        window.addEventListener('load', function() {
            const img = document.querySelector('.logo');
            if (img && img.naturalWidth === 0) {
                console.error('Image failed to load. Current src:', img.src);
                console.log('Try these paths:');
                console.log('1. img/Without-Background.png');
                console.log('2. ../img/Without-Background.png');
                console.log('3. /img/Without-Background.png');
                console.log('4. Check if file exists and name matches exactly (case-sensitive)');
            }
        });
    </script>
</body>
</html>
