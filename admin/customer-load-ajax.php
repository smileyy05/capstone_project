<?php
header('Content-Type: application/json');

// Include PostgreSQL database connection
require_once '../DB/DB_connection.php';

$response = ['success' => '', 'error' => '', 'errors' => []];

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$amount = $_POST['amount'] ?? '';

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
        // Check if user exists and get password
        $result = db_prepare("SELECT password FROM customers WHERE email = $1", [$email]);
        
        if (!$result || db_num_rows($result) === 0) {
            $response['errors']['email'] = "Account not found!";
        } else {
            $row = db_fetch_assoc($result);
            
            // Verify password
            if (!password_verify($password, $row['password'])) {
                $response['errors']['password'] = "Incorrect password!";
            } else {
                // Update balance
                $intAmount = intval($amount);
                $updateResult = db_prepare(
                    "UPDATE customers SET balance = balance + $1 WHERE email = $2",
                    [$intAmount, $email]
                );
                
                if ($updateResult) {
                    $response['success'] = "Balance loaded successfully!";
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