<?php
// Load environment variables
if (file_exists(__DIR__ . '/../../.env')) {
    $lines = file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            // Remove inline comments
            $line = preg_replace('/#.*$/', '', $line);
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Database Configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'db');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'photowall');
define('DB_USER', $_ENV['DB_USER'] ?? 'photowall');
define('DB_PASS', $_ENV['DB_PASS'] ?? 'photowall');

// App Configuration
define('APP_NAME', $_ENV['APP_NAME'] ?? 'PC PhotoWall');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost:4000');
define('UPLOAD_ALLOWED_TYPES', explode(',', $_ENV['UPLOAD_ALLOWED_TYPES'] ?? 'image/jpeg,image/png,image/gif,image/webp'));

// Display Configuration (fallback defaults - now per-event configurable)
define('DEFAULT_DISPLAY_COUNT', (int)($_ENV['DEFAULT_DISPLAY_COUNT'] ?? 9));
define('DEFAULT_DISPLAY_INTERVAL', (int)($_ENV['DEFAULT_DISPLAY_INTERVAL'] ?? 5));
define('DEFAULT_DISPLAY_MODE', $_ENV['DEFAULT_DISPLAY_MODE'] ?? 'random');
define('DEFAULT_GRID_COLUMNS', (int)($_ENV['DEFAULT_GRID_COLUMNS'] ?? 3));

// Image Processing Constants
define('IMAGE_MAX_WIDTH', (int)($_ENV['IMAGE_MAX_WIDTH'] ?? 1920));
define('IMAGE_MAX_HEIGHT', (int)($_ENV['IMAGE_MAX_HEIGHT'] ?? 1080));
define('IMAGE_QUALITY_HIGH', (int)($_ENV['IMAGE_QUALITY_HIGH'] ?? 90));
define('IMAGE_QUALITY_MEDIUM', (int)($_ENV['IMAGE_QUALITY_MEDIUM'] ?? 85));
define('THUMBNAIL_MAX_WIDTH', (int)($_ENV['THUMBNAIL_MAX_WIDTH'] ?? 300));
define('THUMBNAIL_MAX_HEIGHT', (int)($_ENV['THUMBNAIL_MAX_HEIGHT'] ?? 300));
define('THUMBNAIL_QUALITY', (int)($_ENV['THUMBNAIL_QUALITY'] ?? 85));

// Image Rotation Constants
define('ROTATION_ANGLE_90', 90);
define('ROTATION_ANGLE_180', 180);
define('ROTATION_ANGLE_270', 270);
define('EXIF_ORIENTATION_NORMAL', 1);

// File Size Constants
define('BYTES_PER_KB', 1024);
define('DEFAULT_MAX_UPLOAD_SIZE', (int)($_ENV['DEFAULT_MAX_UPLOAD_SIZE'] ?? 10485760)); // 10MB

// GPS/Distance Constants
define('EARTH_RADIUS_METERS', 6371000);
define('GPS_MIN_LATITUDE', -90);
define('GPS_MAX_LATITUDE', 90);
define('GPS_MIN_LONGITUDE', -180);
define('GPS_MAX_LONGITUDE', 180);
define('GPS_DEFAULT_RADIUS_METERS', (int)($_ENV['GPS_DEFAULT_RADIUS_METERS'] ?? 100));
define('DISTANCE_KM_THRESHOLD', (int)($_ENV['DISTANCE_KM_THRESHOLD'] ?? 1000));

// QR Code Constants
define('QR_CODE_DEFAULT_SIZE', (int)($_ENV['QR_CODE_DEFAULT_SIZE'] ?? 200));
define('QR_CODE_MARGIN', (int)($_ENV['QR_CODE_MARGIN'] ?? 10));

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('DATA_PATH', ROOT_PATH . '/data');
define('UPLOAD_PATH', DATA_PATH); // Will be set per event
define('UPLOAD_URL', APP_URL . '/data'); // Will be set per event

// Security
define('CSRF_TOKEN_NAME', $_ENV['CSRF_TOKEN_NAME'] ?? 'csrf_token');
define('SESSION_TIMEOUT', (int)($_ENV['SESSION_TIMEOUT'] ?? 3600)); // 1 hour
define('ADMIN_PASSWORD', $_ENV['ADMIN_PASSWORD'] ?? 'ChangeThisSecurePassword123!');

// Error Reporting - Production safe
$isProduction = ($_ENV['APP_ENV'] ?? 'development') === 'production';
$displayErrors = $_ENV['DISPLAY_ERRORS'] ?? ($isProduction ? '0' : '1');
$logErrors = $_ENV['LOG_ERRORS'] ?? '1';

if ($isProduction) {
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', $displayErrors);
    ini_set('log_errors', $logErrors);
    ini_set('error_log', ROOT_PATH . '/logs/php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', $displayErrors);
    ini_set('log_errors', $logErrors);
}

// PHP Upload Settings (set before session_start)
ini_set('max_execution_time', $_ENV['MAX_EXECUTION_TIME'] ?? '300');
ini_set('memory_limit', $_ENV['MEMORY_LIMIT'] ?? '256M');

// Session Configuration (must be set before session_start)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', $isProduction ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    session_start();
}
?>
