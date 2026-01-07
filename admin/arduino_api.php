<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
require_once __DIR__ . '/../DB/DB_connection.php';

try {
    // Get unprocessed commands (oldest first)
    $sql = "SELECT id, customer_id, customer_name, qr_code, action, station, created_at 
            FROM arduino_commands 
            WHERE processed = FALSE 
            ORDER BY created_at ASC 
            LIMIT 1";
    
    $result = db_query($sql);
    
    if (!$result) {
        throw new Exception('Database query failed: ' . db_error());
    }
    
    if (db_num_rows($result) > 0) {
        $command = db_fetch_assoc($result);
        $command_id = $command['id'];
        
        // Mark as processed
        $update_sql = "UPDATE arduino_commands 
                       SET processed = TRUE, processed_at = NOW() 
                       WHERE id = $1";
        $update_result = db_prepare($update_sql, [$command_id]);
        
        if (!$update_result) {
            error_log("Failed to mark command as processed: " . db_error());
        }
        
        // Return command to Arduino bridge
        echo json_encode([
            'success' => true,
            'has_command' => true,
            'command' => [
                'id' => (int)$command['id'],
                'customer_id' => (int)$command['customer_id'],
                'customer_name' => $command['customer_name'],
                'action' => $command['action'],
                'station' => $command['station'],
                'qr_code' => $command['qr_code'],
                'created_at' => $command['created_at']
            ]
        ]);
    } else {
        // No commands waiting
        echo json_encode([
            'success' => true,
            'has_command' => false,
            'message' => 'No pending commands'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Arduino API Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'has_command' => false,
        'message' => 'API error: ' . $e->getMessage()
    ]);
}
?>
