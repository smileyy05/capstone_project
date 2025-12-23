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
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
require_once '../DB/DB_connection.php';

// Helper function to send JSON response and exit
function send_json($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit();
}

// Get raw input
$raw_input = file_get_contents('php://input');

// Get JSON input
$input = json_decode($raw_input, true);

// Extract and clean QR code
$qr_code = isset($input['qr_code']) ? trim($input['qr_code']) : '';

// Remove any hidden characters, newlines, and extra spaces
$qr_code = preg_replace('/\s+/', ' ', $qr_code);
$qr_code = trim($qr_code);

if (empty($qr_code)) {
    send_json([
        'success' => false,
        'message' => 'QR code is required'
    ]);
}

// Try multiple query approaches to find the customer
// Approach 1: Exact match
$sql = "SELECT id, name, email, plate, vehicle, qr_code, balance, created_at, archived
        FROM customers 
        WHERE TRIM(qr_code) = ? AND archived = 0
        LIMIT 1";

$result = db_prepare($sql, [$qr_code]);

// If exact match fails, try case-insensitive match
if (!$result || db_num_rows($result) === 0) {
    $sql = "SELECT id, name, email, plate, vehicle, qr_code, balance, created_at, archived
            FROM customers 
            WHERE LOWER(TRIM(qr_code)) = LOWER(?) AND archived = 0
            LIMIT 1";
    
    $result = db_prepare($sql, [$qr_code]);
}

// If still no match, try with BINARY comparison removed
if (!$result || db_num_rows($result) === 0) {
    $sql = "SELECT id, name, email, plate, vehicle, qr_code, balance, created_at, archived
            FROM customers 
            WHERE qr_code = ? AND archived = 0
            LIMIT 1";
    
    $result = db_prepare($sql, [$qr_code]);
}

if (!$result) {
    $error = db_error();
    send_json([
        'success' => false,
        'message' => 'Database error: ' . htmlspecialchars($error)
    ]);
}

$num_rows = db_num_rows($result);

if ($num_rows === 0) {
    send_json([
        'success' => false,
        'message' => 'Customer not registered in the system. Please register first at the admin desk.'
    ]);
}

// Customer exists
$customer = db_fetch_assoc($result);
$customer_id = $customer['id'];

// Check if customer has sufficient balance (minimum ₱30)
if ($customer['balance'] < 30) {
    send_json([
        'success' => false,
        'message' => 'Insufficient balance. Your balance is ₱' . number_format($customer['balance'], 2) . '. Minimum required: ₱30.00. Please reload your account.'
    ]);
}

// Check for active parking entry (customer already inside)
$check_sql = "SELECT id, entry_time 
              FROM parking_logs 
              WHERE customer_id = ? AND exit_time IS NULL
              ORDER BY entry_time DESC 
              LIMIT 1";

$check_result = db_prepare($check_sql, [$customer_id]);

if (!$check_result) {
    send_json([
        'success' => false,
        'message' => 'Database error: ' . htmlspecialchars(db_error())
    ]);
}

if (db_num_rows($check_result) > 0) {
    $active_entry = db_fetch_assoc($check_result);
    send_json([
        'success' => false,
        'message' => 'You have already entered the parking lot at ' . date('g:i A', strtotime($active_entry['entry_time'])) . '. Please exit first before entering again.',
        'already_inside' => true,
        'entry_time' => $active_entry['entry_time']
    ]);
}

// Return success with customer data
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
        'balance' => $customer['balance'],
        'created_at' => $customer['created_at']
    ]
]);
?>
