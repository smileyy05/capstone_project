<?php
session_start();

// Include database connection
require_once '../DB/DB_connection.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = "Please enter both email and password!";
        header("Location: customer-login.php");
        exit();
    }
    
    // Query to check if customer exists using prepared statement
    $sql = "SELECT * FROM customers WHERE email = ?";
    $result = db_prepare($sql, [$email]);
    
    if ($result && db_num_rows($result) > 0) {
        $row = db_fetch_assoc($result);
        
        // Verify password
        if (password_verify($password, $row['password'])) {
            // Password is correct, start session
            $_SESSION['customer_id'] = $row['id'];
            $_SESSION['customer_name'] = $row['name'];
            $_SESSION['customer_email'] = $row['email'];
            $_SESSION['plate_number'] = $row['plate'];
            $_SESSION['vehicle_type'] = $row['vehicle'];
            $_SESSION['balance'] = $row['balance'];
            
            // Optional: Update last login time
            $update_sql = "UPDATE customers SET last_login = NOW() WHERE id = ?";
            db_prepare($update_sql, [$row['id']]);
            
            // Redirect to account page
            header("Location: customer-account.php");
            exit();
        } else {
            // Invalid password
            $_SESSION['login_error'] = "Invalid email or password!";
            header("Location: customer-login.php");
            exit();
        }
    } else {
        // User not found or query error
        $_SESSION['login_error'] = "Invalid email or password!";
        header("Location: customer-login.php");
        exit();
    }
    
} else {
    // If not POST request, redirect to login page
    header("Location: customer-login.php");
    exit();
}

// Connection will be closed automatically at script end
// or you can explicitly call db_close() if needed
?>