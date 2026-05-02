<?php
echo "<h1>System Check</h1>";
echo "<p>Host: " . $_SERVER['HTTP_HOST'] . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Current Script: " . $_SERVER['SCRIPT_FILENAME'] . "</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<hr>";
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$test_path = $root . '/forms/config.php';
echo "<p>Checking for config at: $test_path</p>";
if (file_exists($test_path)) {
    echo "<p style='color:green'>Found config.php!</p>";
} else {
    echo "<p style='color:red'>config.php NOT found at root. Checking subfolder...</p>";
    $test_path_sub = $root . '/mechanics_tracer/forms/config.php';
    if (file_exists($test_path_sub)) {
        echo "<p style='color:orange'>Found config.php in /mechanics_tracer/ subfolder.</p>";
    } else {
        echo "<p style='color:red'>config.php NOT found anywhere.</p>";
    }
}
?>
