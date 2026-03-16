<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . "/mechanics_tracer/forms/config.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'driver'){
    die("Unauthorized");
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['booking_id'], $_POST['stars'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id FROM drivers WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $driver = $stmt->get_result()->fetch_assoc();
    
    if(!$driver){
        die("Driver not found");
    }
    
    $driver_id = $driver['id'];
    $booking_id = (int)$_POST['booking_id'];
    $stars = (int)$_POST['stars'];
    $review = $_POST['review'] ?? '';

    // fetch mechanic_id
    $chk = $conn->prepare("SELECT mechanic_id FROM bookings WHERE id = ? AND driver_id = ? AND booking_status = 'completed'");
    $chk->bind_param("ii", $booking_id, $driver_id);
    $chk->execute();
    $booking = $chk->get_result()->fetch_assoc();

    if(!$booking) {
        die("Invalid booking");
    }

    $mechanic_id = $booking['mechanic_id'];

    if ($stars < 1 || $stars > 5) {
        die("Invalid star rating");
    }
    
    // check if rated
    $rc = $conn->prepare("SELECT id FROM ratings WHERE booking_id = ?");
    $rc->bind_param("i", $booking_id);
    $rc->execute();
    if($rc->get_result()->num_rows > 0) {
        // Already rated
        header("Location: /mechanics_tracer/forms/bookings/driver_bookings.php");
        exit;
    }
    
    $ins = $conn->prepare("INSERT INTO ratings (booking_id, driver_id, mechanic_id, stars, review) VALUES (?, ?, ?, ?, ?)");
    $ins->bind_param("iiiis", $booking_id, $driver_id, $mechanic_id, $stars, $review);
    $ins->execute();
    
    header("Location: /mechanics_tracer/forms/bookings/driver_bookings.php");
    exit;
} else {
    header("Location: /mechanics_tracer/forms/bookings/driver_bookings.php");
    exit;
}
