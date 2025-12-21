<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../DB/DB_connection.php';

echo "<h2>Testing Duplicate Entry Prevention</h2>";

// Get first customer
$sql = "SELECT id, name, qr_code FROM customers WHERE archived = 0 LIMIT 1";
$result = db_query($sql);

if ($result && db_num_rows($result) > 0) {
    $customer = db_fetch_assoc($result);
    $customer_id = $customer['id'];
    $qr_code = $customer['qr_code'];
    
    echo "<h3>Test Customer:</h3>";
    echo "ID: " . $customer['id'] . "<br>";
    echo "Name: " . $customer['name'] . "<br>";
    echo "QR Code: " . $qr_code . "<br><br>";
    
    // Check current active entries
    $check_sql = "SELECT id, entry_time, status FROM parking_logs 
                  WHERE customer_id = ? AND status = 'entered'
                  ORDER BY entry_time DESC";
    
    $check_result = db_prepare($check_sql, [$customer_id]);
    
    echo "<h3>Active Entries Check:</h3>";
    if ($check_result && db_num_rows($check_result) > 0) {
        echo "❌ <strong style='color: red;'>Customer HAS active entry (already inside)</strong><br>";
        echo "Number of active entries: " . db_num_rows($check_result) . "<br><br>";
        
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Entry ID</th><th>Entry Time</th><th>Status</th></tr>";
        
        $entries = db_fetch_all($check_result);
        foreach ($entries as $row) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['entry_time'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
        
        echo "✅ <strong style='color: green;'>verify_qr.php should BLOCK this customer</strong><br>";
        echo "Expected error: 'You have already entered the parking lot. Please exit first.'<br>";
        
    } else {
        echo "✅ <strong style='color: green;'>Customer has NO active entry (can enter)</strong><br>";
        echo "verify_qr.php should ALLOW this customer<br>";
    }
    
    echo "<hr>";
    echo "<h3>Test verify_qr.php now:</h3>";
    echo "<p>1. Go to enter.html</p>";
    echo "<p>2. Upload QR code for: <strong>" . $customer['name'] . "</strong></p>";
    echo "<p>3. QR Code text: <code>" . $qr_code . "</code></p>";
    
    if ($check_result && db_num_rows($check_result) > 0) {
        echo "<p style='color: red;'><strong>Expected Result: ERROR MESSAGE (already entered)</strong></p>";
    } else {
        echo "<p style='color: green;'><strong>Expected Result: Show dashboard (can enter)</strong></p>";
    }
    
    echo "<hr>";
    echo "<h3>Quick Actions:</h3>";
    echo "<form method='post' style='display: inline;'>";
    echo "<input type='hidden' name='action' value='clear_entries'>";
    echo "<input type='hidden' name='customer_id' value='" . $customer_id . "'>";
    echo "<button type='submit'>Clear This Customer's Entries</button>";
    echo "</form>";
    
    echo " | ";
    
    echo "<form method='post' style='display: inline;'>";
    echo "<input type='hidden' name='action' value='add_entry'>";
    echo "<input type='hidden' name='customer_id' value='" . $customer_id . "'>";
    echo "<input type='hidden' name='qr_code' value='" . $qr_code . "'>";
    echo "<button type='submit'>Add Entry (for testing block)</button>";
    echo "</form>";
    
    // Handle form actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'clear_entries' && isset($_POST['customer_id'])) {
            $del_id = intval($_POST['customer_id']);
            $del_sql = "DELETE FROM parking_logs WHERE customer_id = ?";
            db_prepare($del_sql, [$del_id]);
            echo "<script>alert('Entries cleared!'); window.location.reload();</script>";
        }
        
        if ($_POST['action'] === 'add_entry' && isset($_POST['customer_id'])) {
            $add_id = intval($_POST['customer_id']);
            $add_qr = $_POST['qr_code'];
            $time = date('Y-m-d H:i:s');
            $add_sql = "INSERT INTO parking_logs (customer_id, qr_code, entry_time, status) 
                        VALUES (?, ?, ?, 'entered')";
            db_prepare($add_sql, [$add_id, $add_qr, $time]);
            echo "<script>alert('Entry added!'); window.location.reload();</script>";
        }
    }
    
} else {
    echo "No customers found!";
}
?>