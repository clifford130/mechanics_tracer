<?php
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
if (file_exists($root . '/mechanics_tracer/forms/config.php')) {
    require_once($root . '/mechanics_tracer/forms/config.php');
} else {
    require_once($root . '/forms/config.php');
}
$conn->query("ALTER TABLE users ADD COLUMN status ENUM('active', 'suspended') DEFAULT 'active'");
echo "Done";
?>