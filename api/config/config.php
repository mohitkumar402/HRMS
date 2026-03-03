<?php
// ============================================================
// ENTERPRISE HRMS - APPLICATION CONFIGURATION
// ============================================================

define('APP_NAME',    'Enterprise HRMS');
define('APP_VERSION', '1.0.0');
define('APP_ENV',     getenv('APP_ENV') ?: 'development'); // production | development

// Base URL (update for your environment)
define('APP_URL',     'http://localhost/HRMs');
define('API_URL',     APP_URL . '/api');

// Security
define('JWT_SECRET',  getenv('JWT_SECRET') ?: 'hrms_jwt_secret_key_change_in_production_2025!@#');
define('JWT_EXPIRY',  86400);          // 24 hours
define('JWT_REFRESH', 604800);         // 7 days
define('BCRYPT_COST', 12);

// File Upload
define('UPLOAD_DIR',  __DIR__ . '/../../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_MIME', [
    'image/jpeg','image/png','image/gif','image/webp',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
]);

// Pagination
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE',     100);

// Rate limiting
define('RATE_LIMIT_REQUESTS', 200);
define('RATE_LIMIT_WINDOW',   60); // seconds

// Email
define('MAIL_FROM',    'noreply@hrms.com');
define('MAIL_FROM_NAME', 'Enterprise HRMS');

// Error reporting
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Timezone
date_default_timezone_set('Asia/Kolkata');
