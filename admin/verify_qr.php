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
    http_response_code(200);
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
    
    // CRITICAL FIX: Use 0 instead of false for smallint column
    $customer = null;
    
    // Strategy 1: Exact QR match
    $sql = "SELECT id, name, email, plate, vehicle, qr_code, balance, created_at, archived
            FROM customers 
            WHERE qr_code = $1 AND archived = 0
            LIMIT 1";
    
    $result = db_prepare($sql, [$qr_code]);
    
    if ($result && db_num_rows($result) > 0) {
        $customer = db_fetch_assoc($result);
        debug_log('Found via exact match');
    }
    
    // Strategy 2: Trimmed match
    if (!$customer) {
        $sql = "SELECT id, name, email, plate, vehicle, qr_code, balance, created_at, archived
                FROM customers 
                WHERE TRIM(qr_code) = $1 AND archived = 0
                LIMIT 1";
        
        $result = db_prepare($sql, [$qr_code]);
        
        if ($result && db_num_rows($result) > 0) {
            $customer = db_fetch_assoc($result);
            debug_log('Found via trimmed match');
        }
    }
    
    // Strategy 3: Case-insensitive match
    if (!$customer) {
        $sql = "SELECT id, name, email, plate, vehicle, qr_code, balance, created_at, archived
                FROM customers 
                WHERE LOWER(TRIM(qr_code)) = LOWER($1) AND archived = 0
                LIMIT 1";
        
        $result = db_prepare($sql, [$qr_code]);
        
        if ($result && db_num_rows($result) > 0) {
            $customer = db_fetch_assoc($result);
            debug_log('Found via case-insensitive match');
        }
    }
    
    // Strategy 4: Extract customer ID from QR code formats like "CNLINE_QR:CUSTOMER_ID:19|PLATE:xxx"
    if (!$customer && preg_match('/CUSTOMER_ID:(\d+)/', $qr_code, $matches)) {
        $customer_id_from_qr = $matches[1];
        debug_log('Extracted customer ID from QR', $customer_id_from_qr);
        
        $sql = "SELECT id, name, email, plate, vehicle, qr_code, balance, created_at, archived
                FROM customers 
                WHERE id = $1 AND archived = 0
                LIMIT 1";
        
        $result = db_prepare($sql, [$customer_id_from_qr]);
        
        if ($result && db_num_rows($result) > 0) {
            $customer = db_fetch_assoc($result);
            debug_log('Found via customer ID extraction');
        }
    }
    
    // Strategy 5: Check if customer exists but is archived
    if (!$customer) {
        $sql = "SELECT id, name, email, qr_code, archived
                FROM customers 
                WHERE LOWER(TRIM(qr_code)) = LOWER($1)
                LIMIT 1";
        
        $result = db_prepare($sql, [$qr_code]);
        
        if ($result && db_num_rows($result) > 0) {
            $archived_customer = db_fetch_assoc($result);
            debug_log('Found archived customer', $archived_customer);
            
            send_json([
                'success' => false,
                'message' => 'Your account has been archived. Please contact the admin desk to reactivate your account.'
            ]);
        }
    }
    
    // No customer found
    if (!$customer) {
        debug_log('Customer not found with QR code', $qr_code);
        
        send_json([
            'success' => false,
            'message' => 'Customer not registered in the system. Please register first at the admin desk.'
        ]);
    }
    
    // Customer found - validate
    $customer_id = $customer['id'];
    debug_log('Customer found', ['id' => $customer_id, 'name' => $customer['name']]);
    
    // Check balance
    if ($customer['balance'] < 30) {
        debug_log('Insufficient balance', ['balance' => $customer['balance']]);
        send_json([
            'success' => false,
            'message' => 'Insufficient balance. Your balance is ₱' . number_format($customer['balance'], 2) . '. Minimum required: ₱30.00. Please reload your account.'
        ]);
    }
    
    // Check for active parking entry
    $check_sql = "SELECT id, entry_time 
                  FROM parking_logs 
                  WHERE customer_id = $1 AND exit_time IS NULL
                  ORDER BY entry_time DESC 
                  LIMIT 1";
    
    $check_result = db_prepare($check_sql, [$customer_id]);
    
    if (!$check_result) {
        debug_log('Failed to check active entries', db_error());
        send_json([
            'success' => false,
            'message' => 'Database error while checking parking status'
        ]);
    }
    
    if (db_num_rows($check_result) > 0) {
        $active_entry = db_fetch_assoc($check_result);
        debug_log('Customer already has active entry', $active_entry);
        
        send_json([
            'success' => false,
            'message' => 'You have already entered the parking lot at ' . date('g:i A', strtotime($active_entry['entry_time'])) . '. Please exit first before entering again.',
            'already_inside' => true,
            'entry_time' => $active_entry['entry_time']
        ]);
    }
    
    // All checks passed - return success
    debug_log('Verification successful', ['customer_id' => $customer_id]);
    
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
    debug_log('Exception caught', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    send_json([
        'success' => false,
        'message' => 'An error occurred while verifying the QR code. Please try again.'
    ]);
}
?>
