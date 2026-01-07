<?php
// Start output buffering to prevent stray output
ob_start();

// Enable detailed error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/verify_qr_errors.log');

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
require_once '../DB/DB_connection.php';

// Helper function to send JSON response and exit
function send_json($data) {
    // Clean any output buffer before sending JSON
    ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    ob_end_flush();
    exit();
}

// Log function for debugging (will create a log file)
function debug_log($message, $data = null) {
    $log_file = __DIR__ . '/verify_qr_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message";
    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $log_message .= ": " . json_encode($data, JSON_PRETTY_PRINT);
        } else {
            $log_message .= ": " . $data;
        }
    }
    $log_message .= "\n" . str_repeat('-', 80) . "\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

try {
    // Get raw input
    $raw_input = file_get_contents('php://input');
    debug_log('Raw input received', $raw_input);

    // Get JSON input
    $input = json_decode($raw_input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        debug_log('JSON decode error', json_last_error_msg());
        send_json([
            'success' => false,
            'message' => 'Invalid JSON data'
        ]);
    }
    
    debug_log('Decoded JSON', $input);

    // Extract and clean QR code
    $qr_code = isset($input['qr_code']) ? $input['qr_code'] : '';
    
    // Remove any whitespace, newlines, and control characters
    $qr_code = trim($qr_code);
    $qr_code = preg_replace('/[\x00-\x1F\x7F]/', '', $qr_code); // Remove control characters
    $qr_code = preg_replace('/\s+/', ' ', $qr_code); // Normalize whitespace
    $qr_code = trim($qr_code);

    debug_log('QR Code Processing', [
        'original' => $input['qr_code'] ?? 'NOT SET',
        'cleaned' => $qr_code,
        'length' => strlen($qr_code),
        'bytes' => bin2hex($qr_code)
    ]);

    if (empty($qr_code)) {
        debug_log('Empty QR code received');
        send_json([
            'success' => false,
            'message' => 'QR code is required'
        ]);
    }

    // DIAGNOSTIC: Check what's in the database
    $diag_sql = "SELECT id, name, qr_code, 
                 LENGTH(qr_code) as qr_length, 
                 archived, balance,
                 ENCODE(qr_code::bytea, 'hex') as qr_hex
                 FROM customers 
                 WHERE archived = false OR archived = 0
                 LIMIT 5";
    
    try {
        $diag_result = db_query($diag_sql);
        if ($diag_result) {
            $sample_customers = db_fetch_all($diag_result);
            debug_log('Sample customers in database', $sample_customers);
        }
    } catch (Exception $e) {
        debug_log('Diagnostic query failed', $e->getMessage());
    }

    // Try to find the customer with multiple approaches
    $customer = null;
    $approach_used = '';

    // Approach 1: Exact match (PostgreSQL is case-sensitive by default)
    debug_log('Trying Approach 1: Exact match');
    $sql = "SELECT id, name, email, plate, vehicle, qr_code, balance, created_at, archived
            FROM customers 
            WHERE qr_code = $1 
            AND (archived = false OR archived = 0 OR archived IS NULL)
            LIMIT 1";
    
    $result = db_prepare($sql, [$qr_code]);
    
    if ($result && db_num_rows($result) > 0) {
        $customer = db_fetch_assoc($result);
        $approach_used = 'Exact match';
        debug_log('Found with approach 1', ['customer_id' => $customer['id'], 'name' => $customer['name']]);
    }

    // Approach 2: Trimmed match
    if (!$customer) {
        debug_log('Trying Approach 2: Trimmed match');
        $sql = "SELECT id, name, email, plate, vehicle, qr_code, balance, created_at, archived
                FROM customers 
                WHERE TRIM(qr_code) = $1 
                AND (archived = false OR archived = 0 OR archived IS NULL)
                LIMIT 1";
        
        $result = db_prepare($sql, [$qr_code]);
        
        if ($result && db_num_rows($result) > 0) {
            $customer = db_fetch_assoc($result);
            $approach_used = 'Trimmed match';
            debug_log('Found with approach 2', ['customer_id' => $customer['id'], 'name' => $customer['name']]);
        }
    }

    // Approach 3: Case-insensitive match
    if (!$customer) {
        debug_log('Trying Approach 3: Case-insensitive match');
        $sql = "SELECT id, name, email, plate, vehicle, qr_code, balance, created_at, archived
                FROM customers 
                WHERE LOWER(TRIM(qr_code)) = LOWER($1)
                AND (archived = false OR archived = 0 OR archived IS NULL)
                LIMIT 1";
        
        $result = db_prepare($sql, [$qr_code]);
        
        if ($result && db_num_rows($result) > 0) {
            $customer = db_fetch_assoc($result);
            $approach_used = 'Case-insensitive match';
            debug_log('Found with approach 3', ['customer_id' => $customer['id'], 'name' => $customer['name']]);
        }
    }

    // Approach 4: Check if customer exists but is archived
    if (!$customer) {
        debug_log('Trying Approach 4: Check archived customers');
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
                'message' => 'Your account has been archived. Please contact the admin desk to reactivate your account.',
                'debug_info' => [
                    'customer_found' => true,
                    'archived' => true,
                    'customer_name' => $archived_customer['name']
                ]
            ]);
        }
    }

    // No customer found
    if (!$customer) {
        debug_log('No customer found with QR code', $qr_code);
        
        // Get sample QR codes for comparison
        $sample_sql = "SELECT id, name, qr_code, LENGTH(qr_code) as len 
                       FROM customers 
                       WHERE archived = false OR archived = 0
                       LIMIT 3";
        $sample_result = db_query($sample_sql);
        $sample_qrs = [];
        
        if ($sample_result) {
            while ($row = db_fetch_assoc($sample_result)) {
                $sample_qrs[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'qr_code' => $row['qr_code'],
                    'length' => $row['len']
                ];
            }
        }
        
        debug_log('Sample QR codes in database', $sample_qrs);
        
        send_json([
            'success' => false,
            'message' => 'Customer not registered in the system. Please register first at the admin desk.',
            'debug_info' => [
                'qr_searched' => $qr_code,
                'qr_length' => strlen($qr_code),
                'total_active_customers' => count($sample_qrs)
            ]
        ]);
    }

    // Customer found - validate and process
    $customer_id = $customer['id'];
    debug_log('Customer found successfully', [
        'id' => $customer_id,
        'name' => $customer['name'],
        'approach' => $approach_used
    ]);

    // Check if customer has sufficient balance (minimum ₱30)
    if ($customer['balance'] < 30) {
        debug_log('Insufficient balance', ['balance' => $customer['balance']]);
        send_json([
            'success' => false,
            'message' => 'Insufficient balance. Your balance is ₱' . number_format($customer['balance'], 2) . '. Minimum required: ₱30.00. Please reload your account.'
        ]);
    }

    // Check for active parking entry (customer already inside)
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
    debug_log('All validations passed, returning success');
    
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
        ],
        'debug_info' => [
            'approach_used' => $approach_used
        ]
    ]);

} catch (Exception $e) {
    debug_log('Exception caught', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    send_json([
        'success' => false,
        'message' => 'An error occurred while verifying the QR code. Please try again.'
    ]);
}
?>
