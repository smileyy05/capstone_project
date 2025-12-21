<?php
// TESTING HELPER - Clear all parking logs to reset testing
header('Content-Type: application/json');

// Include database connection
require_once __DIR__ . '/../DB/DB_connection.php';

// Delete all parking logs
$delete_sql = "DELETE FROM parking_logs";
$result = db_query($delete_sql);

if ($result === false) {
    echo json_encode(['error' => 'Failed to clear parking logs']);
    exit;
}

// Reset parking slots
$reset_slots = "UPDATE parking_slots SET occupied_spaces = 0, available_spaces = 50 WHERE id = 1";
$result = db_query($reset_slots);

if ($result === false) {
    echo json_encode(['error' => 'Failed to reset parking slots']);
    exit;
}

// Reset customer balances
$reset_balance = "UPDATE customers SET balance = 500.00 WHERE archived = 0";
$result = db_query($reset_balance);

if ($result === false) {
    echo json_encode(['error' => 'Failed to reset customer balances']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'All parking logs cleared, slots reset to 50, balances reset to 500'
]);

?>
