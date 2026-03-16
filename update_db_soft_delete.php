<?php
require_once __DIR__ . '/forms/config.php';
$conn->query("ALTER TABLE users MODIFY COLUMN status ENUM('active', 'suspended', 'deleted') DEFAULT 'active'");
echo "done";
?>
