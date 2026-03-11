<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT']."/mechanics_tracer/forms/config.php");

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mechanic'){
    echo json_encode(["status"=>"error","message"=>"Unauthorized"]);
    exit();
}

if(!isset($_POST['booking_id'])){
    echo json_encode(["status"=>"error","message"=>"Booking ID missing"]);
    exit();
}

$booking_id = intval($_POST['booking_id']);

$stmt = $conn->prepare("UPDATE bookings SET booking_status='accepted' WHERE id=?");
$stmt->bind_param("i",$booking_id);

if($stmt->execute()){
    echo json_encode(["status"=>"success","message"=>"Booking accepted"]);
}else{
    echo json_encode(["status"=>"error","message"=>"Failed to accept booking"]);
}