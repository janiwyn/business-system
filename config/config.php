<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Remote Ordering Configuration
define('QR_EXPIRY_HOURS', 24);
define('ORDER_PREFIX', 'ORD');
define('API_VERSION', 'v1');
define('ENABLE_CORS', true);

// Base URLs - UPDATE THESE IF NEEDED
define('BASE_URL', 'http://localhost/business-system');
define('API_BASE_URL', BASE_URL . '/api');
define('CUSTOMER_SITE_URL', BASE_URL . '/customer');
define('ASSETS_URL', BASE_URL . '/assets');

// QR Code Settings
define('QR_CODE_SIZE', 300);
define('QR_CODE_MARGIN', 10);

// File Paths
define('ROOT_PATH', dirname(__DIR__));
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// Security
define('API_SECRET_KEY', 'your-secret-key-here-change-in-production');

// Timezone
date_default_timezone_set('Africa/Kampala');

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
