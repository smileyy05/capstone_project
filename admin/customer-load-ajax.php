<?php
header('Content-Type: application/json');
// Include PostgreSQL database connection
require_once __DIR__ . '/../DB/DB_connection.php';

$response = ['success' => '', 'error' => '', 'errors' => []];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['error'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$amount = trim($_POST['amount'] ?? '');

// Validate inputs
if ($email === '') {
    $response['errors']['email'] = "Email Address is required!";
}

if ($password === '') {
    $response['errors']['password'] = "Password is required!";
}

if ($amount === '' || !preg_match('/^[0-9]+$/', $amount) || intval($amount) <= 0) {
    $response['errors']['amount'] = "Amount must be a whole number greater than 0!";
}

if (count($response['errors']) === 0) {
    try {
        // Check if user exists and get password and current balance
        $result = db_prepare("SELECT id, password, balance FROM customers WHERE email = $1", [$email]);
        
        if (!$result || db_num_rows($result) === 0) {
            $response['errors']['email'] = "Account not found!";
        } else {
            $row = db_fetch_assoc($result);
            
            // Verify password
            if (!password_verify($password, $row['password'])) {
                $response['errors']['password'] = "Incorrect password!";
            } else {
                // Calculate new balance
                $intAmount = intval($amount);
                $newBalance = $row['balance'] + $intAmount;
                
                // Update balance using customer ID
                $updateResult = db_prepare(
                    "UPDATE customers SET balance = $1 WHERE id = $2",
                    [$newBalance, $row['id']]
                );
                
                if ($updateResult) {
                    $response['success'] = "✅ Successfully loaded ₱" . number_format($intAmount, 0) . ".<br>💳 New Balance: ₱" . number_format($newBalance, 2);
                } else {
                    $response['error'] = "Failed to load balance!";
                }
            }
        }
    } catch (Exception $e) {
        error_log("Load Balance Error: " . $e->getMessage());
        $response['error'] = "An error occurred while processing your request!";
    }
}

echo json_encode($response);
?>
