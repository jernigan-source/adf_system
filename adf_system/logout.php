<?php
/**
 * NARAYANA HOTEL MANAGEMENT SYSTEM
 * Logout
 */

define('APP_ACCESS', true);
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
$auth->logout();

redirect(BASE_URL . '/login.php');
?>
