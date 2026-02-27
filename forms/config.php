<?php
// Base URL relative to your web root
// define("BASE_URL", "/new/forms/");
// define('DASHBOARD_URL', '/new/dashboard/');
// // define("BASE_URL", "http://localhost/new/");

// project root URL
define("BASE_URL", "http://localhost/mechanics_tracer/");

// forms folder URL
define("FORMS_URL", BASE_URL . "forms/");

// dashboard folder URL
define("DASHBOARD_URL", BASE_URL . "dashboard/");
define("DASHBOARD_PATH", $_SERVER['DOCUMENT_ROOT'] . "/mechanics_tracer/dashboard/");

$host = "localhost";
$db_name = "mechanic_tracer"; // use your database name
$username = "root";          // your DB username
$password = "";              // your DB password

// Create connection
$conn = new mysqli($host, $username, $password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// else{
//     echo "connect sucess";
// }
// ?>