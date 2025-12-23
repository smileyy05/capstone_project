<?php
// QR data (unique combination)
$qr_data = "CUSTOMER_ID:" . $customer_id . "|PLATE:" . $plate_number . "|NAME:" . $customer_name;

// Define QR folder for local storage (optional)
$qr_folder = __DIR__ . "/assets/customers_QRcodes/";
$safe_filename = "qr_" . $customer_id . "_" . preg_replace("/[^A-Za-z0-9]/", "_", $customer_name);
$qr_filename = $qr_folder . $safe_filename . ".png";
$qr_relative_path = "assets/customers_QRcodes/" . $safe_filename . ".png";

$qr_generated = false;
$qr_url = "";

// Try to include PHP QR Code library
$qrlib_path = __DIR__ . '/phpqrcode/qrlib.php';

if (file_exists($qrlib_path)) {
    require_once $qrlib_path;
    
    // Create folder if not exists
    if (!file_exists($qr_folder)) {
        @mkdir($qr_folder, 0777, true);
    }
    
    try {
        // Check if QR library is properly loaded
        if (class_exists('QRcode')) {
            // Generate QR code if it doesn't exist
            if (!file_exists($qr_filename) || filesize($qr_filename) < 100) {
                // Remove old/invalid file
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
            } else {
                // Existing valid QR code
                $qr_generated = true;
                $qr_url = $qr_relative_path . "?v=" . time();
            }
        }
    } catch (Exception $e) {
        error_log("QR Generation Error: " . $e->getMessage());
    }
}

// Fallback to online QR API if local generation failed
if (!$qr_generated || empty($qr_url)) {
    // Use reliable online QR code API
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
?>
