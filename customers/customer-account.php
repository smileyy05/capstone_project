<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['customer_id'])) {
    header('Location: customer-login.php');
    exit();
}

// Retrieve customer data
$customer_id = $_SESSION['customer_id'];
$customer_name = $_SESSION['customer_name'];
$customer_email = $_SESSION['customer_email'];
$plate_number = $_SESSION['plate_number'];
$vehicle_type = $_SESSION['vehicle_type'];
$balance = $_SESSION['balance'] ?? 0.00;

// Include PostgreSQL database connection
require_once __DIR__ . '/../DB/DB_connection.php';

// QR data (unique combination)
$qr_data = "CUSTOMER_ID:" . $customer_id . "|PLATE:" . $plate_number . "|NAME:" . $customer_name;

// Check existing QR code in database
$existing_qr = '';
try {
    $result = db_prepare("SELECT qr_code FROM customers WHERE id = $1", [$customer_id]);
    
    if ($result && db_num_rows($result) > 0) {
        $row = db_fetch_assoc($result);
        $existing_qr = $row['qr_code'] ?? '';
    }
} catch (Exception $e) {
    error_log("QR Fetch Error: " . $e->getMessage());
}

// Define QR folder for local storage
$qr_folder = __DIR__ . "/assets/customers_QRcodes/";
$safe_filename = "qr_" . $customer_id . "_" . preg_replace("/[^A-Za-z0-9]/", "_", $customer_name);
$qr_filename = $qr_folder . $safe_filename . ".png";
$qr_relative_path = "assets/customers_QRcodes/" . $safe_filename . ".png";

$qr_generated = false;
$qr_url = "";
$use_online_qr = false;

// Check if QR needs to be generated
$needs_generation = empty($existing_qr) || 
                    $existing_qr === 'PENDING_QR_GENERATION' || 
                    $existing_qr === 'TEMP_QR_PENDING' ||
                    (strpos($existing_qr, 'assets/') === 0 && !file_exists($qr_filename));

if ($needs_generation) {
    // Try local generation first
    $qrlib_path = __DIR__ . '/phpqrcode/qrlib.php';
    
    if (file_exists($qrlib_path)) {
        require_once $qrlib_path;
        
        // Create folder if not exists
        if (!file_exists($qr_folder)) {
            @mkdir($qr_folder, 0777, true);
        }
        
        try {
            if (class_exists('QRcode')) {
                // Remove old file if exists
                if (file_exists($qr_filename)) {
                    @unlink($qr_filename);
                }
                
                // Generate new QR code
                if (is_writable($qr_folder)) {
                    QRcode::png($qr_data, $qr_filename, QR_ECLEVEL_M, 10, 2);
                    
                    if (file_exists($qr_filename) && filesize($qr_filename) > 100) {
                        $qr_generated = true;
                        $qr_url = $qr_relative_path . "?v=" . time();
                        
                        // Update database
                        db_prepare("UPDATE customers SET qr_code = $1 WHERE id = $2", 
                            [$qr_relative_path, $customer_id]);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("QR Generation Error: " . $e->getMessage());
        }
    }
    
    // Fallback to online QR API if local generation failed
    if (!$qr_generated) {
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qr_data);
        $use_online_qr = true;
        
        // Save online QR reference to database
        $online_qr_path = "ONLINE_QR:" . $qr_data;
        try {
            db_prepare("UPDATE customers SET qr_code = $1 WHERE id = $2", 
                [$online_qr_path, $customer_id]);
        } catch (Exception $e) {
            error_log("QR DB Update Error: " . $e->getMessage());
        }
    }
} else {
    // Use existing QR code
    if (strpos($existing_qr, 'ONLINE_QR:') === 0) {
        // Extract data from stored online QR
        $stored_data = substr($existing_qr, 10);
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($stored_data);
        $use_online_qr = true;
    } else if (file_exists($qr_filename)) {
        // Local file exists
        $qr_url = $qr_relative_path . "?v=" . filemtime($qr_filename);
    } else {
        // File doesn't exist, regenerate online
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qr_data);
        $use_online_qr = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - Parking System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #e8eef5 0%, #b8d4f1 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .account-container {
            background: #fff;
            padding: 3rem;
            border-radius: 25px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 550px;
            text-align: center;
        }
        h1 { 
            font-size: 2rem; 
            color: #1e5bb8; 
            margin-bottom: 2rem;
            font-weight: 700;
        }
        h3 {
            font-size: 1.3rem;
            color: #1e5bb8;
            margin: 1.5rem 0 1rem 0;
            font-weight: 600;
        }
        .info-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .info-row { 
            display: flex; 
            justify-content: space-between;
            align-items: center;
            font-size: 1.05rem; 
            margin-bottom: 0.75rem;
            padding: 0.5rem;
        }
        .info-row:last-child {
            margin-bottom: 0;
        }
        .info-row label { 
            font-weight: 600; 
            color: #1e5bb8;
        }
        .info-row span { 
            color: #4a5568; 
            font-weight: 500;
        }
        .balance-box {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border: 3px solid #6ee7b7;
            padding: 1.25rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            font-size: 1.4rem;
            font-weight: 700;
            color: #065f46;
        }
        .qr-code-box {
            border: 3px solid #3b82f6;
            padding: 20px;
            border-radius: 16px;
            display: inline-block;
            background: #fff;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        .qr-code-box img {
            display: block;
            border-radius: 8px;
            width: 200px;
            height: 200px;
        }
        .qr-instruction {
            font-size: 0.95rem;
            color: #64748b;
            margin-bottom: 1.5rem;
            padding: 0.75rem;
            background: #f1f5f9;
            border-radius: 8px;
        }
        .btn {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            margin-top: 0.75rem;
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-block;
            letter-spacing: 0.3px;
        }
        .logout-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #fff;
        }
        .logout-btn:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(220, 38, 38, 0.4);
        }
        .btn:active {
            transform: translateY(0);
        }
        @media (max-width: 600px) {
            .account-container { padding: 2rem 1.5rem; }
            h1 { font-size: 1.75rem; }
            .info-row { 
                font-size: 0.95rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
            .balance-box { font-size: 1.2rem; }
            .qr-code-box {
                padding: 15px;
            }
            .qr-code-box img {
                width: 180px;
                height: 180px;
            }
        }
    </style>
</head>
<body>
<div class="account-container">
    <h1>Welcome, <?= htmlspecialchars($customer_name) ?>!</h1>

    <div class="info-section">
        <div class="info-row">
            <label>Email:</label>
            <span><?= htmlspecialchars($customer_email) ?></span>
        </div>
        <div class="info-row">
            <label>Plate Number:</label>
            <span><?= htmlspecialchars($plate_number) ?></span>
        </div>
        <div class="info-row">
            <label>Vehicle Type:</label>
            <span><?= htmlspecialchars($vehicle_type) ?></span>
        </div>
    </div>

    <div class="balance-box">
        Balance: ₱<?= number_format($balance, 2) ?>
    </div>

    <h3>Your QR Code</h3>
    <div class="qr-code-box">
        <img src="<?= htmlspecialchars($qr_url) ?>" alt="QR Code" loading="lazy" onerror="this.src='https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= urlencode($qr_data) ?>'">
    </div>
    
    <div class="qr-instruction">
        📱 Show this QR code at the parking entrance to check in
    </div>
    
    <button class="btn logout-btn" onclick="location.href='customer-logout.php'">🔒 Logout</button>
</div>
</body>
</html>
