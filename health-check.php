<?php
/**
 * Health Check Endpoint for Render
 * This endpoint is used by Render to monitor the application health
 */
header('Content-Type: application/json');

$health_status = [
    'status' => 'unknown',
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

try {
    // Check PHP version
    $health_status['checks']['php'] = [
        'status' => 'ok',
        'version' => PHP_VERSION
    ];
    
    // Check database connection
    require_once __DIR__ . '/DB/DB_connection.php';
    
    if ($conn) {
        // Test database with a simple query
        $result = pg_query($conn, "SELECT 1 as test");
        
        if ($result && pg_fetch_assoc($result)) {
            $health_status['checks']['database'] = [
                'status' => 'ok',
                'type' => 'PostgreSQL',
                'connected' => true
            ];
        } else {
            throw new Exception('Database query failed');
        }
        
        pg_close($conn);
    } else {
        throw new Exception('Database connection failed');
    }
    
    // Check required PHP extensions
    $required_extensions = ['pgsql', 'pdo', 'pdo_pgsql', 'json', 'mbstring'];
    $missing_extensions = [];
    
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $missing_extensions[] = $ext;
        }
    }
    
    if (empty($missing_extensions)) {
        $health_status['checks']['extensions'] = [
            'status' => 'ok',
            'loaded' => $required_extensions
        ];
    } else {
        $health_status['checks']['extensions'] = [
            'status' => 'warning',
            'missing' => $missing_extensions
        ];
    }
    
    // Check write permissions (for logs, uploads, etc.)
    $writable_dirs = ['logs', 'tmp', 'cache'];
    $permission_issues = [];
    
    foreach ($writable_dirs as $dir) {
        if (file_exists($dir) && !is_writable($dir)) {
            $permission_issues[] = $dir;
        }
    }
    
    if (empty($permission_issues)) {
        $health_status['checks']['permissions'] = [
            'status' => 'ok'
        ];
    } else {
        $health_status['checks']['permissions'] = [
            'status' => 'warning',
            'non_writable' => $permission_issues
        ];
    }
    
    // Overall status
    $health_status['status'] = 'healthy';
    http_response_code(200);
    
} catch (Exception $e) {
    $health_status['status'] = 'unhealthy';
    $health_status['error'] = $e->getMessage();
    $health_status['checks']['database'] = [
        'status' => 'error',
        'error' => $e->getMessage()
    ];
    http_response_code(503);
}

echo json_encode($health_status, JSON_PRETTY_PRINT);
?>
