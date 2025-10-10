<?php
// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Database Configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'db');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'picturewall');
define('DB_USER', $_ENV['DB_USER'] ?? 'picturewall');
define('DB_PASS', $_ENV['DB_PASS'] ?? 'picturewall');

// App Configuration
define('APP_NAME', $_ENV['APP_NAME'] ?? 'Picturewall');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost:4000');
define('UPLOAD_ALLOWED_TYPES', explode(',', $_ENV['UPLOAD_ALLOWED_TYPES'] ?? 'image/jpeg,image/png,image/gif,image/webp'));

// Display Configuration (fallback defaults - now per-event configurable)
define('DEFAULT_DISPLAY_COUNT', 9);
define('DEFAULT_DISPLAY_INTERVAL', 5);
define('DEFAULT_DISPLAY_MODE', 'random');
define('DEFAULT_GRID_COLUMNS', 3);

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('DATA_PATH', ROOT_PATH . '/data');
define('UPLOAD_PATH', DATA_PATH); // Will be set per event
define('UPLOAD_URL', APP_URL . '/data'); // Will be set per event

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600); // 1 hour
define('ADMIN_PASSWORD', $_ENV['ADMIN_PASSWORD'] ?? 'admin123');

// Error Reporting - Production safe
$appEnv = $_ENV['APP_ENV'] ?? 'development';
if ($appEnv === 'production') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', ROOT_PATH . '/logs/php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// PHP Upload Settings (set before session_start)
// Server limits removed - only system limits apply
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');

// Session Configuration (must be set before session_start)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', $appEnv === 'production' ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    session_start();
}
?>
