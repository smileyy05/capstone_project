<?php
// Start output buffering to prevent stray output
ob_start();

// Disable notices/warnings in production
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
require_once __DIR__ . '/../DB/DB_connection.php';

// Helper function to send JSON response and exit
function send_json($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    ob_end_flush(); // flush output buffer
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$qr_code = isset($input['qr_code']) ? trim($input['qr_code']) : '';

if (empty($qr_code)) {
    send_json([
        'success' => false,
        'message' => 'QR code is required'
    ]);
}

// Prepare customer query
$sql = "SELECT id, name, email, plate, vehicle, qr_code, balance, created_at 
        FROM customers 
        WHERE qr_code = ? AND archived = 0
        LIMIT 1";

$result = db_prepare($sql, [$qr_code]);

if (!$result) {
    send_json([
        'success' => false,
        'message' => 'Database error: ' . htmlspecialchars(db_error())
    ]);
}

if (db_num_rows($result) === 0) {
    send_json([
        'success' => false,
        'message' => 'Customer not registered or account archived. Please register first.'
    ]);
}

// Customer exists
$customer = db_fetch_assoc($result);
$customer_id = $customer['id'];

// Check for active parking entry
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
