<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Include PostgreSQL database connection
require_once __DIR__ . '/../DB/DB_connection.php';

try {
    // Get current slot status
    $sql = "SELECT total_spaces, occupied_spaces, available_spaces 
            FROM parking_slots 
            WHERE id = 1 
            LIMIT 1";
    
    $result = db_query($sql);

    if ($result && db_num_rows($result) > 0) {
        $slots = db_fetch_assoc($result);
        echo json_encode([
            'success' => true,
            'total_spaces' => intval($slots['total_spaces']),
            'occupied_spaces' => intval($slots['occupied_spaces']),
            'available_spaces' => intval($slots['available_spaces'])
        ]);
    } else {
        // Default values if no data exists
        echo json_encode([
            'success' => true,
            'total_spaces' => 50,
            'occupied_spaces' => 0,
            'available_spaces' => 50
        ]);
    }
    
} catch (Exception $e) {
    error_log("Get Available Slots Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch parking slot data'
    ]);
}

?>
