<?php
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
if (file_exists($root . '/mechanics_tracer/forms/config.php')) {
    require_once($root . '/mechanics_tracer/forms/config.php');
} else {
    require_once($root . '/forms/config.php');
}
$conn->query("ALTER TABLE users MODIFY COLUMN status ENUM('active', 'suspended', 'deleted') DEFAULT 'active'");
echo "done";
?>