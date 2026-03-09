<?php
session_start();
require_once("../config.php");

header("Content-Type: application/json");

if(!isset($_SESSION['user_id'])){
    echo json_encode(["success"=>false]);
    exit();
}

$user_id = $_SESSION['user_id'];

$data = json_decode(file_get_contents("php://input"), true);

$mechanic_id = intval($data["mechanic_id"]);
$service = $data["service"];
$vehicle = $data["vehicle"];
$lat = $data["lat"];
$lng = $data["lng"];

/* get driver id from drivers table */
$stmt = $conn->prepare("SELECT id FROM drivers WHERE user_id=?");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$res = $stmt->get_result();

if($row = $res->fetch_assoc()){

    $driver_id = $row["id"];

    $stmt = $conn->prepare("INSERT INTO bookings
    (driver_id, mechanic_id, service_requested, vehicle_type, driver_latitude, driver_longitude)
    VALUES (?,?,?,?,?,?)");

    $stmt->bind_param("iissdd",
        $driver_id,
        $mechanic_id,
        $service,
        $vehicle,
        $lat,
        $lng
    );

    $stmt->execute();

    echo json_encode([
        "success"=>true,
        "booking_id"=>$conn->insert_id
    ]);
}
else{
    echo json_encode(["success"=>false]);
}