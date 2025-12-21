<?php
// PostgreSQL Database Connection Configuration for Render
// Use environment variables for security

// Get environment variables (Render automatically provides these)
$host = getenv('DB_HOST') ?: 'dpg-d535i5emcj7s73dvkak0-a.oregon-postgres.render.com';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'parking_system_3q8a';
$username = getenv('DB_USERNAME') ?: 'smartparking';
$password = getenv('DB_PASSWORD') ?: '';

// Get environment mode
$environment = getenv('ENVIRONMENT') ?: 'development';

// Create connection string for PostgreSQL
$conn_string = "host=$host port=$port dbname=$dbname user=$username password=$password sslmode=require";

// Create PostgreSQL connection
$conn = pg_connect($conn_string);

// Check connection
if (!$conn) {
    error_log("Database Connection Failed: " . pg_last_error());
    
    if ($environment === 'development') {
        die("Connection failed: " . pg_last_error());
    } else {
        die("Database connection error. Please try again later.");
    }
}

// Set timezone
pg_query($conn, "SET TIME ZONE 'Asia/Manila'");

// Set character encoding
pg_set_client_encoding($conn, 'UTF8');

// Configure error reporting based on environment
if ($environment === 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', '/var/log/php_errors.log'); // Render logs path
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
}

/**
 * Helper function to execute query
 */
function db_query($sql) {
    global $conn;
    $result = pg_query($conn, $sql);
    if (!$result) {
        error_log("Query Error: " . pg_last_error($conn) . " | Query: " . $sql);
        return false;
    }
    return $result;
}

/**
 * Helper function to execute prepared query (prevents SQL injection)
 */
function db_prepare($sql, $params = []) {
    global $conn;
    
    // Convert ? placeholders to $1, $2, etc.
    $i = 0;
    $sql = preg_replace_callback('/\?/', function($matches) use (&$i) {
        $i++;
        return '$' . $i;
    }, $sql);
    
    $result = pg_query_params($conn, $sql, $params);
    
    if (!$result) {
        error_log("Query Error: " . pg_last_error($conn) . " | Query: " . $sql);
        return false;
    }
    return $result;
}

/**
 * Helper function to fetch single row
 */
function db_fetch_assoc($result) {
    return pg_fetch_assoc($result);
}

/**
 * Helper function to fetch all rows
 */
function db_fetch_all($result) {
    return pg_fetch_all($result);
}

/**
 * Helper function to get number of rows
 */
function db_num_rows($result) {
    return pg_num_rows($result);
}

/**
 * Helper function to escape string
 */
function db_escape($string) {
    global $conn;
    return pg_escape_string($conn, $string);
}

/**
 * Helper function to get last error
 */
function db_error() {
    global $conn;
    return pg_last_error($conn);
}

/**
 * Helper function to get affected rows
 */
function db_affected_rows($result) {
    return pg_affected_rows($result);
}

/**
 * Helper function to close connection
 */
function db_close() {
    global $conn;
    return pg_close($conn);
}

/**
 * Helper function to insert and get ID
 * Usage: $id = db_insert_id("INSERT INTO table (col1, col2) VALUES ($1, $2) RETURNING id", ['val1', 'val2']);
 */
function db_insert_id($sql, $params = []) {
    global $conn;
    
    // Convert ? placeholders to $1, $2, etc.
    $i = 0;
    $sql = preg_replace_callback('/\?/', function($matches) use (&$i) {
        $i++;
        return '$' . $i;
    }, $sql);
    
    // Ensure RETURNING id is in the query
    if (stripos($sql, 'RETURNING') === false) {
        $sql .= ' RETURNING id';
    }
    
    $result = pg_query_params($conn, $sql, $params);
    
    if (!$result) {
        error_log("Insert Error: " . pg_last_error($conn) . " | Query: " . $sql);
        return false;
    }
    
    $row = pg_fetch_assoc($result);
    return $row['id'];
}

/**
 * Helper function to begin transaction
 */
function db_begin_transaction() {
    global $conn;
    return pg_query($conn, "BEGIN");
}

/**
 * Helper function to commit transaction
 */
function db_commit() {
    global $conn;
    return pg_query($conn, "COMMIT");
}

/**
 * Helper function to rollback transaction
 */
function db_rollback() {
    global $conn;
    return pg_query($conn, "ROLLBACK");
}
?>