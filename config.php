<?php
// Main Configuration File
require_once __DIR__ . '/config_loader.php';

// Database Configuration
// Note: You can also move these to environment variables if supported by the host.
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
