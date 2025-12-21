<?php
header("Content-Type: application/json");

// Include database connection
require_once '../DB/DB_connection.php';

// Get POST data
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$plate = trim($_POST['plate'] ?? '');
$vehicle = trim($_POST['vehicle'] ?? '');
$password = trim($_POST['password'] ?? '');

$fieldErrors = [];

// Backend validation
if ($name === '') {
    $fieldErrors['name'] = "Full Name is required!";
}
if ($email === '') {
    $fieldErrors['email'] = "Email Address is required!";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $fieldErrors['email'] = "Invalid email format!";
} elseif (!str_ends_with(strtolower($email), '@gmail.com')) {
    $fieldErrors['email'] = "Email must end with @gmail.com!";
}
if ($plate === '') {
    $fieldErrors['plate'] = "Plate Number is required!";
}
if ($vehicle === '') {
    $fieldErrors['vehicle'] = "Vehicle Type is required!";
}
if ($password === '') {
    $fieldErrors['password'] = "Password is required!";
}

if (!empty($fieldErrors)) {
    echo json_encode(["error" => "Validation failed.", "fieldErrors" => $fieldErrors]);
    exit;
}

// Check if email already exists
$sql = "SELECT id FROM customers WHERE email = ?";
$result = db_prepare($sql, [$email]);

if ($result && db_num_rows($result) > 0) {
    echo json_encode(["error" => "Email is already registered."]);
    exit;
}

// Check if plate already exists
$sql = "SELECT id FROM customers WHERE plate = ?";
$result = db_prepare($sql, [$plate]);

if ($result && db_num_rows($result) > 0) {
    echo json_encode(["error" => "Plate number is already registered."]);
    exit;
}

// Insert data (hash password for security)
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO customers (name, email, plate, vehicle, password) VALUES (?, ?, ?, ?, ?)";
$result = db_prepare($sql, [$name, $email, $plate, $vehicle, $hashedPassword]);

if ($result) {
    echo json_encode(["success" => "Registration successful!"]);
} else {
    echo json_encode(["error" => "Database error: " . db_error()]);
}

// Connection will be closed automatically at script end
?>