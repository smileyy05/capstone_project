<?php
// DEBUG HELPER - Check customer and parking log status
header('Content-Type: application/json');

// Include database connection
require_once '../DB/DB_connection.php';

// Get all customers with their latest parking log status
$sql = "SELECT 
            c.id as customer_id,
            c.name,
            c.email,
            c.qr_code,
            c.balance,
            pl.id as log_id,
            pl.entry_time,
            pl.exit_time,
            pl.status,
            pl.parking_fee,
            (SELECT COUNT(*) FROM parking_logs WHERE customer_id = c.id AND status = 'entered') as active_entries
        FROM customers c
        LEFT JOIN parking_logs pl ON c.id = pl.customer_id
        WHERE c.archived = 0
        ORDER BY c.id, pl.entry_time DESC";

$result = db_query($sql);

if ($result === false) {
    echo json_encode([
        'success' => false,
        'error' => 'Query failed: ' . db_error()
    ]);
    exit;
}

$data = db_fetch_all($result);

if ($data === false) {
    $data = [];
}

echo json_encode([
    'success' => true,
    'data' => $data,
    'total_customers' => count($data)
], JSON_PRETTY_PRINT);
?>