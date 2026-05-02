<?php
/**
 * forms/config.php
 * Main configuration and database connection.
 * This file is included by almost all other PHP files.
 */

// Include the environment-aware path loader
// Note: We use a relative path here because config_loader.php is in the root,
// and forms/config.php is always in the /forms/ directory relative to root.
// However, the rule says use absolute paths for includes.
// To be safe and compliant with the "Smart Loader" logic:
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
if (file_exists($root . '/mechanics_tracer/config_loader.php')) {
    require_once($root . '/mechanics_tracer/config_loader.php');
} else {
    require_once($root . '/config_loader.php');
}

// Database Configuration
$host = "localhost";
$db_name = "mechanics_tracer"; 
$username = "root";          
$password = "";              

// Create connection
$conn = new mysqli($host, $username, $password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>