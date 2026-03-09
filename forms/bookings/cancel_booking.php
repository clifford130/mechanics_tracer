<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . "/mechanics_tracer/forms/config.php");

// Only drivers can cancel their bookings
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'driver'){
    header("Location: /mechanics_tracer/forms/auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if booking_id is provided
if(!isset($_POST['booking_id']) || empty($_POST['booking_id'])){
    die("Booking ID is required.");
}

$booking_id = intval($_POST['booking_id']);

// Get driver ID
$stmt = $conn->prepare("SELECT id FROM drivers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$driver = $result->fetch_assoc();

if(!$driver){
    die("Driver profile not found.");
}

$driver_id = $driver['id'];

// Check if the booking belongs to this driver and is pending
$stmt = $conn->prepare("SELECT booking_status FROM bookings WHERE id = ? AND driver_id = ?");
$stmt->bind_param("ii", $booking_id, $driver_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if(!$booking){
    die("Booking not found or you are not authorized.");
}

if($booking['booking_status'] != 'pending'){
    die("Only pending bookings can be cancelled.");
}

// Update booking status to cancelled
$stmt = $conn->prepare("UPDATE bookings SET booking_status='cancelled', updated_at=NOW() WHERE id=?");
$stmt->bind_param("i", $booking_id);
if($stmt->execute()){
    header("Location: /mechanics_tracer/forms/bookings/driver_bookings.php");
    exit();
} else {
    die("Failed to cancel booking. Please try again.");
}
?>