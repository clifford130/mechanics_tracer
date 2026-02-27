<?php
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
//     echo "connection succesful";
// }
?>