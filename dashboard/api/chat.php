<?php
session_start();
require_once(__DIR__ . "/../../forms/config.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

if ($action === 'send') {
    $booking_id = $_POST['booking_id'] ?? 0;
    $message = $_POST['message'] ?? '';

    if (!$booking_id || !$message) {
        echo json_encode(['success' => false, 'message' => 'Missing fields']);
        exit();
    }

    // Check if user is part of the booking
    $stmt = $conn->prepare("
        SELECT b.id 
        FROM bookings b 
        JOIN drivers d ON b.driver_id = d.id 
        JOIN mechanics m ON b.mechanic_id = m.id 
        WHERE b.id = ? AND (d.user_id = ? OR m.user_id = ?)
    ");
    $stmt->bind_param("iii", $booking_id, $user_id, $user_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized for this booking']);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO messages (booking_id, sender_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $booking_id, $user_id, $message);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error']);
    }

} elseif ($action === 'fetch') {
    $booking_id = $_GET['booking_id'] ?? 0;
    $last_id = $_GET['last_id'] ?? 0;

    if (!$booking_id) {
        echo json_encode(['success' => false, 'message' => 'Missing booking_id']);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT b.id 
        FROM bookings b 
        JOIN drivers d ON b.driver_id = d.id 
        JOIN mechanics m ON b.mechanic_id = m.id 
        WHERE b.id = ? AND (d.user_id = ? OR m.user_id = ?)
    ");
    $stmt->bind_param("iii", $booking_id, $user_id, $user_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    // Mark messages as read (where sender is NOT current user)
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE booking_id = ? AND sender_id != ? AND is_read = 0");
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();

    $stmt = $conn->prepare("
        SELECT m.*, u.full_name as sender_name 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.booking_id = ? 
        ORDER BY m.created_at ASC
    ");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'messages' => $messages]);
}
?>
