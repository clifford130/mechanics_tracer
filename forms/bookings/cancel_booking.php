<?php
session_start();
require_once("../config.php");

$data = json_decode(file_get_contents("php://input"), true);

$booking_id = intval($data["booking_id"]);

$stmt = $conn->prepare("UPDATE bookings SET status='cancelled' WHERE id=?");
$stmt->bind_param("i",$booking_id);
$stmt->execute();

echo json_encode([
"success"=>true,
"message"=>"Booking cancelled"
]);