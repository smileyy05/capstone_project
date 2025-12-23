<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
require_once '../DB/DB_connection.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$qr_code = isset($input['qr_code']) ? $input['qr_code'] : '';

if (empty($qr_code)) {
    echo json_encode([
        'success' => false,
        'message' => 'QR code is required'
    ]);
    exit();
}

// Check if customer exists and is registered
$customer_sql = "SELECT id, name, email, balance FROM customers WHERE qr_code = ? AND archived = 0 LIMIT 1";
$customer_result = db_prepare($customer_sql, [$qr_code]);

if (!$customer_result || db_num_rows($customer_result) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Customer not registered in the system'
    ]);
    exit();
}

$customer = db_fetch_assoc($customer_result);

// Check if customer has an active entry (entered but not exited)
$log_sql = "SELECT id, entry_time FROM parking_logs 
            WHERE customer_id = ? AND status = 'entered' 
            ORDER BY entry_time DESC LIMIT 1";
$log_result = db_prepare($log_sql, [$customer['id']]);

if (!$log_result) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . db_error()
    ]);
    exit();
}

if (db_num_rows($log_result) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid exit. You have not entered the parking lot yet. Please tap in first at the entry station.'
    ]);
    exit();
}

$log = db_fetch_assoc($log_result);

// Calculate parking duration and fee with Manila timezone
date_default_timezone_set('Asia/Manila');

$entry_time = new DateTime($log['entry_time'], new DateTimeZone('Asia/Manila'));
$exit_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
$interval = $entry_time->diff($exit_time);

// Calculate total hours (including partial hours)
$total_hours = $interval->days * 24 + $interval->h + ($interval->i / 60);
$total_minutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

// Check if overnight parking applies
// Overnight: Entry between 11pm-11am next day
$entry_hour = (int)$entry_time->format('H');
$exit_hour = (int)$exit_time->format('H');
$is_overnight = false;

// Check if parked overnight (entered at/after 11pm and left at/before 11am next day)
if ($entry_hour >= 23 || ($entry_hour < 11 && $interval->days > 0)) {
    // Check if they left after 11am the next day
    if ($interval->days > 0 || ($entry_hour >= 23 && $exit_hour >= 11)) {
        $is_overnight = true;
    }
}

// Calculate fee based on new rates
// Regular: ₱25 for first 3 hours + ₱5 per succeeding hour
// Overnight: ₱150 + accumulated hourly fee
$fee = 0;

if ($total_hours <= 3) {
    // First 3 hours = ₱25 flat
    $fee = 25.00;
} else {
    // ₱25 for first 3 hours + ₱5 per hour after
    $hours_after_three = ceil($total_hours - 3);
    $fee = 25.00 + ($hours_after_three * 5.00);
}

// Add overnight fee if applicable
if ($is_overnight) {
    $fee += 150.00;
}

// Format duration text
if ($interval->days > 0) {
    $duration_text = sprintf("%d Day(s) %d Hour(s) %d Minute(s)", $interval->days, $interval->h, $interval->i);
} else if ($interval->h > 0) {
    $duration_text = sprintf("%d Hour(s) %d Minute(s)", $interval->h, $interval->i);
} else {
    $duration_text = sprintf("%d Minute(s)", $interval->i);
}

// Check if customer has sufficient balance
if ($customer['balance'] < $fee) {
    echo json_encode([
        'success' => false,
        'message' => 'Insufficient balance. Please load your account.'
    ]);
    exit();
}

// Generate exit token
$exit_token = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 9);

echo json_encode([
    'success' => true,
    'customer' => [
        'id' => $customer['id'],
        'name' => $customer['name'],
        'email' => $customer['email'],
        'balance' => $customer['balance']
    ],
    'log_id' => $log['id'],
    'entry_time' => $log['entry_time'],
    'exit_time' => $exit_time->format('Y-m-d H:i:s'),
    'fee' => $fee,
    'duration_text' => $duration_text,
    'exit_token' => $exit_token
]);
?>
