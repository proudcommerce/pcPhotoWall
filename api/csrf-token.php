<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Generate fresh CSRF token
$token = generateCSRFToken();

// Return token
echo json_encode(['token' => $token]);
?>
