<?php
/**
 * ADF SYSTEM - Multi-Business Management Platform
 * Configuration File
 */

if (!ob_get_level()) {
    ob_start();
}

// Prevent direct access
defined('APP_ACCESS') or define('APP_ACCESS', true);

// ============================================
// APPLICATION SETTINGS
// ============================================
define('APP_NAME', 'ADF System - Multi-Business Management');
define('APP_VERSION', '2.0.0');
define('APP_YEAR', '2026');
define('DEVELOPER_NAME', 'Ariefsystemdesign.net');
define('DEVELOPER_LOGO', 'assets/img/developer-logo.png');

// ============================================
// DATABASE CONFIGURATION
// ============================================
// Local config (override for production in separate file if needed)
$isProduction = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') === false && 
                strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') === false);

if ($isProduction) {
    // Production (Hosting) - uses adf_system database prefixed
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'adfb2574_adf_system');
    define('DB_USER', 'adfb2574_adfsystem');
    define('DB_PASS', '@Nnoc2025');
} else {
    // Local development - uses adf_system as master database
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'adf_system');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}
define('DB_CHARSET', 'utf8mb4');

// ============================================
// PATH CONFIGURATION
// ============================================
define('BASE_PATH', dirname(dirname(__FILE__)));

$port = $_SERVER['SERVER_PORT'] ?? '80';
$portSuffix = ($port != '80' && $port != '443') ? ':' . $port : '';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . '/adf_system');

// ============================================
// SESSION CONFIGURATION
// ============================================
define('SESSION_NAME', 'NARAYANA_SESSION');
define('SESSION_LIFETIME', 3600 * 8);

// ============================================
// TIMEZONE
// ============================================
date_default_timezone_set('Asia/Jakarta');

// ============================================
// ERROR REPORTING
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// CURRENCY FORMAT
// ============================================
define('CURRENCY_SYMBOL', 'Rp');
define('CURRENCY_DECIMAL', 0);

// ============================================
// PAGINATION
// ============================================
define('RECORDS_PER_PAGE', 25);

// ============================================
// DATE FORMAT
// ============================================
define('DATE_FORMAT', 'd/m/Y');
define('DATETIME_FORMAT', 'd/m/Y H:i');
define('TIME_FORMAT', 'H:i');

// ============================================
// MULTI-BUSINESS CONFIGURATION
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

require_once __DIR__ . '/../includes/business_helper.php';

$activeBusinessId = getActiveBusinessId();
$BUSINESS_CONFIG = getActiveBusinessConfig();

define('ACTIVE_BUSINESS_ID', $activeBusinessId);
define('BUSINESS_NAME', $BUSINESS_CONFIG['name']);
define('BUSINESS_TYPE', $BUSINESS_CONFIG['business_type']);
define('BUSINESS_ICON', $BUSINESS_CONFIG['theme']['icon']);
define('BUSINESS_COLOR', $BUSINESS_CONFIG['theme']['color_primary']);

// ============================================
// LANGUAGE CONFIGURATION
// ============================================
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/language.php';