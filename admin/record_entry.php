<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Include database connection
require_once '../DB/DB_connection.php';

$data = json_decode(file_get_contents("php://input"), true);
$customer_id = $data['customer_id'] ?? null;
$qr_code = isset($data['qr_code']) ? trim($data['qr_code']) : null;

if (!$customer_id || !$qr_code) {
    echo json_encode(["success" => false, "message" => "Customer ID and QR code required"]);
    exit;
}

// Get customer details with cleaned QR code matching
$customer_sql = "SELECT id, name, email, plate, vehicle, balance, qr_code 
                 FROM customers 
                 WHERE id = $1 AND archived = 0
                 LIMIT 1";
$customer_result = db_prepare($customer_sql, [$customer_id]);

if (!$customer_result || db_num_rows($customer_result) === 0) {
    echo json_encode(["success" => false, "message" => "Customer not found"]);
    exit;
}

$customer = db_fetch_assoc($customer_result);

// Check if customer balance is sufficient (minimum ₱30)
if ($customer['balance'] < 30) {
    echo json_encode([
        "success" => false,
        "message" => "Insufficient balance. Please reload your account."
    ]);
    exit;
}

// Prevent duplicate active entry
$check_sql = "SELECT id, entry_time FROM parking_logs 
              WHERE customer_id = $1 AND exit_time IS NULL
              ORDER BY entry_time DESC 
              LIMIT 1";
$check_result = db_prepare($check_sql, [$customer_id]);

if ($check_result && db_num_rows($check_result) > 0) {
    $active_entry = db_fetch_assoc($check_result);
    echo json_encode([
        "success" => false,
        "message" => "You have already entered the parking lot at " . date('g:i A', strtotime($active_entry['entry_time'])) . ". Please exit first."
    ]);
    exit;
}

// Check available parking spaces
$slots_sql = "SELECT available_spaces FROM parking_slots WHERE id = 1";
$slots_result = db_query($slots_sql);

if ($slots_result) {
    $slots = db_fetch_assoc($slots_result);
    if ($slots && $slots['available_spaces'] <= 0) {
        echo json_encode([
            "success" => false,
            "message" => "No available parking spaces"
        ]);
        exit;
    }
}

// Start transaction
if (!db_begin_transaction()) {
    echo json_encode(["success" => false, "message" => "Failed to start transaction"]);
    exit;
}

try {
    // Insert parking log and get ID
    $insert_sql = "INSERT INTO parking_logs
                   (customer_id, customer_name, plate, vehicle, entry_time, fee)
                   VALUES ($1, $2, $3, $4, NOW(), 0.00)
                   RETURNING id";
    
    $log_id = db_insert_id($insert_sql, [
        $customer_id,
        $customer['name'],
        $customer['plate'],
        $customer['vehicle']
    ]);
    
    if (!$log_id) {
        throw new Exception("Failed to insert parking log: " . db_error());
    }
    
    // Update parking slots - decrease available, increase occupied
    $update_slots_sql = "UPDATE parking_slots 
                         SET available_spaces = available_spaces - 1,
                             occupied_spaces = occupied_spaces + 1
                         WHERE id = 1 AND available_spaces > 0";
    
    if (!db_query($update_slots_sql)) {
        throw new Exception("Failed to update parking slots: " . db_error());
    }
    
    // Commit transaction
    if (!db_commit()) {
        throw new Exception("Failed to commit transaction");
    }
    
    // ========== INSERT ARDUINO COMMAND (AFTER SUCCESSFUL ENTRY) ==========
    try {
        $arduino_sql = "INSERT INTO arduino_commands 
                        (customer_id, customer_name, qr_code, action, station) 
                        VALUES ($1, $2, $3, $4, $5)";
        
        $arduino_result = db_prepare($arduino_sql, [
            $customer_id,
            $customer['name'],
            $qr_code,
            'OPEN',
            'entry'
        ]);
        
        if (!$arduino_result) {
            error_log("Arduino command insert failed: " . db_error());
            // Don't fail the entry if Arduino command fails
        }
    } catch (Exception $e) {
        error_log("Arduino command error: " . $e->getMessage());
        // Continue even if Arduino command fails
    }
    // ========== END ARDUINO COMMAND ==========
    
    echo json_encode([
        "success" => true,
        "message" => "Entry recorded successfully",
        "log_id" => $log_id,
        "customer" => [
            "id" => $customer['id'],
            "name" => $customer['name'],
            "email" => $customer['email'],
            "balance" => $customer['balance'],
            "qr_code" => $customer['qr_code'],
            "plate" => $customer['plate'],
            "vehicle" => $customer['vehicle']
        ]
    ]);
    
} catch (Exception $e) {
    db_rollback();
    echo json_encode([
        "success" => false,
        "message" => "Entry failed: " . $e->getMessage()
    ]);
}
?>
