<?php
session_start();
require_once(__DIR__ . "/../../forms/config.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized or invalid role']);
    exit();
}

$user_id = $_SESSION['user_id'];
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

if (!$data || empty($data['mechanic_id']) || empty($data['problem'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$mechanic_id = (int)$data['mechanic_id'];
$problem_text = "AI Detected Problem: " . trim($data['problem']);
$driver_lat = isset($data['lat']) ? floatval($data['lat']) : 0.0;
$driver_lng = isset($data['lng']) ? floatval($data['lng']) : 0.0;
$services_requested = isset($data['services_requested']) ? trim($data['services_requested']) : ''; // e.g. "Engine Repair, ABS Repair"

global $conn;

// 1. Fetch driver details
$stmt = $conn->prepare("SELECT id, vehicle_type FROM drivers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$driver_result = $stmt->get_result();

if ($driver_row = $driver_result->fetch_assoc()) {
    $driver_id = $driver_row['id'];
    $vehicle_type = $driver_row['vehicle_type'];
} else {
    echo json_encode(['success' => false, 'message' => 'Driver record not found']);
    exit();
}

// 2. Check for existing pending booking with this mechanic
$stmt_check = $conn->prepare("SELECT id FROM bookings WHERE driver_id = ? AND mechanic_id = ? AND booking_status = 'pending'");
$stmt_check->bind_param("ii", $driver_id, $mechanic_id);
$stmt_check->execute();
if ($stmt_check->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'You already have a pending booking with this mechanic.']);
    exit();
}

// 3. Insert new booking
$stmt_insert = $conn->prepare("INSERT INTO bookings (driver_id, mechanic_id, service_requested, vehicle_type, driver_latitude, driver_longitude, notes, booking_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");

$stmt_insert->bind_param("iisssds", 
    $driver_id, 
    $mechanic_id, 
    $services_requested, 
    $vehicle_type, 
    $driver_lat, 
    $driver_lng, 
    $problem_text
);

if ($stmt_insert->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => 'Booking confirmed successfully. The mechanic will review your request.',
        'booking_id' => $stmt_insert->insert_id
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create booking. Database error.']);
}
?>
