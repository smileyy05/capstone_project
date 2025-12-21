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
    // Get parking log details
    $log_sql = "SELECT entry_time, customer_id FROM parking_logs WHERE id = ? AND status = 'entered' LIMIT 1";
    $log_result = db_prepare($log_sql, [$log_id]);
    
    if (!$log_result || db_num_rows($log_result) === 0) {
        throw new Exception('Invalid parking log or already exited');
    }
    
    $log = db_fetch_assoc($log_result);
    
    // Verify customer ID matches
    if ($log['customer_id'] != $customer_id) {
        throw new Exception('Customer ID mismatch');
    }
    
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
    
    // Calculate fee: ₱25 for first 3 hours + ₱5 per succeeding hour
    $fee = 0;
    if ($total_hours <= 3) {
        $fee = 25.00;
    } else {
        $hours_after_three = ceil($total_hours - 3);
        $fee = 25.00 + ($hours_after_three * 5.00);
    }
    
    // Add overnight fee if applicable
    if ($is_overnight) {
        $fee += 150.00;
    }
    
    // Get customer balance
    $balance_sql = "SELECT balance FROM customers WHERE id = ? LIMIT 1";
    $balance_result = db_prepare($balance_sql, [$customer_id]);
    
    if (!$balance_result || db_num_rows($balance_result) === 0) {
        throw new Exception('Customer not found');
    }
    
    $customer_data = db_fetch_assoc($balance_result);
    
    if ($customer_data['balance'] < $fee) {
        throw new Exception('Insufficient balance');
    }
    
    // Update parking log with exit time and fee
    $update_log_sql = "UPDATE parking_logs 
                       SET exit_time = ?, status = 'exited', parking_fee = ? 
                       WHERE id = ?";
    $exit_time_str = $exit_time->format('Y-m-d H:i:s');
    $update_log_result = db_prepare($update_log_sql, [$exit_time_str, $fee, $log_id]);
    
    if (!$update_log_result) {
        throw new Exception('Failed to update parking log');
    }
    
    // Deduct fee from customer balance
    $new_balance = $customer_data['balance'] - $fee;
    $update_balance_sql = "UPDATE customers SET balance = ? WHERE id = ?";
    $update_balance_result = db_prepare($update_balance_sql, [$new_balance, $customer_id]);
    
    if (!$update_balance_result) {
        throw new Exception('Failed to update customer balance');
    }
    
    // Update parking slots - increase available by 1 (max 50)
    $update_slots_sql = "UPDATE parking_slots 
                         SET occupied_spaces = GREATEST(0, occupied_spaces - 1),
                             available_spaces = LEAST(50, available_spaces + 1)
                         WHERE id = 1";
    if (!db_query($update_slots_sql)) {
        throw new Exception('Failed to update parking slots');
    }
    
    // Commit transaction
    if (!db_commit()) {
        throw new Exception('Failed to commit transaction');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Exit completed successfully',
        'fee_charged' => $fee,
        'new_balance' => $new_balance,
        'slot_increased' => true
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    db_rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>