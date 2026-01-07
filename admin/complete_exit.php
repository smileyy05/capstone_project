<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
require_once __DIR__ . '/../DB/DB_connection.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$log_id = isset($input['log_id']) ? $input['log_id'] : '';
$customer_id = isset($input['customer_id']) ? $input['customer_id'] : '';

if (empty($log_id) || empty($customer_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Log ID and Customer ID are required'
    ]);
    exit();
}

// Start transaction
if (!db_begin_transaction()) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to start transaction'
    ]);
    exit();
}

try {
    // Get parking log details - Changed status check to use exit_time IS NULL
    $log_sql = "SELECT entry_time, customer_id, qr_code 
                FROM parking_logs 
                WHERE id = $1 AND exit_time IS NULL 
                LIMIT 1";
    $log_result = db_prepare($log_sql, [$log_id]);
    
    if (!$log_result || db_num_rows($log_result) === 0) {
        throw new Exception('Invalid parking log or already exited');
    }
    
    $log = db_fetch_assoc($log_result);
    
    // Verify customer ID matches
    if ($log['customer_id'] != $customer_id) {
        throw new Exception('Customer ID mismatch');
    }
    
    // Get customer name and QR code for Arduino command
    $customer_sql = "SELECT name, qr_code, balance FROM customers WHERE id = $1 LIMIT 1";
    $customer_result = db_prepare($customer_sql, [$customer_id]);
    
    if (!$customer_result || db_num_rows($customer_result) === 0) {
        throw new Exception('Customer not found');
    }
    
    $customer_data = db_fetch_assoc($customer_result);
    $customer_name = $customer_data['name'];
    $qr_code = $log['qr_code']; // Use QR from parking log
    
    // Calculate fee with Manila timezone
    date_default_timezone_set('Asia/Manila');
    
    $entry_time = new DateTime($log['entry_time'], new DateTimeZone('Asia/Manila'));
    $exit_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $interval = $entry_time->diff($exit_time);
    $total_hours = $interval->days * 24 + $interval->h + ($interval->i / 60);
    
    // Check overnight parking
    $entry_hour = (int)$entry_time->format('H');
    $exit_hour = (int)$exit_time->format('H');
    $is_overnight = false;
    
    if ($entry_hour >= 23 || ($entry_hour < 11 && $interval->days > 0)) {
        if ($interval->days > 0 || ($entry_hour >= 23 && $exit_hour >= 11)) {
            $is_overnight = true;
        }
    }
    
    // Calculate fee
    $fee = 0;
    if ($total_hours <= 3) {
        $fee = 25.00;
    } else {
        $hours_after_three = ceil($total_hours - 3);
        $fee = 25.00 + ($hours_after_three * 5.00);
    }
    
    if ($is_overnight) {
        $fee += 150.00;
    }
    
    // Format duration
    if ($interval->days > 0) {
        $duration_text = sprintf("%d Day(s) %d Hour(s) %d Minute(s)", $interval->days, $interval->h, $interval->i);
    } else if ($interval->h > 0) {
        $duration_text = sprintf("%d Hour(s) %d Minute(s)", $interval->h, $interval->i);
    } else {
        $duration_text = sprintf("%d Minute(s)", $interval->i);
    }
    
    // Check balance
    if ($customer_data['balance'] < $fee) {
        throw new Exception('Insufficient balance. Required: ₱' . number_format($fee, 2));
    }
    
    // Update parking log with exit time and fee
    $exit_time_str = $exit_time->format('Y-m-d H:i:s');
    $update_log_sql = "UPDATE parking_logs 
                       SET exit_time = $1, parking_fee = $2 
                       WHERE id = $3";
    $update_log_result = db_prepare($update_log_sql, [$exit_time_str, $fee, $log_id]);
    
    if (!$update_log_result) {
        throw new Exception('Failed to update parking log: ' . db_error());
    }
    
    // Deduct fee from customer balance
    $new_balance = $customer_data['balance'] - $fee;
    $update_balance_sql = "UPDATE customers SET balance = $1 WHERE id = $2";
    $update_balance_result = db_prepare($update_balance_sql, [$new_balance, $customer_id]);
    
    if (!$update_balance_result) {
        throw new Exception('Failed to update customer balance: ' . db_error());
    }
    
    // Update parking slots
    $update_slots_sql = "UPDATE parking_slots 
                         SET occupied_spaces = GREATEST(0, occupied_spaces - 1),
                             available_spaces = LEAST(50, available_spaces + 1)
                         WHERE id = 1";
    if (!db_query($update_slots_sql)) {
        throw new Exception('Failed to update parking slots: ' . db_error());
    }
    
    // Commit transaction
    if (!db_commit()) {
        throw new Exception('Failed to commit transaction');
    }
    
    // Insert Arduino command
    try {
        $arduino_sql = "INSERT INTO arduino_commands 
                        (customer_id, customer_name, qr_code, action, station) 
                        VALUES ($1, $2, $3, $4, $5)";
        
        $arduino_result = db_prepare($arduino_sql, [
            $customer_id,
            $customer_name,
            $qr_code,
            'OPEN',
            'exit'
        ]);
        
        if (!$arduino_result) {
            error_log("Arduino exit command insert failed: " . db_error());
        }
    } catch (Exception $e) {
        error_log("Arduino exit command error: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Exit completed successfully',
        'fee' => $fee,
        'duration_text' => $duration_text,
        'new_balance' => $new_balance
    ]);
    
} catch (Exception $e) {
    db_rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
