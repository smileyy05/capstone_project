<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/verify_qr_errors.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

require_once '../DB/DB_connection.php';

function send_json($data) {
    ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit();
}

function debug_log($message, $data = null) {
    $log_file = __DIR__ . '/verify_qr_debug.log';
    $log = "[" . date('Y-m-d H:i:s') . "] $message";
    if ($data) $log .= ": " . json_encode($data);
    file_put_contents($log_file, $log . "\n", FILE_APPEND);
}

try {
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    $qr_code = isset($input['qr_code']) ? trim($input['qr_code']) : '';
    $qr_code = preg_replace('/[\x00-\x1F\x7F]/', '', $qr_code);
    $qr_code = trim($qr_code);
    
    debug_log('QR Code received', ['qr' => $qr_code, 'length' => strlen($qr_code)]);
    
    if (empty($qr_code)) {
        send_json(['success' => false, 'message' => 'QR code is required']);
    }
    
    // CRITICAL: Try to extract customer ID from different QR formats
    $customer_id_from_qr = null;
    
    // Format 1: CNLINE_QR:CUSTOMER_ID:19|PLATE:xxx
    if (preg_match('/CUSTOMER_ID:(\d+)/', $qr_code, $matches)) {
        $customer_id_from_qr = $matches[1];
        debug_log('Extracted customer ID from CNLINE format', $customer_id_from_qr);
    }
    
    // Try multiple search strategies
    $customer = null;
    
    // Strategy 1: Exact QR match
    $sql = "SELECT * FROM customers WHERE qr_code = $1 AND (archived = false OR archived = 0) LIMIT 1";
    $result = db_prepare($sql, [$qr_code]);
    if ($result && db_num_rows($result) > 0) {
        $customer = db_fetch_assoc($result);
        debug_log('Found via exact match');
    }
    
    // Strategy 2: Search by extracted customer ID
    if (!$customer && $customer_id_from_qr) {
        $sql = "SELECT * FROM customers WHERE id = $1 AND (archived = false OR archived = 0) LIMIT 1";
        $result = db_prepare($sql, [$customer_id_from_qr]);
        if ($result && db_num_rows($result) > 0) {
            $customer = db_fetch_assoc($result);
            debug_log('Found via customer ID extraction', $customer_id_from_qr);
        }
    }
    
    // Strategy 3: Partial QR match (if QR contains part of stored QR)
    if (!$customer) {
        $sql = "SELECT * FROM customers WHERE qr_code LIKE $1 AND (archived = false OR archived = 0) LIMIT 1";
        $result = db_prepare($sql, ['%' . $qr_code . '%']);
        if ($result && db_num_rows($result) > 0) {
            $customer = db_fetch_assoc($result);
            debug_log('Found via partial match');
        }
    }
    
    if (!$customer) {
        debug_log('Customer not found');
        send_json([
            'success' => false,
            'message' => 'Customer not registered in the system. Please register first at the admin desk.'
        ]);
    }
    
    // Check balance
    if ($customer['balance'] < 30) {
        send_json([
            'success' => false,
            'message' => 'Insufficient balance. Your balance is ₱' . number_format($customer['balance'], 2) . '. Minimum required: ₱30.00.'
        ]);
    }
    
    // Check if already inside
    $check_sql = "SELECT id, entry_time FROM parking_logs WHERE customer_id = $1 AND exit_time IS NULL ORDER BY entry_time DESC LIMIT 1";
    $check_result = db_prepare($check_sql, [$customer['id']]);
    
    if ($check_result && db_num_rows($check_result) > 0) {
        $active_entry = db_fetch_assoc($check_result);
        send_json([
            'success' => false,
            'message' => 'You have already entered at ' . date('g:i A', strtotime($active_entry['entry_time'])) . '. Please exit first.',
            'already_inside' => true
        ]);
    }
    
    // Success
    debug_log('Verification successful', $customer['id']);
    send_json([
        'success' => true,
        'message' => 'Customer verified successfully',
        'customer' => [
            'id' => $customer['id'],
            'name' => $customer['name'],
            'email' => $customer['email'],
            'plate' => $customer['plate'],
            'vehicle' => $customer['vehicle'],
            'qr_code' => $customer['qr_code'],
            'balance' => floatval($customer['balance']),
            'created_at' => $customer['created_at']
        ]
    ]);
    
} catch (Exception $e) {
    debug_log('Exception', $e->getMessage());
    send_json(['success' => false, 'message' => 'An error occurred']);
}
?>
