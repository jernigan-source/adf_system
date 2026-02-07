<?php
define('APP_ACCESS', true);
require_once 'config/config.php';

// Start session with correct name
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

echo "<h2>Session Debug</h2>";
echo "<pre>";

echo "=== SESSION CONFIGURATION ===\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Name: " . session_name() . "\n";
echo "Session Save Path: " . session_save_path() . "\n";
echo "Session Cookie Params:\n";
print_r(session_get_cookie_params());

echo "\n=== SESSION DATA ===\n";
if (empty($_SESSION)) {
    echo "SESSION IS EMPTY!\n";
} else {
    print_r($_SESSION);
}

echo "\n=== COOKIES ===\n";
print_r($_COOKIE);

echo "\n=== SERVER INFO ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";

echo "</pre>";

echo "<hr>";
echo "<a href='login.php'>Go to Login</a> | ";
echo "<a href='home.php'>Go to Home</a> | ";
echo "<a href='debug-permissions.php'>Check Permissions</a>";
