<?php
// Force clear ALL session cookies
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Start fresh session
session_start();
session_unset();
session_destroy();

// Clear all cookies
foreach ($_COOKIE as $key => $value) {
    setcookie($key, '', time() - 3600, '/');
}

// Redirect to login
header('Location: ' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/adf_system/login.php');
exit;
?>
