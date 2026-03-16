<?php
include "forms/config.php";
$conn->query("ALTER TABLE users ADD COLUMN status ENUM('active', 'suspended') DEFAULT 'active'");
echo "Done";
?>
