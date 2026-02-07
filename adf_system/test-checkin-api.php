<?php
/**
 * Test Check-in API dengan session simulation
 */

// Start session
session_start();

// Simulate logged in user
$_SESSION['user_id'] = 1;
$_SESSION['is_logged_in'] = true;

// Simulate POST data
$_POST['booking_id'] = 1;

// Include the API file
include '../api/checkin-guest.php';
