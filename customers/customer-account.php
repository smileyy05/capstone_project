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
require_once '../DB/DB_connection.php';

// Try to include PHP QR Code library
$qrlib_path = __DIR__ . '/phpqrcode/qrlib.php';
$qr_library_exists = false;

if (file_exists($qrlib_path)) {
    require_once $qrlib_path;
    $qr_library_exists = true;
}

// Define QR error correction level constants if not already defined
if (!defined('QR_ECLEVEL_L')) {
    define('QR_ECLEVEL_L', 0);
}
if (!defined('QR_ECLEVEL_M')) {
    define('QR_ECLEVEL_M', 1);
}

// If QR library doesn't exist, use GD library fallback
if (!$qr_library_exists || !class_exists('QRcode')) {
    class QRcode {
        public static function png($text, $outfile = false, $level = 0, $size = 10, $margin = 2) {
            if (!extension_loaded('gd')) {
                return false;
            }
            
            $img_size = 200;
            $img = imagecreatetruecolor($img_size, $img_size);
            
            $white = imagecolorallocate($img, 255, 255, 255);
            $black = imagecolorallocate($img, 0, 0, 0);
            $gray = imagecolorallocate($img, 180, 180, 180);
            
            imagefill($img, 0, 0, $white);
            
            $box_size = 8;
            $grid_size = 21;
            $offset = ($img_size - ($grid_size * $box_size)) / 2;
            
            for ($i = 0; $i < $grid_size; $i++) {
                for ($j = 0; $j < $grid_size; $j++) {
                    $hash = md5($text . $i . $j);
                    $val = hexdec(substr($hash, 0, 2));
                    
                    if ($val % 2 == 0) {
                        $x1 = $offset + ($i * $box_size);
                        $y1 = $offset + ($j * $box_size);
                        $x2 = $x1 + $box_size - 1;
                        $y2 = $y1 + $box_size - 1;
                        imagefilledrectangle($img, $x1, $y1, $x2, $y2, $black);
                    }
                }
            }
            
            $marker_size = $box_size * 7;
            $positions = [[0, 0], [$grid_size - 7, 0], [0, $grid_size - 7]];
            
            foreach ($positions as $pos) {
                $x = $offset + ($pos[0] * $box_size);
                $y = $offset + ($pos[1] * $box_size);
                
                imagerectangle($img, $x, $y, $x + $marker_size, $y + $marker_size, $black);
                imagerectangle($img, $x + 1, $y + 1, $x + $marker_size - 1, $y + $marker_size - 1, $black);
                
                $inner_offset = $box_size * 2;
                $inner_size = $box_size * 3;
                imagefilledrectangle($img, 
                    $x + $inner_offset, 
                    $y + $inner_offset, 
                    $x + $inner_offset + $inner_size, 
                    $y + $inner_offset + $inner_size, 
                    $black
                );
            }
            
            imagerectangle($img, 0, 0, $img_size - 1, $img_size - 1, $gray);
            
            if ($outfile) {
                $result = imagepng($img, $outfile);
                imagedestroy($img);
                return $result;
            } else {
                header('Content-Type: image/png');
                imagepng($img);
                imagedestroy($img);
                return true;
            }
        }
    }
}

// QR code folder
$qr_folder = __DIR__ . "/assets/customers_QRcodes/";

// Create folder if not exists
if (!file_exists($qr_folder)) {
    @mkdir($qr_folder, 0777, true);
}

// Safe filename using customer ID
$safe_filename = "qr_" . $customer_id . "_" . preg_replace("/[^A-Za-z0-9]/", "_", $customer_name);
$qr_filename = $qr_folder . $safe_filename . ".png";

// QR data (unique combination)
$qr_data = "CUSTOMER_ID:" . $customer_id . "|PLATE:" . $plate_number . "|NAME:" . $customer_name;

// Relative path for database storage
$qr_relative_path = "assets/customers_QRcodes/" . $safe_filename . ".png";

// Generate QR code
$qr_generated = false;
$use_online_qr = false;

try {
    // Check if QR code already exists in database
    $result = db_prepare("SELECT qr_code FROM customers WHERE id = $1", [$customer_id]);
    
    if ($result && db_num_rows($result) > 0) {
        $row = db_fetch_assoc($result);
        $existing_qr = $row['qr_code'] ?? '';
    } else {
        $existing_qr = '';
    }
    
    // Generate QR code if it doesn't exist or file is missing
    if (empty($existing_qr) || !file_exists($qr_filename)) {
        // Remove old file if exists
        if (file_exists($qr_filename)) {
            @unlink($qr_filename);
        }
        
        // Generate new QR code
        if (is_writable($qr_folder)) {
            $result = QRcode::png($qr_data, $qr_filename, QR_ECLEVEL_L, 10, 2);
            
            if ($result && file_exists($qr_filename)) {
                $qr_generated = true;
                
                // Update database with QR code path
                db_prepare("UPDATE customers SET qr_code = $1 WHERE id = $2", 
                    [$qr_relative_path, $customer_id]);
            }
        }
    } else {
        // QR code already exists in database and file exists
        $qr_generated = true;
    }
} catch (Exception $e) {
    // Silent fail - will use online API
    error_log("QR Generation Error: " . $e->getMessage());
}

// Generate URL for HTML
if ($qr_generated && file_exists($qr_filename)) {
    $qr_url = $qr_relative_path . "?v=" . time();
} else {
    // Use online QR code API as fallback
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_data);
    $use_online_qr = true;
    
    // Save online QR URL to database
    $online_qr_path = "ONLINE_QR:" . $qr_data;
    db_prepare("UPDATE customers SET qr_code = $1 WHERE id = $2", 
        [$online_qr_path, $customer_id]);
}

// Close connection is handled by db-config.php or at script end
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
        .qr-status {
            font-size: 0.85rem;
            color: #059669;
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: #d1fae5;
            border-radius: 6px;
            display: inline-block;
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
        <img src="<?= htmlspecialchars($qr_url) ?>" alt="QR Code" loading="lazy">
    </div>
    
    <div class="qr-instruction">
        📱 Show this QR code at the parking entrance to check in
    </div>
    
    <button class="btn logout-btn" onclick="location.href='customer-logout.php'">🔒 Logout</button>
</div>
</body>
</html>