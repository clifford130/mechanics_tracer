<?php
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
if (file_exists($root . '/mechanics_tracer/forms/config.php')) {
    require_once($root . '/mechanics_tracer/forms/config.php');
} else {
    require_once($root . '/forms/config.php');
}

$res = $conn->query("SELECT id, garage_name, latitude, longitude FROM mechanics");
echo "<h1>Mechanics in Database</h1>";
while ($row = $res->fetch_assoc()) {
    echo "<p>ID: {$row['id']} | Name: {$row['garage_name']} | Lat: {$row['latitude']} | Lng: {$row['longitude']}</p>";
}
?>
